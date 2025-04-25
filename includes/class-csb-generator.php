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
        return "Tu es un expert en SEO abtimiser pour le référencement. Génère une structure hiérarchique de cocon sémantique en texte brut.
        Consignes :
        - Utilise des tirets `-` pour chaque point.
        - Utilise **4 espaces** pour chaque niveau d’imbrication (indentation).
        - Le mot-clé principal est : \"$keyword\"
        - $depth sous-thèmes, chacun avec $depth sous-sous-thèmes.
        Pas de commentaires, pas de balises, juste le texte hiérarchique.";
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

    public function generate_structure_array($keyword, $depth = 1, bool $use_fake = false) {
        if($use_fake)
            return $this->generate_fake_structure_array();
        $markdown = $this->generate_structure($keyword, $depth);
        $tree = $this->parse_markdown_structure($markdown);
        //var_dump($tree);
        //$this->assign_slugs_recursively($tree);
        //var_dump($tree);
        return $this->tree_to_slug_map($tree);

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
            $slug = $this->generate_slug($node['title']);
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

    
    
    

    private function clean_generated_structure($text) {
        return preg_replace('/^```.*$\n?|```$/m', '', $text);
    }



    public function to_bullet_tree(array $map, int $current_id = null, int $indent = 0): string {
        $out = '';
    
        foreach ($map as $id => $node) {
            if ($node['parent_id'] === $current_id) {
                $out .= str_repeat('    ', $indent) . "- {$node['title']} [ID: {$id}]\n";
    
                if (!empty($node['children_ids'])) {
                    $out .= $this->to_bullet_tree($map, $id, $indent + 1);
                }
            }
        }
        //print_r($out);
        return $out;
    }
    
    
    
    private function getPromptIntro(string $title, array $contextTree): string {
        $structure = $this->to_bullet_tree($contextTree);
        return "Tu es un rédacteur SEO professionnel expert de WordPress.
    
        Tu dois écrire une **INTRODUCTION HTML** pour un article intitulé « $title ».
        
        Voici la structure complète du cocon sémantique auquel cet article appartient :
        
        $structure
        
        Consignes :
        - Ne commence pas par « Cet article va parler de… ».
        - Structure l’intro en 2 ou 3 paragraphes <p>, dans un <div class='csb-intro'>.
        - Utilise un ton engageant, accessible, et un vocabulaire fluide.
        - Pas de <h1>, <h2>, ni de résumé. Pas de liste.
        - N’utilise **jamais** de balise ```html ni aucun bloc de code Markdown
        
        Ta seule mission : captiver le lecteur pour qu’il ait envie de lire les développements.";
    }
    
    

    private function getPromptDevelopment(string $title, array $contextTree): string {
        $structure = $this->to_bullet_tree($contextTree);
        return "Tu es un expert en rédaction SEO sur WordPress.
    
        Tu dois écrire un bloc de DÉVELOPPEMENT HTML pour un article intitulé « $title ».
        
        Voici la structure du cocon sémantique global :
        
        $structure
        
        Consignes :
        - doit avoir un <h4>$title</h4> suivi de 1 ou 2 paragraphes <p>. 
        - Si c’est pertinent, tu peux utiliser des <ul><li> pour lister des conseils, caractéristiques, etc.
        - N’utilise **jamais** de balise ```html ni aucun bloc de code Markdown
        Structure le tout dans un <div class='csb-development'>.";
    }
    

/*
    private function getPromptDevelopment(string $title, array $contextTree): string {
        $structure = $this->to_bullet_tree($contextTree);
        return "Tu es un expert en rédaction SEO pour WordPress.
    
    Ta tâche : rédiger un bloc de DÉVELOPPEMENT HTML pour un article intitulé « $title ».
    
    Voici la structure globale du cocon sémantique :
    
    $structure
    
    Consignes de rédaction :
    - Commence par un titre HTML : <h4>$title</h4>
    - Rédige ensuite 1 ou 2 paragraphes (<p>...</p>) informatifs et engageants.
    - Si nécessaire, utilise une liste à puces avec <ul><li> pour structurer des points clés, astuces ou caractéristiques.
    - Structure l’ensemble dans un bloc <div class='csb-development'> pour faciliter la mise en page WordPress.
    - ⚠️ N’utilise **aucun** bloc de code Markdown (comme ```html).
    
    Garde un ton clair, naturel et informatif.";
    }
    
*/ 
    
    
    private function getPromptConclusion(string $title, array $contextTree): string {
        $structure = $this->to_bullet_tree($contextTree);
        return "Tu es un rédacteur SEO confirmé.
    
        Tu dois écrire une CONCLUSION HTML pour l’article intitulé « $title ».
        
        Structure du cocon sémantique complet :
        
        $structure
        
        Consignes :
        - Résume les points forts de l’article sans redites.
        - Termine sur un message encourageant ou une réflexion.
        - Utilise uniquement des balises HTML suivantes : <div class='csb-conclusion'>, <p>, <strong>.
        - Ne mets pas de liens ni d’ouverture vers d’autres sujets.
        - N’utilise **jamais** de balise ```html ni aucun bloc de code Markdown
        
        Écris de manière naturelle, engageante, et claire.";
    }
    
    

    public function generate_full_content(int $post_id, array $map, int $number): string {
        
        $node = $map[$post_id];
        $title = $node['title'];
        $structure = $this->to_bullet_tree($map);
    
        // Prompt et génération de l’intro
        $prompt_intro = $this->getPromptIntro($title, $map);
        //$intro = $this->call_api($prompt_intro);
    
        // Développements
        $developments_html = '';
        foreach ($node['children_ids'] ?? [] as $child_id) {
            if (!isset($map[$child_id])) continue;
            $child = $map[$child_id];
            $prompt_dev = $this->getPromptDevelopment($child['title'], $map);
            //print_r($child['title']);
            //$dev_content =$this->call_api($prompt_dev);
            $child_link = '<p>Pour en savoir plus, découvrez notre article sur <a href="' . esc_url($child['link'] ?? '#') . '">' . esc_html($child['title']) . '</a>.</p>';
    
            $developments_html .= $dev_content . $child_link;
        }
    
        // Prompt et génération de la conclusion
        $prompt_conclusion = $this->getPromptConclusion($title, $map);
        //$conclusion =$this->call_api($prompt_conclusion);
        // Récupération de l'URL de l'image depuis Freepik
        $image_url = $this->get_freepik_image($title);

        if (!str_starts_with($image_url, '❌')) {
            $image = "\n\n<img src=\"" . esc_url($image_url) . "\" alt=\"" . esc_attr($title) . "\" style=\"max-width:100%; height:auto;\" />";
        } else {
            // Utilise l'image locale par défaut
            $default_image_url = plugin_dir_url(__FILE__) . '../image_test.png';
            $image = "\n\n<img src=\"" . esc_url($default_image_url) . "\" alt=\"Image par défaut\" style=\"max-width:100%; height:auto;\" />";
        }
        

    
        // Concatène toutes les parties
        return /*$intro .$developments_html .*/ $conclusion.$image;
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



    
    
    /***
     * 
     * Récupération Image
     */
    
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
        
    private function generate_fake_structure_array() {
        return [
            'chat' => [
                'title' => 'Chat',
                'slug' => 'chat',
                'children' => [
                    'alimentation-chat' => [
                        'title' => 'Alimentation du chat',
                        'slug' => 'alimentation-chat',
                        'children' => [
                            'croquettes' => [
                                'title' => 'Croquettes pour chat',
                                'slug' => 'croquettes',
                                'children' => []
                            ],
                            'patee' => [
                                'title' => 'Pâtée pour chat',
                                'slug' => 'patee',
                                'children' => []
                            ],
                        ]
                    ],
                    'sante-chat' => [
                        'title' => 'Santé du chat',
                        'slug' => 'sante-chat',
                        'children' => [
                            'vaccins' => [
                                'title' => 'Vaccins pour chat',
                                'slug' => 'vaccins',
                                'children' => []
                            ],
                            'vermifuge' => [
                                'title' => 'Vermifuge pour chat',
                                'slug' => 'vermifuge',
                                'children' => []
                            ],
                        ]
                    ]
                ]
            ]
        ];
    }
    
    


    
    
    
    

}
