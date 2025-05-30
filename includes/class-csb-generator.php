<?php
if (!defined('ABSPATH')) exit;

require_once __DIR__ . '/interface-csb-generator.php';

class CSB_Generator implements GeneratorInterface {
    private $api_key;
    private $model;
    private $temperature;
    private $style;
    //private $image_description;
    //private PromptProviderInterface $promptProvider;
    private $tokens_used = 0;
    //private $defaultImage;

    
    public function __construct($api_key = null) {
        $this->api_key = $api_key ?: get_option('csb_openai_api_key');
        $this->model = get_option('csb_model', 'gpt-3.5-turbo');
        //$this->defaultImage=$defaultImage;
        $this->temperature = floatval(get_option('csb_temperature', 0.7));
        $this->style = get_option('csb_writing_style', 'SEO');
        
    }

    public function getTokensUsed(){return $this->tokens_used;}


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
        //error_log("Call openIA promt : $prompt");

        if (empty($this->api_key)) {
            $result = '❌ Clé API non configurée.';
            error_log("[CSB ERROR] Clé API manquante.");
        } 
        else {
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
                'timeout' => 60,
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
        //error_log($result);

        return $base64 ? base64_encode($result) : $result;
    }


    public function generateImage(
        string $title,
        string $imageDescription,
        PromptContext $context,
        string $defaultImage,
        bool   $test = false
    ): string {

        return $this->generate(
            function (string $desc) use ($title, $defaultImage) {
                // tente de récupérer l’image distante
                $url = $this->fetchImageFromPosteria($title, $desc);

                // si l’API renvoie null ou chaîne vide → on bascule sur l’image par défaut
                return empty($url) ? $defaultImage : $url;
            },
            $imageDescription,
            $test,
            $defaultImage
        );
    }




    public function generateTexte(string $title, bool $test, string $defaultContent, string $prompt, bool $preserveFormatting = false): string {
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

        $keyword =$this->normalizeKeyword($title); 
        $desc= $this->normalizeKeyword($text);     

        error_log("Mot clé principal ".rawurlencode($keyword));
        error_log("Text ".rawurlencode($desc));
        
        $api_url = 'https://app.posteria.fr/crons/freepikImageCoconSemantique/' . rawurlencode($keyword) . '/' . rawurlencode($desc);

        $ch = curl_init($api_url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT        => 20,
        ]);

        $body   = curl_exec($ch);
        $code   = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err    = curl_error($ch);
        curl_close($ch);

        if ($err || $code !== 200) {
            error_log("❌ Image API error ($code) : $err | url=$api_url");
            return null;                      
        }

        return trim($body);
    }


}
