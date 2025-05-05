<?php
if (!defined('ABSPATH')) exit;

class CSB_Admin {
    private $last_tree = [];
    private int $nb;
    private $mapIdPost=[];
    private $generator;

    public function __construct() {
        add_action('admin_menu', [$this, 'add_admin_menu']);
        //echo "DOG";
        add_action('admin_init', [$this, 'maybe_delete_author_posts']);
        $this->generator= new CSB_Generator(new CSB_Prompts());
    }

    public function add_admin_menu() {
        add_menu_page(
            'Cocon Sémantique',
            'Cocon Sémantique',
            'manage_options',
            'csb_admin', // <-- slug ici
            [$this, 'render_admin_page'],
            'dashicons-networking',
            30
        );
        
    }

    private function capitalize_each_word($text) {
        $text = strtolower($text);    
        $text = ucwords($text);     
        return $text;
    }

    public function render_admin_page() {
        echo '<form method="post">';
        submit_button('❌ Supprimer les articles de Nicolas', 'delete', 'delete_author_posts');
        echo '</form>';

        $keyword =$this->capitalize_each_word(isset($_POST['csb_keyword']) ? sanitize_text_field($_POST['csb_keyword']) : '');
        $this->nb = isset($_POST['csb_nb_nodes']) ? intval($_POST['csb_nb_nodes']) : 3;
        $use_existing_root = isset($_POST['use_existing_root']) ? 1 : 0;
        $existing_root_url = isset($_POST['existing_root_url']) ? $_POST['existing_root_url'] : '';
        $existing_root_url = $this->sanitize_to_relative_url($existing_root_url);



    
        if (!empty($keyword) && !empty($this->nb) && isset($_POST['submit'])) {
            
            //$this->last_tree = $generator->generate_structure_array($keyword, $this->nb);
            $this->last_tree = $this->generator->generate_structure_array($keyword, $this->nb,false);
            
            // echo "<br>";echo "<br>";echo "<br>";
            //     print_r($this->last_tree);
            //     echo "<br>";echo "<br>";echo "<br>";
        }
    
        if (isset($_POST['structure'])) {
            $this->last_tree = $_POST['structure'];
            $this->handle_structure_actions($this->last_tree); 
            
            if (isset($_POST['csb_validate_publish'])) {
                $this->nb = isset($_POST['csb_nb_nodes']) ? intval($_POST['csb_nb_nodes']) : 3;
                $this->process_structure();   
            }
        }
    
        echo '<div class="wrap">';
        echo '<h1>Générateur de Cocon Sémantique</h1>';
        $this->render_keyword_form($keyword, $this->nb);
        $this->render_structure_form($this->last_tree, 'structure', 0, $use_existing_root, $existing_root_url);
        echo '</div>';
    
        echo '<div style="margin: 1em 0; padding: 1em; border-left: 4px solid #0073aa; background: #f1f1f1;">';
        echo '<p><strong>🔐 Clé API :</strong> <a href="' . admin_url('admin.php?page=csb_settings') . '">Configurer ici</a></p>';
        echo '</div>';
    
        if (!empty($this->last_tree)) {
            echo '<div class="wrap"><h2>🔗 Articles publiés</h2><ul>';
            $this->render_links_to_articles();
            echo '</ul></div>';
        }
    }
    private function render_keyword_form($keyword, $nb) {
        $use_existing_root = isset($_POST['use_existing_root']) ? 1 : 0;
    
        $original_url = isset($_POST['existing_root_url']) ? sanitize_text_field($_POST['existing_root_url']) : '';
        $existing_root_url = $this->sanitize_to_relative_url($original_url); // <-- Conversion ici
    
        echo '<form method="post">';
        echo '<table class="form-table">';
        
        // Champ mot-clé
        echo '<tr><th><label for="csb_keyword">Mots Clés principaux</label></th>';
        echo '<td><input type="text" id="csb_keyword" name="csb_keyword" value="' . esc_attr($keyword) . '" class="regular-text" required></td></tr>';
        
        // Champ nombre de niveaux
        echo '<tr><th><label for="csb_nb_nodes">Nombre de sous-niveaux</label></th>';
        echo '<td><input type="number" id="csb_nb_nodes" name="csb_nb_nodes" value="' . esc_attr($nb) . '" class="regular-text" required></td></tr>';
    
        // Case à cocher
        echo '<tr><th><label for="use_existing_root">Utiliser un article racine existant</label></th>';
        echo '<td><input type="checkbox" id="use_existing_root" name="use_existing_root" value="1" ' . checked(1, $use_existing_root, false) . '></td></tr>';
    
        // Champ URL
        echo '<tr><th><label for="existing_root_url">URL de l’article racine</label></th>';
        echo '<td><input type="text" id="existing_root_url" name="existing_root_url" value="' . esc_attr($existing_root_url) . '" class="regular-text">';
        echo '<p class="description">Uniquement une URL relative (ex: /mon-article)</p>';
    
        if (!empty($original_url) && str_starts_with($original_url, 'http')) {
            echo '<p style="color: red;">❗ L’URL absolue a été automatiquement convertie en lien relatif';
        }
    
        echo '</td></tr>';
        echo '</table>';
        submit_button('Générer la structure', 'primary', 'submit');
        echo '</form>';
    }
    
    

