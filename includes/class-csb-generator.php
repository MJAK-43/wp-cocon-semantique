<?php
if (!defined('ABSPATH')) exit;

class CSB_Generator {
    private $freepik_api_key;
    private $api_key;
    private $model;
    private $temperature;
    private $style;
    //private $image_description;
    private PromptProviderInterface $promptProvider;
    private $tokens_used = 0;


    private static function getDefaultIntro(string $title): string {
        return "<p><em>Introduction par défaut sur «&nbsp;$title&nbsp;».</em></p>";
    }

    private static function getDefaultDevelopment(string $title): string {
        return "<p><em>Développement par défaut pour «&nbsp;$title&nbsp;».</em></p>";
    }

    private static function getDefaultConclusion(string $title): string {
        return "<p><em>Conclusion par défaut sur «&nbsp;$title&nbsp;».</em></p>";
    }

    private static function getDefaultLeafParts(string $title): string {
        return "- Partie 1 de « $title »\n- Partie 2 de « $title »\n- Partie 3 de « $title »";
    }

    private static function generateDefaultStructure(string $keyword = 'Thème Principal'): string {
        return "- " . ucwords($keyword) . "\n"
            . "    - Sous-thème A\n"
            . "        - Exemple A1\n"
            . "        - Exemple A2\n"
            . "    - Sous-thème B\n"
            . "        - Exemple B1\n"
            . "        - Exemple B2\n";
    }


    public function get_tokens_used() {
        return $this->tokens_used;
    }


    public function __construct(PromptProviderInterface $promptProvider, $api_key = null, $freepik_api_key = null) {
        $this->promptProvider = $promptProvider;
        $this->api_key = $api_key ?: get_option('csb_openai_api_key');
        $this->freepik_api_key = $freepik_api_key ?: get_option('csb_freepik_api_key');
        $this->model = get_option('csb_model', 'gpt-3.5-turbo');
        $this->temperature = floatval(get_option('csb_temperature', 0.7));
        $this->style = get_option('csb_writing_style', 'SEO');
    }
    

    public function generateStructure($keyword, $depth = 1, bool $test = false) {
        if ($test) 
            return self::generateDefaultStructure($keyword);
        
        $prompt = $this->promptProvider->structure($keyword, $depth);
        $raw = $this->callApi($prompt);
        return $this->clean_generated_structure($raw);
    }


    

    

