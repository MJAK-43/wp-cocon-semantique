<?php
if (!defined('ABSPATH')) exit;

class CSB_Generator {
    private $freepik_api_key;
    private $api_key;
    private $model;
    private $temperature;
    private $style;
    private $image_description;

    private function getPromptArticle($keyword, $depth) {
        return "Tu es un expert en SEO. Génère une structure hiérarchique de cocon sémantique en texte brut.
    Consignes :
    - Utilise des tirets `-` pour chaque point.
    - Utilise **4 espaces** pour chaque niveau d’imbrication (indentation).
    - Le mot-clé principal est : \"$keyword\"
    - $depth sous-thèmes, chacun avec $depth sous-sous-thèmes.
    Pas de commentaires, pas de balises, juste le texte hiérarchique.";
    }
    private function build_content_prompt(array $tree) {
        $structure = $this->to_bullet_tree($tree);
    
        return "Tu es un rédacteur professionnel en style {$this->style}.\n\n" .
            "Voici une structure hiérarchique d'articles avec leurs slugs :\n\n{$structure}\n\n" .
            "Ta mission : rédiger pour chaque titre un article optimisé, en suivant STRICTEMENT ce format :\n\n" .
            "[TITRE: Le titre exact ici]\n" .
            "INTRO: Introduction générale du sujet.\n" .
            "CLICK_BAIT: Une phrase incitative qui donne envie de lire l'article (visible chez le parent).\n" .
            "DEVELOPMENTS:\n" .
            "- Titre 1: Texte du développement 1\n" .
            "- Titre 2: Texte du développement 2\n" .
            "...\n" .
            "CONCLUSION: Conclusion synthétique de l’article.\n" .
            "[IMAGE: description courte de l’image à générer sur Freepik]\n" .
            "[SLUG: le slug EXACT donné ci-dessus — NE LE MODIFIE JAMAIS]\n\n" .
            "⚠️ Respecte strictement le format pour CHAQUE titre, et copie exactement le slug affiché dans la structure.\n";
    }
     

    private function getPromptContent($title, $context) {
        return "Tu es un rédacteur web SEO.
    
        Rédige un article optimisé pour le sujet : \"$title\".

        Contexte :
        Cet article s’inscrit dans un cocon sémantique avec la hiérarchie suivante :
        $context

        Consignes :
        - Le contenu doit être du texte brut (pas de balises HTML ou Markdown).
        - Structure : introduction, paragraphes avec sous-titres, conclusion.
        - N’ajoute ni commentaires, ni balises.
        - Sois clair, informatif, naturel et professionnel.
        - N’inclus jamais le titre dans l’introduction.

        À la fin de l’article, propose une description courte d’image (10 mots max) adaptée à ce sujet, que l’on pourra rechercher sur Freepik. Format :
        [IMAGE: ...]";
    }

    public function __construct($api_key = null, $freepik_api_key = null) {
        $this->api_key = $api_key ?: get_option('csb_openai_api_key');
        $this->freepik_api_key = $freepik_api_key ?: get_option('csb_freepik_api_key');
        $this->model = get_option('csb_model', 'gpt-3.5-turbo');
        $this->temperature = floatval(get_option('csb_temperature', 0.7));
        $this->style = get_option('csb_writing_style', 'SEO');
    }


    private function normalize_title($title) {
        return strtolower(trim(preg_replace('/\s+/', ' ', strip_tags($title))));
    }
    


    public function generate_structure($keyword, $depth = 1) {
         $prompt = $this->getPromptArticle($keyword, $depth); 
         $raw = $this->call_api($prompt);
         return $this->clean_generated_structure($raw);
    }

    public function generate_structure_array($keyword, $depth = 1) {
        $markdown = $this->generate_structure($keyword, $depth);
        $tree = $this->parse_markdown_structure($markdown);
        $this->assign_slugs_recursively($tree);
        return $this->tree_to_slug_map($tree);
    }
    

    /**Utilise uniquement du texte brut sans mise en forme Markdown
     * Envoie une requête à l'API ChatGPT avec le prompt donne
     */
    private function call_api($prompt) {
        if (!$this->api_key) return '❌ Clé API non configurée.';

        $url = 'https://api.openai.com/v1/chat/completions';

        $data = [
            'model' => $this->model,
            'messages' => [
                ['role' => 'system', 'content' => "Tu es un rédacteur {$this->style}."],
                ['role' => 'user', 'content' => $prompt],
            ],
            'temperature' => $this->temperature,
        ];

        $args = [
            'headers' => [
                'Content-Type'  => 'application/json',
                'Authorization' => 'Bearer ' . $this->api_key,
            ],
            'body' => json_encode($data),
            'timeout' => 60,
        ];

        $response = wp_remote_post($url, $args);

        if (is_wp_error($response)) {
            return '❌ Erreur API : ' . $response->get_error_message();
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);

        // 🧪 Debug : afficher toute la réponse dans les logs si besoin
        // error_log(print_r($body, true));

        if (!isset($body['choices'][0]['message']['content'])) {
            return '❌ Erreur : réponse OpenAI invalide ou vide.';
        }

        return $body['choices'][0]['message']['content'];

    }

