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
        $this->assign_slugs_recursively($tree);
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

    
    private function is_valid_format(string $raw): bool {
        $required_blocks = [
            '/\[TITRE:\s*.+?\]/',
            '/INTRO:\s+.+/',
            '/CLICK_BAIT:\s+.+/',
            '/DEVELOPMENTS:\s+-\s*title:\s*.+\s+text:\s*.+\s+link:\s*/',
            '/CONCLUSION:\s+.+/',
            '/\[IMAGE:\s*.+?\]/',
            '/\[SLUG:\s*.+?\]/',
        ];

        foreach ($required_blocks as $pattern) {
            if (!preg_match($pattern, $raw)) {
                return false;
            }
        }

        return true;
    }
       
        
    

    
    private function parse_content_blocks($text) {
        // Extraire les blocs principaux avec des regex simples
        preg_match('/\[TITRE:\s*(.*?)\]/s', $text, $titleMatch);
        preg_match('/INTRO:\s*(.*?)\s*CLICK_BAIT:/s', $text, $introMatch);
        preg_match('/CLICK_BAIT:\s*(.*?)\s*DEVELOPMENTS:/s', $text, $clickBaitMatch);
        preg_match('/DEVELOPMENTS:\s*(.*?)\s*CONCLUSION:/s', $text, $devBlockMatch);
        preg_match('/CONCLUSION:\s*(.*?)\s*\[IMAGE:/s', $text, $conclusionMatch);
        preg_match('/\[IMAGE:\s*(.*?)\]/s', $text, $imageMatch);
        preg_match('/\[SLUG:\s*(.*?)\]/s', $text, $slugMatch);
    
        if (!$titleMatch || !$introMatch || !$clickBaitMatch || !$devBlockMatch || !$conclusionMatch || !$imageMatch || !$slugMatch) {
            return [];
        }
    
        $title = trim($titleMatch[1]);
        $intro = trim($introMatch[1]);
        $click_bait = trim($clickBaitMatch[1]);
        $dev_block = trim($devBlockMatch[1]);
        $conclusion = trim($conclusionMatch[1]);
        $image = trim($imageMatch[1]);
        $slug = trim($slugMatch[1]);
    
        // Parse chaque développement
        preg_match_all('/-\s*title:\s*(.*?)\s+text:\s*(.*?)\s+link:\s*(.*)/s', $dev_block, $matches, PREG_SET_ORDER);
        $developments = [];
        foreach ($matches as $match) {
            $developments[] = [
                'title' => trim($match[1]),
                'text' => trim($match[2]),
                'link' => ''  // toujours vide ici
            ];
        }
    
        return [
            self::generate_slug($title) => [
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

    private function getPromptArticle($title, $contextTree, $number, $slug) {
        $structure = $this->to_bullet_tree($contextTree);
    
        // 🧠 Génération des liens HTML à partir du vrai fullTree
        $children_links = [];
        if (isset($contextTree[$slug]['children'])) {
            foreach ($contextTree[$slug]['children'] as $child_slug => $child_node) {
                if (!empty($child_node['title']) && !empty($child_node['link']) && !empty($child_node['click_bait'])) {
                    $children_links[] = [
                        'title' => $child_node['title'],
                        //'link' => '<a href="' . esc_url($child_node['link']) . '">' . esc_html($child_node['click_bait']) . '</a>',
                    ];
                }
            }
        }


        // 🔧 Partie DEVELOPMENT avec ou sans enfants
        $dev_part = '';

        if (!empty($children_links)) {
            $dev_part .= "Dans la section DEVELOPMENTS, crée une entrée pour chaque enfant direct de cet article.\n";
            $dev_part .= "Chaque développement doit suivre ce format :\n";
            $dev_part .= "- title: Le titre exact de l’enfant\n  text: Le texte du développement\n  link: (laisse vide, écris juste `link:`)\n\n";
            $dev_part .= "Voici les titres à utiliser :\n";
            foreach ($children_links as $child) {
                $dev_part .= "- title: {$child['title']}\n";
            }
            $dev_part .= "\n⚠️ Ne mets pas de lien HTML. Juste `link:` vide.\n";
        } else {
            $dev_part .= "Il n’y a **aucun enfant** dans cet article. Tu dois donc créer **exactement $number sous-parties**.\n";
            $dev_part .= "Chaque sous-partie doit suivre ce format :\n";
            $dev_part .= "- title: Un sous-titre pertinent\n  text: Le développement\n  link:\n\n";
            $dev_part .= "⚠️ Ne dépasse pas $number sous-parties. Ne mets **aucun lien HTML**.\n";
        }

    
        // 📝 Prompt complet
        return "Tu es un rédacteur professionnel en style {$this->style}.\n\n" .
            "Contexte : voici la structure hiérarchique dans laquelle s’insère l’article \"$title\". Chaque ligne représente un titre d’article :\n\n" .
            "$structure\n\n" .
            "Ta mission : rédiger un article optimisé pour le sujet \"$title\" (environ 800 mots).\n\n" .
            "Évite les répétitions et développe les idées avec des exemples concrets et pertinents.\n\n" .
            "Respecte ce format STRICTEMENT :\n\n" .
            "[TITRE: $title]\n" .
            "INTRO: Introduction générale du sujet.\n" .
            "CLICK_BAIT: Une phrase incitative qui donne envie de lire l'article (visible chez le parent).\n" .
            "DEVELOPMENTS:\n" .
            "$dev_part" .
            "CONCLUSION: Conclusion synthétique de l’article.\n" .
            "[IMAGE: description courte de l’image à générer sur Freepik]\n" .
            "[SLUG: le slug EXACT donné ci-dessus — NE LE MODIFIE JAMAIS]\n\n" .
            "⚠️ Très important :\n" .
            "- Ne mets aucun emoji ou mise en forme.\n" .
            "- Ne change pas les titres fournis.\n" .
            "- Chaque développement doit inclure les champs : title, text";
    }
    
    
    

    private function getPromptArticleValidation($title, $contextTree, $raw): string {
        $structure = $this->to_bullet_tree($contextTree);
    
        return "Tu es un expert en rédaction SEO.\n\n" .
            "Voici la structure hiérarchique du cocon sémantique (contexte global) :\n" .
            "{$structure}\n\n" .
            "Le titre à traiter est : \"{$title}\"\n\n" .
            "Voici le texte généré à valider :\n" .
            "{$raw}\n\n" .
            "Ta mission :\n" .
            "- Vérifie que ce texte respecte strictement le format suivant :\n" .
            "[TITRE: ...]\nINTRO: ...\nCLICK_BAIT: ...\nDEVELOPMENTS:\n- title: ...\n  text: ...\n  link: <a href='...'>...</a>\n...\nCONCLUSION: ...\n[IMAGE: ...]\n[SLUG: ...]\n\n" .
            "- Chaque bloc doit être présent et bien structuré.\n" .
            "- Le lien doit être un lien HTML complet (balise <a>) donné dans le prompt initial.\n" .
            "- Ne change pas le contenu des liens ni le format des balises.\n\n" .
            "Corrige uniquement ce qui est nécessaire pour que le texte soit correctement parsé automatiquement.";
    }
    
    

    private function clean_generated_structure($text) {
        return preg_replace('/^```.*$\n?|```$/m', '', $text);
    }


    public function generate_full_content(array &$tree, int $number, bool $use_fake = false) {
        foreach ($tree as $slug => &$node) {

            if ($use_fake) {
                // 🔹 Mode TEST (sans API)
                $fake_content = $this->generate_fake_content_for_slug($slug,$tree);
                if (isset($fake_content[$slug])) {
                    $data = $fake_content[$slug];
                    $node['content'] = $data['content'];
                    $node['click_bait'] = $data['click_bait'];
                }
    
            } else {
                // 🔸 Mode normal (API OpenAI)
                $this->generate_content_for_node($slug, $node, $tree, $number);
            }
    
            // Récursif sur les enfants
            if (!empty($node['children'])) {
                $this->generate_full_content($node['children'], $number, $use_fake);
            }
        }
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



    private function generate_content_for_node(string $slug, array &$node, array $fullTree, int $number) {
        $context_tree = $this->extract_subtree_context($slug, $fullTree);
        $prompt = $this->getPromptArticle($node['title'], $context_tree,$number,$slug);
        $raw = $this->call_api($prompt);
        $itiration =0;
        while(!$this->is_valid_format($raw)&&$itiration<2) {
            $itiration+=1;
            echo '<br>';
            echo '<br>';
            print_r($itiration);
            echo '<br>';
            echo '<br>';
            $validation_prompt = $this->getPromptArticleValidation($node['title'], $context_tree, $raw);
            $raw = $this->call_api($validation_prompt); // Correction via OpenAI
        }
        
        //$parsed = $this->parse_content_blocks($raw);

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
                $this->generate_content_for_node($child_slug, $child, $fullTree,$number);
            }
        }
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

    public function generate_fake_content_for_slug($slug, array $tree = []): array {
        // Recherche des enfants correspondants dans l'arbre
        $children = $tree[$slug]['children'] ?? [];
    
        $developments = [];
    
        // S'il y a des enfants : un développement par enfant
        if (!empty($children)) {
            foreach ($children as $child_slug => $child_node) {
                $developments[] = [
                    'title' => $child_node['title'],
                    'text'  => "Contenu détaillé sur le sujet \"" . $child_node['title'] . "\".",
                    'link'  => '' // le lien sera inséré plus tard par add_links_to_developments()
                ];
            }
        } else {
            // Sinon, on génère 3 développements fictifs
            for ($i = 1; $i <= 3; $i++) {
                $developments[] = [
                    'title' => "Aspect $i de $slug",
                    'text'  => "Explication détaillée de l'aspect $i...",
                    'link'  => ''
                ];
            }
        }
    
        return [
            $slug => [
                'click_bait' => "Découvrez tout sur $slug !",
                'slug'       => $slug,
                'title'      => ucfirst(str_replace('-', ' ', $slug)),
                'content'    => [
                    'intro'      => "Voici une introduction pour l'article $slug.",
                    'developments' => $developments,
                    'conclusion' => "Conclusion de l'article $slug.",
                    'image'      => "chat mignon",
                    'image_url'  => "https://placekitten.com/800/400"
                ]
            ]
        ];
    }
    
    
    

}
