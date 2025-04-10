<?php
if (!defined('ABSPATH')) exit;

class CSB_Generator {
    private $freepik_api_key;
    private $api_key;
    private $model;
    private $temperature;
    private $style;
    private $image_description;

    private function getPromptArticle($keyword, $depth) {
        return "Tu es un expert en SEO. Génère une structure hiérarchique de cocon sémantique en texte brut.
        Consignes :
        - Pas de format Markdown (pas de *, **, ni de ```).
        - Utilise uniquement des tirets `-` pour représenter la hiérarchie.
        - Le mot-clé principal est : \"$keyword\"
        - La structure doit comporter $depth sous-thèmes, et chacun de ces sous-thèmes doit avoir $depth sous-sous-thèmes.
        - N’ajoute aucun commentaire ni explication. Juste la structure.
        
        Exemple attendu :
        - Titre principal
        - Sous-thème 1
            - Sous-sous-thème 1
            - Sous-sous-thème 2
        - Sous-thème 2
            - ...";
    }

   
    

    private function getPromptContent($title, $context) {
        return "Tu es un rédacteur web SEO.
    
        Rédige un article optimisé pour le sujet : \"$title\".

        Contexte :
        Cet article s’inscrit dans un cocon sémantique avec la hiérarchie suivante :
        $context

        Consignes :
        - Le contenu doit être du texte brut (pas de balises HTML ou Markdown).
        - Structure : introduction, paragraphes avec sous-titres, conclusion.
        - N’ajoute ni commentaires, ni balises.
        - Sois clair, informatif, naturel et professionnel.
        - N’inclus jamais le titre dans l’introduction.

        À la fin de l’article, propose une description courte d’image (10 mots max) adaptée à ce sujet, que l’on pourra rechercher sur Freepik. Format :
        [IMAGE: ...]";
    }

    private function getProntImage($title,$context){

    }
    
    
    


    public function __construct($api_key = null, $freepik_api_key = null) {
        $this->api_key = $api_key ?: get_option('csb_openai_api_key');
        $this->freepik_api_key = $freepik_api_key ?: get_option('csb_freepik_api_key');
        $this->model = get_option('csb_model', 'gpt-3.5-turbo');
        $this->temperature = floatval(get_option('csb_temperature', 0.7));
        $this->style = get_option('csb_writing_style', 'SEO');
    }



    public function generate_structure($keyword, $depth = 1) {
         $prompt = $this->getPromptArticle($keyword, $depth); 
         $raw = $this->call_api($prompt);
         return $this->clean_generated_structure($raw);
    }

    public function generate_structure_array($keyword, $depth = 1) {
        $markdown = $this->generate_structure($keyword, $depth);
        var_dump($this->parse_markdown_structure($markdown));
        return $this->parse_markdown_structure($markdown);
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
    

    private function clean_generated_structure($text) {
        return preg_replace('/^```.*$\n?|```$/m', '', $text);
    }
    

    public function generate_full_content(&$tree, $breadcrumb = []) {
        foreach ($tree as &$node) {
            $title = $node['title'];
            $context = implode(" > ", array_merge($breadcrumb, [$title]));
    
            $node['content'] = $this->generate_article_content($title, $context);
    
            if (!empty($node['children'])) {
                $this->generate_full_content($node['children'], array_merge($breadcrumb, [$title]));
            }
        }
    }

    private function generate_article_content($title, $context) {
        $prompt = $this->getPromptContent($title, $context);
        $result = $this->call_api($prompt);

        // Utiliser la fonction extract_image_tag()
        $image_description = $this->extract_image_tag($result); // Modifie $result pour enlever le tag
        //echo "<p>$image_description</p>";

        // Récupération de l'URL de l'image depuis Freepik
        $image_url = $this->get_freepik_image($image_description);

        // Intégration de l'image dans le contenu
        if (!str_starts_with($image_url, '❌')) {
            $result .= "\n\n<img src=\"" . esc_url($image_url) . "\" alt=\"" . esc_attr($image_description) . "\" style=\"max-width:100%; height:auto;\" />";
        } else {
            $result .= "\n\n<!-- Aucune image trouvée sur Freepik -->";
        }

        return [
            'content' => trim($result),
            'image' => $image_description
        ];
    }

    
    
    private function extract_image_tag(&$text) {
        $image = null;
        if (preg_match('/\[IMAGE:\s*(.+?)\]/', $text, $matches)) {
            $image = trim($matches[1]);
            $text = str_replace($matches[0], '', $text);
        }
        return $image;
    }
    private function get_freepik_link($description) {
        $query = urlencode($description);
        return "https://www.freepik.com/search?format=search&query=$query";
    } 
    private function get_freepik_image($description) {
        if (!$this->freepik_api_key) {
            return '❌ Clé API Freepik non configurée.';
        }
    
        $query = urlencode($description);
        $url = "https://api.freepik.com/v1/resources?query=$query&content_type=photo";
    
        $args = [
            'headers' => [
                'x-freepik-api-key' => $this->freepik_api_key,
            ],
            'timeout' => 60,
        ];
    
        $response = wp_remote_get($url, $args);
    
        if (is_wp_error($response)) {
            return '❌ Erreur API Freepik : ' . $response->get_error_message();
        }
    
        $body = json_decode(wp_remote_retrieve_body($response), true);
    
        if (isset($body['data'][0]['image']['source']['url'])) {
            return $body['data'][0]['image']['source']['url'];
        } else {
            return '❌ Aucune image trouvée pour cette description.';
        }
    }   
    
}