    private function render_structure_form($tree, $prefix = 'structure', $level = 0, $use_existing_root = 0, $existing_root_url = ''){
        echo '<form method="post">';
        echo '<input type="hidden" name="csb_nb_nodes" value="' . intval($this->nb) . '" />';

        echo '<fieldset style="padding: 1em; border: 1px solid #ccd0d4; background: #fff; margin-bottom: 1em;">';
        echo '<legend style="font-weight: bold;">Structure générée</legend>';

        $this->render_structure_fields($tree, $prefix, $level);

        echo '</fieldset>';

        if ($use_existing_root) {
            echo '<input type="hidden" name="use_existing_root" value="1" />';
        }
        if (!empty($existing_root_url)) {
            echo '<input type="hidden" name="existing_root_url" value="' . esc_attr($existing_root_url) . '" />';
        }
        if (!empty($this->last_tree)) 
            submit_button('Valider et publier', 'primary', 'csb_validate_publish');
        echo '</form>';
    }

    
    public function maybe_delete_author_posts() {
        if (isset($_POST['delete_author_posts']) && current_user_can('manage_options')) {
            $this->delete_all_posts_by_author();
        }
    }
    
    private function delete_all_posts_by_author($author_login = '83') {
        global $wpdb;
        //echo "///////////////////////////////////////";
        $author_id = $wpdb->get_var($wpdb->prepare(
            "SELECT ID FROM $wpdb->users WHERE user_login = 83"
        ));
        
    
        // if (!$author_id) {
        //     echo '<div class="notice notice-error"><p>❌ Aucun utilisateur trouvé avec le login "' . esc_html($author_login) . '".</p></div>';
        //     return;
        // }
    
        $post_ids = $wpdb->get_col($wpdb->prepare(
            "SELECT ID FROM $wpdb->posts WHERE post_type = 'post' AND post_author = %d",
            $author_id
        ));

    
        if (empty($post_ids)) {
            echo '<div class="notice notice-info"><p>ℹ️ Aucun article trouvé pour l’auteur "' . esc_html($author_login) . '".</p></div>';
            return;
        }
    
        foreach ($post_ids as $post_id) {
            wp_delete_post($post_id, true); // suppression définitive
        }
    
        echo '<div class="notice notice-success"><p>✅ ' . count($post_ids) . ' article(s) de "' . esc_html($author_login) . '" supprimé(s) avec succès.</p></div>';
    }


    private function render_structure_fields($tree, $prefix, $level) {
        echo '<ul style="list-style-type: none; margin: 0; padding-left: ' . (($level+20)) . 'px;">';
        //print_r($level);
    
        foreach ($tree as $index => $node) {
            $node_prefix = $prefix . "[$index]";
            echo '<li style="margin-bottom: 10px;">';
            
            echo '<div style="display: flex; align-items: center; gap: 6px;">';
            echo '<span style="min-width: 10px;">-</span>';
            echo '<input type="text" name="' . esc_attr($node_prefix . '[title]') . '" value="' . esc_attr($node['title']) . '" class="regular-text" required />';
            echo '<button type="submit" name="delete_node" value="' . esc_attr($node_prefix) . '" style="padding: 2px 6px;">🗑️</button>';
            echo '<button type="submit" name="add_child" value="' . esc_attr($node_prefix) . '" style="padding: 2px 6px;">➕ Sous-thème</button>';
            echo '</div>';
    
            if (!empty($node['children'])) {
                //echo "DOG";
                $this->render_structure_fields($node['children'], $node_prefix . '[children]', $level + 30 );
            }
            // else
            //     echo "cat";
    
            echo '</li>';
        }
    
        echo '</ul>';
    }
    
    

