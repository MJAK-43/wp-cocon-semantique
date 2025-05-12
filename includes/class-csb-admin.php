<?php
if (!defined('ABSPATH')) exit;

class CSB_Admin {
    private int $nb;
    private $mapIdPost=[];
    private $mapIdPostLoaded=[];
    private $generator;
    private $publisher;

    private static $minExecutionTime=600;
    private static $minInputTime=60;
    private static $minSize=32;

    // private static $minExecutionTime=1;
    // private static $minInputTime=1;
    // private static $minSize=1;

    public function __construct() {
        add_action('admin_menu', [$this, 'add_admin_menu']);
        //echo "DOG";
        // add_action('admin_init', [$this, 'maybe_delete_author_posts']);
        $this->generator= new CSB_Generator(new CSB_Prompts());
        $this->publisher=  new CSB_Publisher();
    }


    private static function convertBytes($size) {
        $unit = strtoupper(substr($size, -1));
        $value = (int) $size;

        switch($unit) {
            case 'G': return $value * 1024 * 1024 * 1024;
            case 'M': return $value * 1024 * 1024;
            case 'K': return $value * 1024;
            default:  return (int) $size;
        }
    }


    private static function checkServerRequirements(): array {
        $errors = [];

        $maxExecutionTime = (int) ini_get('max_execution_time');
        $maxInputTime = (int) ini_get('max_input_time');
        $postMaxSizeRaw = ini_get('post_max_size');
        $postMaxSize = self::convertBytes($postMaxSizeRaw);

        // Comparaisons
        if ($maxExecutionTime < self::$minExecutionTime) {
            $errors[] = "⏱️ `max_execution_time` est trop bas : $maxExecutionTime (minimum requis : " . self::$minExecutionTime . ")";
        }

        if ($maxInputTime < self::$minInputTime) {
            $errors[] = "📥 `max_input_time` est trop bas : $maxInputTime (minimum requis : " . self::$minInputTime . ")";
        }

        if ($postMaxSize < self::$minSize * 1024 * 1024) { // minSize est en Mo
            $errors[] = "📦 `post_max_size` est trop petit : $postMaxSizeRaw (minimum requis : " . self::$minSize . "M)";
        }

        return $errors;
    }


