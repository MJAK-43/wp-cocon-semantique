<?php
if (!defined('ABSPATH')) exit;

class CSB_Generator {
    private $freepik_api_key;
    private $api_key;
    private $model;
    private $temperature;
    private $style;
    private $image_description;

    private function getPromptStructure($keyword, $depth) {
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
        "📝 Chaque article doit faire environ **800 à 1000 mots** (1 page de texte).\n" .
        "Évite les répétitions et développe les idées avec des exemples concrets et pertinents.\n\n" .
        "[TITRE: Le titre exact ici]\n" .
        "INTRO: Introduction générale du sujet.\n" .
        "CLICK_BAIT: Une phrase incitative qui donne envie de lire l'article (visible chez le parent).\n" .
        "DEVELOPMENTS:\n" .
        "- Chaque ligne commence par un vrai sous-titre suivi de : le texte associé (ex : Le Labrador Retriever : un chien idéal pour la famille)\n" .
        "CONCLUSION: Conclusion synthétique de l’article.\n" .
        "[IMAGE: description courte de l’image à générer sur Freepik]\n" .
        "[SLUG: le slug EXACT donné ci-dessus — NE LE MODIFIE JAMAIS]\n\n" .
        "⚠️ Très important :
        - Ne mets **aucun emoji** ou mise en forme (gras, italique, astérisques).
        - Commence chaque bloc exactement par [TITRE: ...], sans ajout.
        - Aucun saut de ligne inutile ou bloc superflu.
        - ❌ N’utilise pas les titres génériques comme 'Titre 1', 'Titre 2'. Utilise un **vrai sous-titre parlant**.";
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
         $prompt = $this->getPromptStructure($keyword, $depth); 
         $raw = $this->call_api($prompt);
         return $this->clean_generated_structure($raw);
    }