    private function process_structure() {
        $publisher = new CSB_Publisher();
        $linker = new CSB_Linker();
        $use_existing_root = isset($_POST['use_existing_root']) && $_POST['use_existing_root'] == '1';
        $forced_link = null;

        if ($use_existing_root && !empty($_POST['existing_root_url'])) {
            $forced_link = sanitize_text_field($_POST['existing_root_url']);
        }

    
        // Étape 1 : Créer les articles
        $publisher->registerAllPost($this->last_tree);
    
        // Étape 2 : Construire la map des articles
        $root = reset($this->last_tree); 
        $this->mapIdPost = $this->build_node_map($root, null, $forced_link);

        // if (!empty($forced_link)) {
        //     echo '<div class="notice notice-info is-dismissible"><p>🔗 Lien utilisateur : <a href="' . esc_url($forced_link) . '" target="_blank">' . esc_html($forced_link) . '</a></p></div>';
        // }
        // else
        //     echo '<div class="notice notice-info is-dismissible"><p>🔗 Lien utilisateur absent</p></div>';

        // Étape 3 : Générer et publier chaque article individuellement
        foreach ($this->mapIdPost as $id => $info) {
            if ($info['parent_id'] === null && !empty($forced_link)) {
                continue;
            }
            $html =$this->generator->generateContent($id, $this->mapIdPost, $this->nb);
            $html.=$linker->generate_structured_links($this->mapIdPost,$id);
            $publisher->fill_and_publish_content($id, $html);
        }
    
        // 🔥 Après publication, récupérer les tokens utilisés
        $total_tokens = $this->generator->get_tokens_used();
        //curl('https://isoluce.slack.com/archives/D08MREPLUGG/p1745596328927739');

        echo '<div class="notice notice-success is-dismissible"><p>✅ Tous les articles ont été mis à jour avec leur contenu complet.</p></div>';
        echo '<div class="notice notice-info is-dismissible"><p>🧠 Nombre total de tokens utilisés : <strong>' . intval($total_tokens) . '</strong> tokens.</p></div>';
        
        // if (!empty($_POST['existing_root_url'])) {
        //     echo '<p>🔗 Lien fourni par l\'utilisateur : ' . esc_url($_POST['existing_root_url']) . '</p>';
        // }
        // else 
        //     echo '<p> Pas de lien fornie</p>';
    }
    
    private function sanitize_to_relative_url(string $url): string {
        $parsed = parse_url($url);
    
        if (!isset($parsed['path'])) {
            return '';
        }
    
        $relative_url = $parsed['path'];
    
        // Ajoute la query string si elle existe
        if (isset($parsed['query'])) {
            $relative_url .= '?' . $parsed['query'];
        }
    
        // Ajoute le fragment si nécessaire
        if (isset($parsed['fragment'])) {
            $relative_url .= '#' . $parsed['fragment'];
        }
    
        return $relative_url;
    }
    


    private function debug_display_tree(array $tree, int $indent = 0) {
        foreach ($tree as $slug => $node) {
            $prefix = str_repeat('— ', $indent);
            $title = $node['title'] ?? $slug;
            $post_id = isset($node['post_id']) ? " (ID: {$node['post_id']})" : '';
            echo "{$prefix}<strong>{$title}</strong>{$post_id}<br>";
    
            if (!empty($node['children'])) {
                $this->debug_display_tree($node['children'], $indent + 1);
            }
        }
    }
    

    public static function debug_display_links(array $tree, $indent = 0) {
        foreach ($tree as $slug => $node) {
            $title = $node['title'] ?? $slug;
            $link = $node['link'] ?? '❌ Aucun lien';
            echo str_repeat('—', $indent) . " <strong>{$title}</strong> → <a href='{$link}' target='_blank'>{$link}</a><br>";
    
            if (!empty($node['children'])) {
                self::debug_display_links($node['children'], $indent + 1);
            }
        }
    }

    private function build_node_map(array $node, ?string $parent_id = null, ?string $forced_link = null): array {
        $map = [];
        //$linker= new CSB_Linker()
    
        if (isset($node['post_id'])) {
            $entry = [
                'post_id'     => $node['post_id'],
                'title'        => $node['title'] ?? '',
                'link' => $forced_link ?? ($node['post_id'] ? wp_make_link_relative(get_permalink($node['post_id'])) : null),
                'parent_id'   => $parent_id,
                'children_ids' => []
            ];
    
            if (!empty($node['children'])) {
                $count = 0;
                foreach ($node['children'] as $child) {
                    if (isset($child['post_id'])) {
                        $entry['children_ids'][] = $child['post_id'];
                        $count++;
                        if ($count >= 3) break; // Limite à 3 enfants
                    }
                }
            }
    
            $map[$node['post_id']] = $entry;
    
            // Ensuite, on descend récursivement
            if (!empty($node['children'])) {
                foreach ($node['children'] as $child) {
                    $map += $this->build_node_map($child, $node['post_id']);
                }
            }
        }
    
        return $map;
    }
    