    private function normalizeKeyword($title) {
        // Convertir les accents
        $translit = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $title);
        // Nettoyer les caractères non alphanumériques sauf espace
        $clean = preg_replace('/[^a-zA-Z0-9 ]/', '', $translit);
        // Retourner le résultat en minuscule
        return strtolower(trim($clean));
    }

    


    /**Utilise uniquement du texte brut sans mise en forme Markdown
     * Envoie une requête à l'API ChatGPT avec le prompt donne
     */
    private function callApi($prompt) {
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
        // ➔ Stocke les tokens utilisés si possible
        if (isset($body['usage']['total_tokens'])) {
            $this->tokens_used += (int)$body['usage']['total_tokens'];
        }

        return $body['choices'][0]['message']['content'];

    }

    
    private function clean_generated_structure($text) {
        return preg_replace('/^```.*$\n?|```$/m', '', $text);
    }

    private function to_bullet_tree(array $map, int $current_id = null, int $indent = 0): string {
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

    public function generateImage(string $title, string $keyword,bool $test = false): string {
        $default_image_url = plugin_dir_url(__FILE__) . '../image_test.png';
        if($test)
            return $default_image_url;
        try {
            $prompt_image = $this->promptProvider->image($keyword, $title);
            $text_image_description = $this->callApi($prompt_image);
            $image_url = $this->fetch_image_from_api($title, $text_image_description);
            if (!str_starts_with($image_url, '❌')) {
                return $image_url;
            } else {
                throw new Exception("URL image invalide.");
            }
        } catch (Exception $e) {
            // Fallback : URL vers l’image locale par défaut
            error_log("Erreur lors de la récupération de l'image Freepik : " . $e->getMessage());
        }
        return $default_image_url;
    
    }

    public function generateIntro(string $title, string $structure, string $slug, bool $test): string {
        $prompt = $this->promptProvider->intro($title, $structure);
        $default = self::getDefaultIntro($title);

        return $this->generate($title, $structure, $test, $default, $prompt);
    }


    public function generateDevelopment(string $title, string $structure, bool $test): string {
        $prompt = $this->promptProvider->development($title, $structure);
        $default = self::getDefaultDevelopment($title);

        return $this->generate($title, $structure, $test, $default, $prompt);
    }

    public function generateConclusion(string $title, string $structure, string $slug, bool $test): string {
        $prompt = $this->promptProvider->conclusion($title, $structure);
        $default = self::getDefaultConclusion($title);

        return $this->generate($title, $structure, $test, $default, $prompt);
    }


    public function generate(string $title, string $structure, bool $test, string $defaultContent, string $prompt): string {
        $content = '';
        if ($test) 
            $content = $defaultContent;

        else 
            $content = $this->callApi($prompt);
        

        return $content;
    }

    public function generateContent(int $post_id, array $map, int $number,bool $test = false): string {
        $node = $map[$post_id];
        $title = $node['title'];
        $slug = get_post_field('post_name', $post_id); 
        $structure = $this->to_bullet_tree($map);

        // Intro
        $prompt_intro = $this->promptProvider->intro($title, $structure);
        $intro ="";
        if(!$test)
            $intro =$this->callApi($prompt_intro);

        $intro = "<div id='csb-intro-$slug' class='csb-content csb-intro'>$intro</div>";

        // Développements
        $developments_html = '';
        if (!empty($node['children_ids'])) {
            foreach ($node['children_ids'] as $child_id) {
                if (isset($map[$child_id])) {
                    $child = $map[$child_id];

                    $devContent = $this->generateDevelopment($child['title'], $structure, $test);
                    $child_slug = $this->slugify($child['title']);

                    $devBlock = "<div id='csb-development-$child_slug' class='csb-content csb-development'>$devContent</div>";
                    $child_link = '<p>Pour en savoir plus, découvrez notre article sur <a href="' . esc_url($child['link']) . '">' . esc_html($child['title']) . '</a>.</p>';

                    $developments_html .= $devBlock . $child_link;
                }
            }
                
        } 
        else {
            // 1. Générer les titres des parties
            $prompt_leaf_parts = $this->promptProvider->leafParts($title, $structure, $number);
            $leaf_parts_raw = $test ? '' : $this->callApi($prompt_leaf_parts);

            // 2. Nettoyer et parser la liste
            $lines = explode("\n", trim($leaf_parts_raw));
            foreach ($lines as $line) {
                if (preg_match('/^\s*-\s*(.+)$/', $line, $matches)) {
                    $leaf_title = trim($matches[1]);

                    // 3. Générer le contenu pour chaque partie
                    $prompt_dev = $this->promptProvider->development($leaf_title, $structure);
                    $dev_content = $test ? '' : $this->callApi($prompt_dev);

                    $leaf_slug = $this->slugify($leaf_title);

                    // 4. Rendu HTML
                    $developments_html .= "<div id='csb-leaf-$leaf_slug' class='csb-content csb-development'>$dev_content</div>";
                }
            }
        }
        // Conclusion
        $prompt_conclusion = $this->promptProvider->conclusion($title, $structure);
        $conclusion = "";
        if(!$test)
            $conclusion = $this->callApi($prompt_conclusion);
        $conclusion = "<div id='csb-conclusion-$slug' class='csb-content csb-conclusion'>$conclusion</div>";

        return $intro . $developments_html . $conclusion;
    }

    private function slugify(string $text): string {
        // 1. Translittération : convertit les accents et caractères spéciaux
        $text = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text);

        // 2. Mise en minuscule
        $text = strtolower($text);

        // 3. Remplace les caractères non alphanumériques par des tirets
        $text = preg_replace('/[^a-z0-9]+/', '-', $text);

        // 4. Supprime les tirets en début ou fin
        return trim($text, '-');
    }


    

    
    /***
     * 
     * Récupération Image
     */
   


    private function fetch_image_from_api(string $title, string $text): ?string {
        // 🔥 Normalisation du titre et du texte
        $normalized_title = $this->normalizeKeyword($title);
        $normalized_text = $this->normalizeKeyword($text);
        // echo "<br>";echo "<br>";
        // print_r($normalized_title);
        // echo "<br>";
        // print_r($normalized_text);
        // echo "<br>";echo "<br>";
        // Construction de l'URL
        $api_url = 'https://app.posteria.fr/crons/freepikImageCoconSemantique/' . rawurlencode($normalized_title) . '/' . rawurlencode($normalized_text);
    
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $api_url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    
        $response = curl_exec($ch);
        $err = curl_error($ch);
        curl_close($ch);
    
        if ($err) {
            error_log("Erreur lors de l'appel API d'image : " . $err);
            return null;
        }
    
        return trim($response); // Toujours trim au cas où il y a des espaces
    }
    
        

    
    
    


    
    
    
    

}
