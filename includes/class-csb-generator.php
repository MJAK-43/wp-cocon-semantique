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
            return $this->generateStaticStructure($keyword);
        
        $prompt = $this->promptProvider->structure($keyword, $depth);
        $raw = $this->call_api($prompt);
        return $this->clean_generated_structure($raw);
    }
    

    

    private function normalize_keyword($title) {
        // Convertir les accents
        $translit = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $title);
        // Nettoyer les caractères non alphanumériques sauf espace
        $clean = preg_replace('/[^a-zA-Z0-9 ]/', '', $translit);
        // Retourner le résultat en minuscule
        return strtolower(trim($clean));
    }

    
private function generateStaticStructure(string $keyword = 'Thème Principal'): string {
    return "- " . ucwords($keyword) . "\n"
         . "    - Sous-thème A\n"
         . "        - Exemple A1\n"
         . "        - Exemple A2\n"
         . "    - Sous-thème B\n"
         . "        - Exemple B1\n"
         . "        - Exemple B2\n";
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
        // ➔ Stocke les tokens utilisés si possible
        if (isset($body['usage']['total_tokens'])) {
            $this->tokens_used += (int)$body['usage']['total_tokens'];
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

    public function generateImage(string $title, string $keyword): string {
    try {
        $prompt_image = $this->promptProvider->image($keyword, $title);
        $text_image_description = $this->call_api($prompt_image);
        $image_url = $this->fetch_image_from_api($title, $text_image_description);

        if (!str_starts_with($image_url, '❌')) {
            return $image_url;
        } else {
            throw new Exception("URL image invalide.");
        }
    } catch (Exception $e) {
        // Fallback : URL vers l’image locale par défaut
        $default_image_url = plugin_dir_url(__FILE__) . '../image_test.png';
        error_log("Erreur lors de la récupération de l'image Freepik : " . $e->getMessage());
        return $default_image_url;
    }
}

    public function generateContent(int $post_id, array $map, int $number): string {
        
        $node = $map[$post_id];
        $title = $node['title'];
        $structure = $this->to_bullet_tree($map);
    
        // Prompt et génération de l’intro
        $prompt_intro = $this->promptProvider->intro($title, $structure);
        //print_r($prompt_intro);

        $intro ="";
        $intro =$this->call_api($prompt_intro);
    
        // Développements
        $developments_html = '';
        if (!empty($node['children_ids'])) {
            // L'article a de vrais enfants : on génère normalement
            foreach ($node['children_ids'] as $child_id) {
                if (!isset($map[$child_id])) continue;
                $child = $map[$child_id];
                $prompt_dev = "";
                $prompt_dev = $this->promptProvider->development($child['title'], $structure);
                //print_r($prompt_dev);

                $dev_content ="";
                $dev_content =$this->call_api($prompt_dev);

                $child_link = '<p>Pour en savoir plus, découvrez notre article sur <a href="' . esc_url($child['link'] ?? '#') . '">' . esc_html($child['title']) . '</a>.</p>';
        
                $developments_html .= $dev_content . $child_link;
            }
        } else {
            // L'article est une feuille : on génère un développement complet artificiel
            $prompt_leaf =$this->promptProvider->leafDevelopment($title, $structure, $number);
            //print_r($prompt_leaf);
            $dev_content ="";
            $dev_content =$this->call_api($prompt_leaf);
            $developments_html .= $dev_content;
        }
    
        // Prompt et génération de la conclusion
        $prompt_conclusion = $this->promptProvider->conclusion($title, $structure);
        $conclusion ="";

        $conclusion =$this->call_api($prompt_conclusion);
    
        // Concatène toutes les parties
        return $intro .$developments_html. $conclusion;
    }
    
    
    /***
     * 
     * Récupération Image
     */
    
    private function get_freepik_image($keywords){
        
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
            "x-freepik-api-key: " . $this->freepik_api_key
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


    private static function generate_slug($title){
        $slug = sanitize_title($title);
        return $slug;
    }

    private function fetch_image_from_api(string $title, string $text): ?string {
        // 🔥 Normalisation du titre et du texte
        $normalized_title = $this->normalize_keyword($title);
        $normalized_text = $this->normalize_keyword($text);
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