    public function generate_structure_array($keyword, $depth = 1) {
        $markdown = $this->generate_structure($keyword, $depth);
        $tree = $this->parse_markdown_structure($markdown);
        //var_dump($tree);
        $this->assign_slugs_recursively($tree);
        //var_dump($tree);
        return $this->tree_to_slug_map($tree);

    }
    private function parse_markdown_structure($text) {
        $lines = explode("\n", trim($text));
        $stack = [];
        $root = [];
    
        foreach ($lines as $line) {
            if (trim($line) === '' || preg_match('/^```/', trim($line))) {
                continue;
            }
    
            // Match indentation + tiret + titre
            if (preg_match('/^(\s*)-\s*(.+)$/', $line, $matches)) {
                $indent = strlen($matches[1]);
                $title = trim($matches[2]);
                $level = intval($indent / 4); // chaque niveau = 4 espaces
    
                $node = ['title' => $title, 'children' => []];
    
                if ($level === 0) {
                    $root[] = $node;
                    $stack = [&$root[array_key_last($root)]];
                } else {
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


    private function assign_slugs_recursively(&$tree) {
        foreach ($tree as &$node) {
            $node['slug'] = self::generate_slug($node['title']); // ou sanitize_title()
            if (!empty($node['children'])) {
                $this->assign_slugs_recursively($node['children']);
            }
        }
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

    private function getPromptArticle($title, $contextTree) {
        $structure = $this->to_bullet_tree($contextTree);
    
        return "Tu es un rédacteur professionnel en style {$this->style}.\n\n" .
            "Contexte : voici la structure hiérarchique dans laquelle s’insère l’article \"$title\". Chaque ligne représente un titre d’article :\n\n" .
            $structure . "\n\n" .
            "Ta mission : rédiger un article optimisé pour le sujet \"$title\".\n\n" .
            "📝 L'article doit faire environ **400 à 600 mots** (1 page de texte).\n" .
            "Évite les répétitions et développe les idées avec des exemples concrets et pertinents.\n\n" .
            "Respecte ce format STRICTEMENT (ne rien ajouter ni modifier) :\n\n" .
            "[TITRE: $title]\n" .
            "INTRO: Introduction générale du sujet.\n" .
            "CLICK_BAIT: Une phrase incitative qui donne envie de lire l'article (visible chez le parent).\n" .
            "DEVELOPMENTS:\n" .
            "- Titre 1: Texte du développement 1\n" .
            "- Titre 2: Texte du développement 2\n" .
            "...\n" .
            "CONCLUSION: Conclusion synthétique de l’article.\n" .
            "[IMAGE: description courte de l’image à générer sur Freepik]\n\n" .
            "⚠️ Très important :\n" .
            "- Ne mets **aucun emoji** ou mise en forme (gras, italique, astérisques).\n" .
            "- Ne modifie jamais le format ni l'ordre des blocs.\n" .
            "- Aucun saut de ligne inutile ou bloc superflu.";
    }

    private function parse_content_blocks($text) {
        // Correspond à un seul article au format strict
        if (preg_match('/\[TITRE:\s*(.*?)\]\s*INTRO:\s*(.*?)\s*CLICK_BAIT:\s*(.*?)\s*DEVELOPMENTS:\s*((?:-.*?:.*?\n?)+?)CONCLUSION:\s*(.*?)\s*\[IMAGE:\s*(.*?)\]/s', $text, $m)) {
            $title = trim($m[1], " \t\n\r\0\x0B\"");
            $intro = trim($m[2]);
            $click_bait = trim($m[3]);
            $dev_block = trim($m[4]);
            $conclusion = trim($m[5]);
            $image = trim($m[6]);
    
            $slug = self::generate_slug($title);
    
            // Extraire les développements
            $developments = [];
            preg_match_all('/-\s*(.*?):\s*(.*?)(?=(?:-\s.*?:|$))/s', $dev_block, $dev_matches, PREG_SET_ORDER);
            foreach ($dev_matches as $dev) {
                $developments[] = [
                    'title' => trim($dev[1]),
                    'text' => trim($dev[2])
                ];
            }
    
            return [
                $slug => [
                    'content' => [
                        'intro' => $intro,
                        'developments' => $developments,
                        'conclusion' => $conclusion,
                        'image' => $image
                    ],
                    'click_bait' => $click_bait,
                    'slug' => $slug,
                    'title' => $title
                ]
            ];
        }
        // echo '<br>';echo '<br>';
        // print_r($text);
        // echo '<br>';echo '<br>';
        return []; // rien trouvé
    }
    

    
    private function clean_generated_structure($text) {
        return preg_replace('/^```.*$\n?|```$/m', '', $text);
    }
    

    public function generate_full_content(array &$tree) {
        // echo "<br>";echo "<br>";
        // print_r($tree);
        // echo "<br>";echo "<br>";
        foreach ($tree as $slug => &$node) {
            $this->generate_content_for_node($slug, $node, $tree);
        }
        // echo "<br>";echo "<br>";
        // print_r($tree);
        // echo "<br>";echo "<br>";
    }

    private function extract_subtree_context($slug, $tree) {
        foreach ($tree as $key => $node) {
            if ($key === $slug) return [$key => $node];
    
            if (!empty($node['children'])) {
                $found = $this->extract_subtree_context($slug, $node['children']);
                if ($found !== null) {
                    return [$key => array_merge($node, ['children' => $found])];
                }
            }
        }
        return null;
    }

    private function generate_content_for_node(string $slug, array &$node, array $fullTree) {
        $context_tree = $this->extract_subtree_context($slug, $fullTree);
        $prompt = $this->getPromptArticle($node['title'], $context_tree);
        $raw = $this->call_api($prompt);
        // echo "<br>";echo "<br>";
        // print_r($raw);
        // echo "<br>";echo "<br>";
        $parsed = $this->parse_content_blocks($raw);
        
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
        }
    
        // Appel récursif sur les enfants
        if (!empty($node['children'])) {
            foreach ($node['children'] as $child_slug => &$child) {
                $this->generate_content_for_node($child_slug, $child, $fullTree);
            }
        }
    }
    
    
    private function get_freepik_link($description) {
        $query = urlencode($description);
        return "https://www.freepik.com/search?format=search&query=$query";
    } 
    
    public function get_freepik_image($keywords){
        
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
