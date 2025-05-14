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


    public function getTokensUsed() {
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

    public function generateStructure(string $keyword, int $depth = 1, bool $test = false): string {
        $default = self::generateDefaultStructure($keyword);
        $prompt = $this->promptProvider->structure($keyword, $depth);
        return $this->generateTexte($keyword, $test, $default, $prompt);
    }
    


    public function generateImage(string $title, string $keyword, bool $test = false): string {
        $default_image_url = plugin_dir_url(__FILE__) . '../image_test.png';

        $prompt = $this->promptProvider->image($keyword, $title);

        return $this->generate(
            fn($p) => $this->fetch_image_from_api($title, $this->callApi($p), 15),
            $prompt,
            $test,
            $default_image_url
        );
    }

    public function generateIntro(string $title, string $structure, string $slug, bool $test): string {
        $prompt = $this->promptProvider->intro($title, $structure);
        $default = self::getDefaultIntro($title);
        return $this->generateTexte($title, $test, $default, $prompt);
    }


    public function generateDevelopment(string $title, string $structure, bool $test): string {
        $prompt = $this->promptProvider->development($title, $structure);
        $default = self::getDefaultDevelopment($title);

        return $this->generateTexte($title, $test, $default, $prompt);
    }

    public function generateConclusion(string $title, string $structure, string $slug, bool $test): string {
        $prompt = $this->promptProvider->conclusion($title, $structure);
        $default = self::getDefaultConclusion($title);

        return $this->generateTexte($title, $test, $default, $prompt);
    }


    public function generateLeaf(string $title, string $structure, int $nb, bool $test = false): string {
        $prompt = $this->promptProvider->leafParts($title, $structure, $nb);
        $default = self::getDefaultLeafParts($title);
        return $this->generateTexte($title, $test, $default, $prompt);
    }


    private function generateTexte(string $title, bool $test, string $defaultContent, string $prompt): string {
        return $this->generate(fn($p) => $this->callApi($p), $prompt, $test, $defaultContent);
    }

    private function generate(callable $method, string $prompt, bool $test = false, string $default = ''): string {
        $content = $default;
        if (!$test) {
            try {
                $content = $method($prompt);
            } 
            catch (\Throwable $e) {
                error_log("❌ Erreur dans generate() : " . $e->getMessage());
            }
        }

        return $content;
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