    private function parse_markdown_structure($text) {
        $lines = explode("\n", trim($text));
        $stack = [];
        $root = [];
    
        foreach ($lines as $line) {
            if (trim($line) === '') continue;
    
            // Match le titre avec indentation (espaces) et tiret
            if (preg_match('/^(\s*)-\s*(.+)$/', $line, $matches)) {
                $indent = strlen($matches[1]); // nombre d'espaces
                $title = trim($matches[2]);
    
                $level = intval($indent / 4); // 4 espaces = 1 niveau
    
                $node = ['title' => $title, 'children' => []];
    
                if ($level === 0) {
                    $root[] = $node;
                    $stack = [&$root[array_key_last($root)]];
                } else {
                    // Trouve le bon parent selon le niveau
                    $parent = &$stack[$level - 1]['children'];
                    $parent[] = $node;
                    $stack[$level] = &$parent[array_key_last($parent)];
                }
            }
        }
    
        return $root;
    }

    private function tree_to_slug_map(array $tree) {
        $map = [];
    
        foreach ($tree as $node) {
            $slug = $node['slug'];
            $entry = [
                'title' => $node['title'],
            ];
    
            if (!empty($node['children'])) {
                $entry['children'] = $this->tree_to_slug_map($node['children']);
            }
    
            $map[$slug] = $entry;
        }
    
        return $map;
    }

    private function clean_generated_structure($text) {
        return preg_replace('/^```.*$\n?|```$/m', '', $text);
    }
    

    public function generate_full_content(array &$tree) {
        print_r("\nTableau vide\n");
        print("<br>");
        print_r($tree);
        $prompt = $this->build_content_prompt($tree);
        $raw = $this->call_api($prompt);
        // print("<br>");print("<br>");print("<br>");
        // print_r($raw);
        // print("<br>");print("<br>");print("<br>");
        $parsed = $this->parse_content_blocks($raw);
        // print("<br>");
        // print("<br>");
        // print("<br>");
        // print_r("\n\n\nElement à ajouter\n");
        // print("<br>");
        // print_r($parsed);

        //print("<br>");
        foreach ($tree as $slug => &$node) {
            $this->fill_tree_node($slug, $node, $parsed);
        }
        
    }
    
    

    private function generate_article_content($title, $context) {
        $prompt = $this->getPromptContent($title, $context);
        $result = $this->call_api($prompt);

        // Utiliser la fonction extract_image_tag()
        $image_description = $this->extract_image_tag($result); // Modifie $result pour enlever le tag
        //echo "<p>$image_description</p>";

        // Récupération de l'URL de l'image depuis Freepik
        $image_url = $this->get_freepik_image($image_description);

        // Intégration de l'image dans le contenu
        if (!str_starts_with($image_url, '❌')) {
            $result .= "\n\n<img src=\"" . esc_url($image_url) . "\" alt=\"" . esc_attr($image_description) . "\" style=\"max-width:100%; height:auto;\" />";
        } else {
            $result .= "\n\n<!-- Aucune image trouvée sur Freepik -->";
        }

        return [
            'content' => trim($result),
            'image' => $image_description
        ];
    }
    
    private function extract_image_tag(&$text) {
        $image = null;
        if (preg_match('/\[IMAGE:\s*(.+?)\]/', $text, $matches)) {
            $image = trim($matches[1]);
            $text = str_replace($matches[0], '', $text);
        }
        return $image;
    }
    private function get_freepik_link($description) {
        $query = urlencode($description);
        return "https://www.freepik.com/search?format=search&query=$query";
    } 
    