    private function generate_slug($title) {
        global $wpdb;
        $base_slug = sanitize_title($title);
        $slug = $base_slug;
        $i = 1;

        while ($wpdb->get_var($wpdb->prepare(
            "SELECT ID FROM $wpdb->posts WHERE post_name = %s AND post_type = 'post'",
            $slug
        ))) {
            $slug = $base_slug . '-' . $i;
            $i++;
        }

        return $slug;
    }
    private function &get_node_by_path(&$tree, $path_array) {
        $ref = &$tree;
        foreach ($path_array as $index) {
            if (!isset($ref[$index]['children'])) {
                $ref[$index]['children'] = [];
            }
            $ref = &$ref[$index]['children'];
        }
        return $ref;
    }
    

    private function handle_structure_actions(&$tree) {
        //return;
        // Ajout d'un enfant
        if (isset($_POST['add_child'])) {
            $path = str_replace(['structure[', ']'], '', $_POST['add_child']);
            $segments = explode('[', $path);
            $current = array_filter($segments, fn($v) => $v !== 'children');
            $last = array_pop($current);
    
            // Accéder au parent via le chemin
            $parent = &$this->get_node_by_path($tree, $current);
    
            if (!isset($parent[$last]['children'])) {
                $parent[$last]['children'] = [];
            }
    
            $title = 'Nouveau sous-thème';
            $slug = $this->generate_slug($title);
    
            // Utiliser le slug comme clé
            $parent[$last]['children'][$slug] = [
                'title' => $title,
                'slug' => $slug,
                'children' => []
            ];
        }
    
        // Suppression d'un nœud
        if (isset($_POST['delete_node'])) {
            $raw_path = explode('[', str_replace(']', '', str_replace('structure[', '', $_POST['delete_node'])));
            $path = array_filter($raw_path, fn($v) => $v !== 'children');
            $path = array_values($path); // réindexer
            $this->delete_node_at_path($tree, $path);
        }
    }
    
    
    
    private function delete_node_at_path(&$tree, $path) {
        $ref = &$tree;
    
        while (count($path) > 1) {
            $key = array_shift($path); // pas de intval : on travaille avec des slugs
            if (!isset($ref[$key]['children'])) return;
            $ref = &$ref[$key]['children'];
        }
    
        $final_key = array_shift($path);
        if (isset($ref[$final_key])) {
            unset($ref[$final_key]);
        }
    }
       

    private function render_links_to_articles($parent_id = null, $level = 0) {
        echo '<ul style="padding-left: ' . (20 * $level) . 'px;">';
    
        foreach ($this->mapIdPost as $id => $node) {
            // if ($node['parent_id'] === $parent_id) {
            //     $title = esc_html($node['title'] ?? "Article #$id");
            //     $url = $node['link'];
            //     echo "<li><a href='" . esc_url($url) . "' target='_blank'>🔗 $title</a>";
    
            //     // 🔥 Appel récursif pour afficher les enfants, **À L'INTÉRIEUR DU LI**
            //     $this->render_links_to_articles($id, $level + 1);
    
            //     echo "</li>"; // Fermeture du LI APRÈS les enfants
            // }
            $title = esc_html($node['title'] ?? "Article #$id");
            $url = $node['link'];
            echo "<li><a href='" . esc_url($url) . "' target='_blank'>🔗 $title</a>";

            //🔥 Appel récursif pour afficher les enfants, **À L'INTÉRIEUR DU LI**
            //$this->render_links_to_articles($id, $level + 1);

            echo "</li>"; // Fermeture du LI APRÈS les enfants
        }
    
        echo '</ul>';
    }
      
}

function download_public_file($file_url) {
    $ch = curl_init();

    curl_setopt($ch, CURLOPT_URL, $file_url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true); // Pour suivre les redirections (important pour Slack)

    $response = curl_exec($ch);
    $err = curl_error($ch);
    curl_close($ch);

    if ($err) {
        throw new Exception("Erreur cURL : " . $err);
    }

    return $response; // C'est du contenu brut (image, fichier...)
}