    public function add_admin_menu() {
        add_menu_page(
            'Cocon Sémantique',
            'Cocon Sémantique',
            'manage_options',
            'csb_admin',
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

        $keyword =$this->capitalize_each_word(isset($_POST['csb_keyword']) ? sanitize_text_field($_POST['csb_keyword']) : '');
        $this->nb = isset($_POST['csb_nb_nodes']) ? intval($_POST['csb_nb_nodes']) : 3;
        $use_existing_root = isset($_POST['use_existing_root']) ? 1 : 0;
        $existing_root_url = isset($_POST['existing_root_url']) ? $_POST['existing_root_url'] : '';
        $existing_root_url = $this->sanitize_to_relative_url($existing_root_url);


        if (empty($this->mapIdPost)) {
            $this->mapIdPost = get_option('csb_structure_map', []);
        }


        if (isset($_POST['load_existing_cocon'])) {
            $root_post_id = intval($_POST['load_existing_cocon']);
            $this->mapIdPostLoaded = $this->rebuildCoconFromRoot($root_post_id);
            update_option('csb_structure_map', $this->mapIdPost);
        }

        if((!empty($keyword) && !empty($this->nb) && isset($_POST['submit']))||(isset($_POST['structure']))){
            if (!empty($keyword) && !empty($this->nb) && isset($_POST['submit'])) {

                //$this->generator->setKeyword($keyword);
                $raw = $this->generator->generateStructure($keyword,$this->nb,false);
                $this->mapIdPost = $this->convertStructureToMap($raw, $use_existing_root ? $existing_root_url : null);
                update_option('csb_structure_map', $this->mapIdPost);
            }

            if (isset($_POST['structure'])) {
                $this->handleStructureActionsMap($this->mapIdPost);

                 // Synchronise les modifications utilisateur (titres)
                if (isset($_POST['structure']) && is_array($_POST['structure'])) {
                    $this->updateMapFromPost($this->mapIdPost, $_POST['structure']);
                    update_option('csb_structure_map', $this->mapIdPost);
                }

                if (isset($_POST['csb_validate_publish'])) {
                    $this->nb = isset($_POST['csb_nb_nodes']) ? intval($_POST['csb_nb_nodes']) : 3;
                    $word= reset($this->mapIdPost)['title']; 
                    $this->process_structure($word);
                    echo '<div class="wrap"><h2>🔗 Articles publiés</h2><ul>';
                    $this->render_links_to_articles();
                    echo '</ul></div>';
                }
            }
            $total_tokens = $this->generator->get_tokens_used();
            echo '<div class="notice notice-info is-dismissible"><p>Nombre total de tokens utilisés : <strong>' . intval($total_tokens) . '</strong> tokens.</p></div>';
        }


        echo '<div class="wrap">';
        echo '<h1>Générateur de Cocon Sémantique</h1>';
        // Vérification des paramètres système
        $errors = $this->checkServerRequirements();

        if(!empty($errors)){
            echo '<div class="notice notice-error"><ul>';
            foreach ($errors as $error) {
                echo '<li>' . esc_html($error) . '</li>';
            }
            echo '</ul></div>';
        }
        else{
            $this->render_keyword_form($keyword, $this->nb);
            $this->render_structure_form('structure', 0, $use_existing_root, $existing_root_url);
        }

        echo '</div>';

        echo '<div style="margin: 1em 0; padding: 1em; border-left: 4px solid #0073aa; background: #f1f1f1;">';
        echo '<p><strong>🔐 Clé API :</strong> <a href="' . admin_url('admin.php?page=csb_settings') . '">Configurer ici</a></p>';
        echo '</div>';


        $roots = $this->publisher->getAllRootNodesFromMeta();
        echo '<h2>🌳 Racines des cocons existants</h2><ul>';
        foreach ($roots as $root) {
            echo '<li><strong>' . esc_html($root['title']) . '</strong> - ';
            $this->render_load_existing_button($root['post_id']);
            echo '</li>';
        }
        echo '</ul>';


        if (!empty($this->mapIdPostLoaded)) {
            echo '<h2>📂 Structure du cocon chargé</h2>';
            $this->render_loaded_structure();
        }
    }

    private function render_structure_form($prefix = 'structure', $level = 0, $use_existing_root = 0, $existing_root_url = ''){
        echo '<form method="post">';
        echo '<input type="hidden" name="csb_nb_nodes" value="' . intval($this->nb) . '" />';

        echo '<fieldset style="padding: 1em; border: 1px solid #ccd0d4; background: #fff; margin-bottom: 1em;">';
        echo '<legend style="font-weight: bold;">Structure générée</legend>';

        // Affichage à partir de la racine (parent_id null)
        $this->render_structure_fields(null, $prefix, 0);

        echo '</fieldset>';

        if ($use_existing_root) {
            echo '<input type="hidden" name="use_existing_root" value="1" />';
        }
        if (!empty($existing_root_url)) {
            echo '<input type="hidden" name="existing_root_url" value="' . esc_attr($existing_root_url) . '" />';
        }

        submit_button('Valider et publier', 'primary', 'csb_validate_publish');
        echo '</form>';
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


    private function render_structure_fields(?int $parent_id, string $prefix, int $level) {
        echo '<ul style="list-style-type: none; margin: 0; padding-left: ' . ($level * 20) . 'px;">';

        foreach ($this->mapIdPost as $id => $node) {
            if ($node['parent_id'] !== $parent_id) continue;

            $node_prefix = $prefix . "[$id]";

            echo '<li style="margin-bottom: 10px;">';
            echo '<div style="display: flex; align-items: center; gap: 6px;">';
            echo '<span style="min-width: 10px;">-</span>';
            echo '<input type="text" name="' . esc_attr($node_prefix . '[title]') . '" value="' . esc_attr($node['title']) . '" class="regular-text" required />';
            //echo '<button type="submit" name="delete_node" value="' . esc_attr($node_prefix) . '" style="padding: 2px 6px;">🗑️</button>';
            //echo '<button type="submit" name="add_child" value="' . esc_attr($node_prefix) . '" style="padding: 2px 6px;">➕ Sous-thème</button>';
            echo '</div>';

            if (!empty($node['children_ids'])) {
                $this->render_structure_fields($id, $prefix, $level + 1);
            }

            echo '</li>';
        }

        echo '</ul>';
    }


    private function render_loaded_structure(?int $parent_id = null, int $level = 0): void {
        if (empty($this->mapIdPostLoaded)) {
            echo '<p>Aucun cocon chargé.</p>';
            return;
        }
    
        echo '<ul style="padding-left: ' . (20 * $level) . 'px;">';
    
        foreach ($this->mapIdPostLoaded as $id => $node) {
            if ($node['parent_id'] !== $parent_id) continue;
    
            $title = esc_html($node['title']);
            $link = esc_url($node['link']);
    
            echo "<li><a href=\"$link\" target=\"_blank\">🔗 $title</a>";
    
            $this->render_loaded_structure($id, $level + 1); // récursif
    
            echo '</li>';
        }
    
        echo '</ul>';
    }
   
    
    private function process_structure($keyword) {

        $linker = new CSB_Linker();

        $use_existing_root = isset($_POST['use_existing_root']) && $_POST['use_existing_root'] == '1';
        $forced_link = null;

        if ($use_existing_root && !empty($_POST['existing_root_url'])) {
            $forced_link = sanitize_text_field($_POST['existing_root_url']);
        }

        // 💾 Mise à jour de la map persistée avant publication
        update_option('csb_structure_map', $this->mapIdPost);

        // 📝 Publication de chaque nœud
        foreach ($this->mapIdPost as $id => $info) {
            if ($info['parent_id'] != null || empty($forced_link)) {
                $html =$this->generator->generateContent($id, $this->mapIdPost, $this->nb);
                $image_url = $this->generator->generateImage($info['title'], $keyword);
                $this->publisher->set_featured_image($id, $image_url);
                $html .= $linker->generate_structured_links($this->mapIdPost, $id);
                $this->publisher->fill_and_publish_content($id, $html);
            }
        }


        $published_count = $this->publisher->getPublishedCount();

        echo '<div class="notice notice-success is-dismissible"><p>✅ ' . $published_count . ' article(s) ont été publiés avec succès.</p></div>';
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


    private function createMapEntry(string $title, ?int $parent_id, ?string $forced_link = null, int $level = 0): array {
        $post_id = $this->publisher->createPostDraft($title);

        //  Enregistre les métas ici
        $this->publisher->storeMeta($post_id, $level, $parent_id);

        return [
            'post_id'      => $post_id,
            'title'        => $title,
            'link'         => $forced_link ?? wp_make_link_relative(get_permalink($post_id)),
            'parent_id'    => $parent_id,
            'children_ids' => [],
            'level'        => $level
        ];
    }


    private function convertStructureToMap(string $raw, ?string $forced_link = null): array {
        $lines = explode("\n", trim($raw));
        $stack = [];
        $map = [];

        foreach ($lines as $line) {
            if (trim($line) === '') continue;

            if (preg_match('/^(\s*)-\s*(.+)$/', $line, $matches)) {
                $indent = strlen($matches[1]);
                $title = trim($matches[2]);
                $level = intval($indent / 4);

                $parent_id = $level === 0 ? null : $stack[$level - 1]['post_id'];
                $entry = $this->createMapEntry($title, $parent_id, $level === 0 ? $forced_link : null,$level);
                $post_id = $entry['post_id'];

                $map[$post_id] = $entry;
                $stack[$level] = &$map[$post_id];

                // Ajout dans les enfants du parent
                if ($level > 0) {
                    $map[$parent_id]['children_ids'][] = $post_id;
                }
            }
        }

        return $map;
    }


    private function updateMapFromPost(array &$map, array $posted_structure): void {
    
        foreach ($posted_structure as $post_id => $node_data) {
            if (isset($map[$post_id]) && isset($node_data['title'])) {
                $new_title = sanitize_text_field($node_data['title']);
    
                if ($map[$post_id]['title'] !== $new_title) {
                    $this->publisher->updatePostTitleAndSlug($post_id, $new_title);
                }
    
                $map[$post_id]['title'] = $new_title;
            }
        }
    }
    

    private function handleStructureActionsMap(&$map) {
        // ➕ Ajouter un enfant
        if (isset($_POST['add_child'])) {
            $parent_path = str_replace(['structure[', ']'], '', $_POST['add_child']);
            $segments = explode('[', $parent_path);
            $parent_post_id = intval($segments[0]);

            $this->addChildToNode($map, $parent_post_id);
        }

        // ❌ Supprimer un nœud (et ses enfants récursivement)
        if (isset($_POST['delete_node'])) {
            $path = str_replace(['structure[', ']'], '', $_POST['delete_node']);
            $segments = explode('[', $path);
            $post_id = intval($segments[0]);

            if (isset($map[$post_id])) {
                $this->deleteNode($map, $post_id);
            }
        }
    }


    private function addChildToNode(&$map, int $parent_post_id): void {
        if (!isset($map[$parent_post_id])) return;

        $title = 'Nouveau sous-thème';
        // Récupère le niveau du parent (via postmeta ou map) et incrémente
        $parent_level = $map[$parent_post_id]['level'] ?? 0;
        $child_level = $parent_level + 1;

        // Crée le nouvel article avec le bon niveau
        $entry = $this->createMapEntry($title, $parent_post_id, null, $child_level);

        $new_post_id = $entry['post_id'];
        $map[$new_post_id] = $entry;
        $map[$parent_post_id]['children_ids'][] = $new_post_id;
    }


    private function deleteNode(&$map, $post_id) {
        // Supprimer les enfants récursivement
        foreach ($map[$post_id]['children_ids'] as $child_id) {
            $this->deleteNode($map, $child_id);
        }

        // Supprimer la référence dans le parent
        $parent_id = $map[$post_id]['parent_id'];
        if ($parent_id !== null && isset($map[$parent_id])) {
            $map[$parent_id]['children_ids'] = array_filter(
                $map[$parent_id]['children_ids'],
                fn($id) => $id !== $post_id
            );
        }

        // Supprimer ce nœud
        unset($map[$post_id]);
    }


    private function render_links_to_articles($parent_id = null, $level = 0) {
        echo '<ul style="padding-left: ' . (20 * $level) . 'px;">';

        foreach ($this->mapIdPost as $id => $node) {
            if ($node['parent_id'] === $parent_id) {
                $title = esc_html($node['title'] ?? "Article #$id");
                $url = $node['link'];
                echo "<li><a href='" . esc_url($url) . "' target='_blank'>🔗 $title</a>";

                // 🔥 Appel récursif pour afficher les enfants, **À L'INTÉRIEUR DU LI**
                $this->render_links_to_articles($id, $level + 1);

                echo "</li>"; // Fermeture du LI APRÈS les enfants
            }
        }

        echo '</ul>';
    }


    private function mergeSubCoconIntoMap(array $subMap, ?int $target_parent_id = null): void {
        foreach ($subMap as $sub_id => $node) {
            // Crée un nouveau post pour chaque nœud du sous-cocon
            $new_post_id = $this->publisher->createPostDraft($node['title']);
            $new_level = ($target_parent_id && isset($this->mapIdPost[$target_parent_id]['level']))
                         ? $this->mapIdPost[$target_parent_id]['level'] + 1
                         : $node['level'];
    
            // Enregistre les métas
            $this->publisher->storeMeta($new_post_id, $new_level, $target_parent_id);
    
            // Ajoute dans la map principale
            $this->mapIdPost[$new_post_id] = [
                'post_id'      => $new_post_id,
                'title'        => $node['title'],
                'link'         => wp_make_link_relative(get_permalink($new_post_id)),
                'parent_id'    => $target_parent_id,
                'children_ids' => [],
                'level'        => $new_level
            ];
    
            // Lier au parent dans la map
            if ($target_parent_id !== null) {
                $this->mapIdPost[$target_parent_id]['children_ids'][] = $new_post_id;
            }
    
            // Si ce nœud a des enfants, les traiter récursivement
            if (!empty($node['children_ids'])) {
                // Extraire sous-map des enfants
                $child_subMap = array_intersect_key($subMap, array_flip($node['children_ids']));
                $this->mergeSubCoconIntoMap($child_subMap, $new_post_id);
            }
        }
    
        // Met à jour la version persistée
        update_option('csb_structure_map', $this->mapIdPost);
    }
  
    
    private function render_load_existing_button(int $post_id): void {
        echo '<form method="post" style="display:inline;">';
        echo '<input type="hidden" name="load_existing_cocon" value="' . esc_attr($post_id) . '">';
        echo '<button type="submit" class="button-link">📂 Charger</button>';
        echo '</form>';
    }
  
    
    private function rebuildCoconFromRoot(int $root_post_id): array {
        $map = [];
    
        $this->rebuildCoconRecursive($root_post_id, $map);
    
        return $map;
    }
   
    
    private function rebuildCoconRecursive(int $post_id, array &$map): void {
        $title = get_the_title($post_id);
        $parent_id = intval(get_post_meta($post_id, '_csb_parent_id', true));
        $level = intval(get_post_meta($post_id, '_csb_level', true));
    
        $map[$post_id] = [
            'post_id'      => $post_id,
            'title'        => $title,
            'link'         => wp_make_link_relative(get_permalink($post_id)),
            'parent_id'    => $parent_id ?: null,
            'children_ids' => [],
            'level'        => $level,
        ];
    
        $args = [
            'post_type'      => 'post',
            'post_status'    => ['publish', 'draft'],
            'meta_key'       => '_csb_parent_id',
            'meta_value'     => $post_id,
            'posts_per_page' => -1,
            'orderby'        => 'menu_order',
            'order'          => 'ASC',
        ];
    
        $children = get_posts($args);
    
        foreach ($children as $child) {
            $map[$post_id]['children_ids'][] = $child->ID;
            $this->rebuildCoconRecursive($child->ID, $map);
        }
    }
    
}

