<?php
if (!defined('ABSPATH')) exit;

require_once __DIR__ . '/interface-csb-generator.php';

class CSB_Generator implements GeneratorInterface {
    private $api_key;
    private $model;
    private $temperature;
    private $style;
    //private $image_description;
    private PromptProviderInterface $promptProvider;
    private $tokens_used = 0;
    private $defaultImage;

    
    public function __construct(PromptProviderInterface $promptProvider,$defaultImage ,$api_key = null) {
        $this->promptProvider = $promptProvider;
        $this->api_key = $api_key ?: get_option('csb_openai_api_key');
        $this->model = get_option('csb_model', 'gpt-3.5-turbo');
        $this->defaultImage=$defaultImage;
        $this->temperature = floatval(get_option('csb_temperature', 0.7));
        $this->style = get_option('csb_writing_style', 'SEO');
        
    }


    private static function getDefaultIntro(string $title): string {
        return "<p><em>Introduction par défaut sur «&nbsp;$title&nbsp;».</em></p>";
    }

    private static function getDefaultDevelopment(string $title): string {
        return "<p><em>Développement par défaut pour «&nbsp;$title&nbsp;».</em></p>";
    }

    private static function getDefaultConclusion(string $title): string {
        return "<p><em>Conclusion par défaut sur «&nbsp;$title&nbsp;».</em></p>";
    }

    private static function generateDefaultStructure(string $keyword = 'Thème Principal'): string {
        $structure = "- " . ucwords($keyword) . "\n";

        // Niveau 1
        foreach (['A', 'B'] as $lvl1) {
            $structure .= "    - Niveau 1 $lvl1\n";

            // Niveau 2
            foreach ([1, 2] as $i) {
                $structure .= "        - Niveau 2 {$lvl1}{$i}\n";

                // Niveau 3 (feuilles)
                foreach (['a', 'b'] as $j) {
                    $structure .= "            - Niveau 3 {$lvl1}{$i}{$j}\n";
                }
            }
        }

        return $structure;
    }

    private static function getDefaultImage(){return plugin_dir_url(dirname(__DIR__)) . 'image_test.png';}

    public function getTokensUsed() {
        return $this->tokens_used;
    }



    private function normalizeKeyword($title) {
        // Convertir les accents
        $translit = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $title);
        // Nettoyer les caractères non alphanumériques sauf espace
        $clean = preg_replace('/[^a-zA-Z0-9 ]/', '', $translit);
        // Retourner le résultat en minuscule
        return strtolower(trim($clean));
    }

    /**
     * Envoie un prompt à l'API OpenAI et retourne la réponse.
     *
     * @param string $prompt Le texte à envoyer.
     * @param bool $base64 Si true, retourne la réponse encodée en base64.
     * @param bool $preserveFormatting Si true, conserve la mise en forme du texte.
     *
     * @return string La réponse générée ou un message d'erreur.
    */
    private function callApi(string $prompt, bool $base64 = false, bool $preserveFormatting = false): string {
        $result = '';
        //error_log("clé $this->api_key");

        if (empty($this->api_key)) {
            $result = '❌ Clé API non configurée.';
            error_log("[CSB ERROR] Clé API manquante.");
        } else {
            $url = 'https://api.openai.com/v1/chat/completions';

            $data = [
                'model' => $this->model,
                'messages' => [
                    ['role' => 'system', 'content' => "Tu es un rédacteur {$this->style}."],
                    ['role' => 'user', 'content' => $prompt],
                ],
                'temperature' => $this->temperature,
                'max_tokens' => 800,
            ];

            $args = [
                'headers' => [
                    'Content-Type'  => 'application/json',
                    'Authorization' => 'Bearer ' . $this->api_key,
                ],
                'body' => json_encode($data),
                'timeout' => 30,
                'httpversion' => '1.1',
            ];

            $response = wp_remote_post($url, $args);

            if (is_wp_error($response)) {
                $result = '❌ Erreur API : ' . $response->get_error_message();
                error_log("[CSB ERROR] API WP_Error : " . $response->get_error_message());
            } else {
                $body_raw = wp_remote_retrieve_body($response);
                $body = json_decode($body_raw, true);

                if (json_last_error() !== JSON_ERROR_NONE) {
                    $result = '❌ Erreur de décodage JSON.';
                    error_log("[CSB ERROR] JSON invalide : " . json_last_error_msg() . " | Réponse brute : " . $body_raw);
                } elseif (!isset($body['choices'][0]['message']['content'])) {
                    $result = '❌ Erreur : réponse OpenAI invalide ou vide.';
                    error_log("[CSB ERROR] Réponse inattendue : " . print_r($body, true));
                } else {
                    if (isset($body['usage']['total_tokens'])) {
                        $this->tokens_used += (int)$body['usage']['total_tokens'];
                    }

                    $raw = $body['choices'][0]['message']['content'];
                    $result = $preserveFormatting ? rtrim($raw) : trim(preg_replace('/\s+/', ' ', $raw));
                }
            }
        }

        return $base64 ? base64_encode($result) : $result;
    }


    public function generateStructure(string $keyword, int $depth, int $breadth, PromptContext $context, bool $test = false): string {
        $default = self::generateDefaultStructure($keyword, $depth, $breadth);
        $prompt = $this->promptProvider->structure($keyword, $depth, $breadth, $context);
        return $this->generateTexte($keyword, $test, $default, $prompt, true);
    }

    public function generateImage(string $title, string $keyword, PromptContext $context, bool $test = false): string {
        $default_image_url = $this->getDefaultImage();
        $prompt = $this->promptProvider->image($keyword, $title, $context);

        return $this->generate(
            fn($p) => $this->fetchImageFromPosteria($title, $this->callApi($p), 15),
            $prompt,
            $test,
            $default_image_url
        );
    }

    public function generateIntro(string $title, string $structure, PromptContext $context, bool $test): string {
        $prompt = $this->promptProvider->intro($title, $structure, $context);
        $default = self::getDefaultIntro($title);
        return $this->generateTexte($title, $test, $default, $prompt);
    }

    public function generateDevelopment(string $title, string $structure, PromptContext $context, bool $test): string {
        $prompt = $this->promptProvider->development($title, $structure, $context);
        $default = self::getDefaultDevelopment($title);
        return $this->generateTexte($title, $test, $default, $prompt);
    }

    public function generateConclusion(string $title, string $structure, PromptContext $context, bool $test): string {
        $prompt = $this->promptProvider->conclusion($title, $structure, $context);
        $default = self::getDefaultConclusion($title);
        return $this->generateTexte($title, $test, $default, $prompt);
    }

    public function generateFullContent(string $keyword, string $title, string $structure, array $subparts, PromptContext $context, bool $test = false): string {
        $prompt = $this->promptProvider->fullArticle($keyword, $title,$subparts, $context);
        error_log("$prompt");
        $default = self::getDefaultIntro($title)
                . self::getDefaultDevelopment($title)
                . self::getDefaultConclusion($title);
        return $this->generateTexte($title, $test, $default, $prompt, true);
    }

    private function generateTexte(string $title, bool $test, string $defaultContent, string $prompt, bool $preserveFormatting = false): string {
        return $this->generate(fn($p) => $this->callApi($p, false, $preserveFormatting), $prompt, $test, $defaultContent);
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


    private function fetchImageFromPosteria(string $title, string $text): ?string {
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
