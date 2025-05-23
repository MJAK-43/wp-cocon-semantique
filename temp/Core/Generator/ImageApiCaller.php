<?php

namespace CSB\Core\Generator;

if (!defined('ABSPATH')) exit;

/**
 * Gère la génération d'images via une API externe (Posteria, Freepik...).
 */
class ImageApiCaller extends BaseApiCaller
{
    /**
     * Appelle le service d’image (ex: Posteria) pour générer une image.
     *
     * @param string $title     Titre de l’article.
     * @param string $description Description textuelle pour guider la génération.
     * @return string|null      URL de l’image générée ou null en cas d’erreur.
     */
    public function fetchImageFromPosteria(string $title, string $description): ?string
    {
        $normalized_title = $this->normalize($title);
        $normalized_text = $this->normalize($description);

        $url = 'https://app.posteria.fr/crons/freepikImageCoconSemantique/' .
               rawurlencode($normalized_title) . '/' . rawurlencode($normalized_text);

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
        ]);

        $response = curl_exec($ch);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            $this->logError('ImageApiCaller', $error);
            return null;
        }

        return trim($response);
    }

    /**
     * Normalise un texte pour usage en URL/API.
     */
    private function normalize(string $input): string
    {
        $translit = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $input);
        $clean = preg_replace('/[^a-zA-Z0-9 ]/', '', $translit);
        return strtolower(trim($clean));
    }
}
