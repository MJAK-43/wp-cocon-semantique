<?php

namespace CSB\Core\Generator;

if (!defined('ABSPATH')) exit;

/**
 * Gère les appels à l’API OpenAI (ou autre LLM) pour la génération de contenu.
 */
class ContentApiCaller extends BaseApiCaller
{
    private string $apiKey;
    private string $model;
    private float $temperature;
    private string $style;

    private int $tokensUsed = 0;

    public function __construct(?string $apiKey = null, ?string $model = null, ?float $temperature = null, ?string $style = null)
    {
        $this->apiKey = $apiKey ?: get_option('csb_openai_api_key');
        $this->model = $model ?: get_option('csb_model', 'gpt-3.5-turbo');
        $this->temperature = $temperature ?? floatval(get_option('csb_temperature', 0.7));
        $this->style = $style ?: get_option('csb_writing_style', 'SEO');
    }

    /**
     * Envoie un prompt à l’API et retourne le texte généré.
     *
     * @param string $prompt
     * @param bool $base64 Si true, encode le résultat en base64.
     * @param bool $preserveFormatting Si true, ne nettoie pas les retours à la ligne.
     * @return string
     */
    private function call(string $prompt, bool $base64 = false, bool $preserveFormatting = false): string {
        $result = '';

        if (empty($this->api_key)) {
            $result = '❌ Clé API non configurée.';
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
                $this->logError('ContentApiCaller', $response->get_error_message());
                $result = '❌ Erreur API : ' . $response->get_error_message();
            } else {
                $body = json_decode(wp_remote_retrieve_body($response), true);

                if (!isset($body['choices'][0]['message']['content'])) {
                    $this->logError('ContentApiCaller', 'Réponse OpenAI invalide ou vide.');
                    $result = '❌ Erreur : réponse OpenAI invalide ou vide.';
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
    
}