    public function get_freepik_image($keywords)
    {
        //echo "andy";
        // 1. Appel de la fonction pour obtenir les mots-clés via OpenAI
        //$keywords = $this->openaiAutocompleteFreepik($contentId);
        // echo '</br>';
        // print_r($keywords);
        // echo '</br>';
        // 2. Vérification des mots-clés générés
        if (empty($keywords)) {
            throw new Exception("Aucun mot-clé généré.");
        }
        // 3. Préparation de la requête API Freepik
        $curl = curl_init();
        curl_setopt_array($curl, [
        CURLOPT_URL => "https://api.freepik.com/v1/resources?filters%5Bvector%5D%5Btype%5D=jpg&term=" . urlencode($keywords) . "&limit=1&page=1&filters%5Borientation%5D%5Blandscape%5D=1&filters%5Bpsd%5D%5Btype%5D=jpg&filters%5Bai-generated%5D%5Bexcluded%5D=1&filters%5Bcontent_type%5D%5Bphoto%5D=1&filters%5Bcontent_type%5D%5Bpsd%5D=1&order=relevance",
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => "",
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => "GET",
        CURLOPT_HTTPHEADER => [
            "Accept-Language: fr-FR",
            "x-freepik-api-key: FPSXbef134979a9a48aeb5afacdb9793d74b"
        ],
        ]);
        // 4. Exécution de la requête API
        $response = curl_exec($curl);
        //print_r($response);
        $err = curl_error($curl);
        curl_close($curl);
        if ($err) {
            throw new Exception("Erreur cURL : " . $err);
        }
        // 5. Analyse de la réponse JSON pour obtenir l'URL de l'image
        $data = json_decode($response, true);
        if (isset($data['data'][0]['image']['source']['url'])) {
        //print_r($data['data'][0]['image']['source']['url']);
        return  $data['data'][0]['image']['source']['url'];
        } else {
        throw new Exception("Aucune image trouvée.");
        }
    }

    private function parse_content_blocks($text) {
        $result = [];
        print_r($text);
    
        // Découper chaque bloc d'article complet
        preg_match_all('/\[TITRE:\s*(.*?)\]\s*INTRO:\s*(.*?)\s*CLICK_BAIT:\s*(.*?)\s*DEVELOPMENTS:\s*((?:-.*?:.*?\n?)+?)CONCLUSION:\s*(.*?)\s*\[IMAGE:\s*(.*?)\]\s*\[SLUG:\s*(.*?)\]/s', $text, $matches, PREG_SET_ORDER);
    
        foreach ($matches as $m) {
            $title = trim($m[1], " \t\n\r\0\x0B\"");
            $intro = trim($m[2]);
            $click_bait = trim($m[3]);
            $dev_block = trim($m[4]);
            $conclusion = trim($m[5]);
            $image = trim($m[6]);
    
            // 🔧 Slug normalisé avec sanitize_title
            $slug = CSB_Generator::generate_slug(trim($m[7]));
    
            // Extraction des sous-points de développement
            $developments = [];
            preg_match_all('/-\s*(.*?):\s*(.*?)(?=(?:-\s.*?:|$))/s', $dev_block, $dev_matches, PREG_SET_ORDER);
            foreach ($dev_matches as $dev) {
                $developments[] = [
                    'title' => trim($dev[1]),
                    'text' => trim($dev[2])
                ];
            }
    
            $result[$slug] = [
                'content' => [
                    'intro' => $intro,
                    'developments' => $developments,
                    'conclusion' => $conclusion,
                    'image' => $image
                ],
                'click_bait' => $click_bait,
                'slug' => $slug,
                'title' => $title
            ];
        }
    
        return $result;
    }
    
    private function fill_tree_node(string $slug, array &$node, array $parsed) {
        if (isset($parsed[$slug])) {
            $data = $parsed[$slug];
            
            try {
                $image_url = $this->get_freepik_image($data['content']['image']);
            } catch (Exception $e) {
                $image_url = '❌ Image non trouvée';
            }
    
            $node['content'] = [
                'intro' => $data['content']['intro'],
                'developments' => $data['content']['developments'],
                'conclusion' => $data['content']['conclusion'],
                'image' => $data['content']['image'],
                'image_url' => $image_url
            ];
            $node['click_bait'] = $data['click_bait'];
            
        }else{/*echo "//////////////////////////////////////////////////////////";*/}
    
        if (!empty($node['children'])) {
            foreach ($node['children'] as $child_slug => &$child) {
                $this->fill_tree_node($child_slug, $child, $parsed);
            }
        }
    }
    
    
    private function assign_slugs_recursively(&$tree) {
        foreach ($tree as &$node) {
            $node['slug'] = self::generate_slug($node['title']); // ou sanitize_title()
            if (!empty($node['children'])) {
                $this->assign_slugs_recursively($node['children']);
            }
        }
    }

    public static function generate_slug($title){
        $slug = sanitize_title($title);
        return $slug;
    }
        
    
    private function to_bullet_tree(array $tree, $indent = 0) {
        $out = '';
        foreach ($tree as $slug => $node) {
            $out .= str_repeat('    ', $indent) . "- {$node['title']} [SLUG: {$slug}]\n";
            if (!empty($node['children'])) {
                $out .= $this->to_bullet_tree($node['children'], $indent + 1);
            }
        }
        return $out;
    }
    
    

}
