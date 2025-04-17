<?php
if (!defined('ABSPATH')) exit;

class CSB_Admin {
    private $last_tree = [];

    public function __construct() {
        add_action('admin_menu', [$this, 'add_admin_menu']);
        //echo "DOG";
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

    public function render_admin_page() {
        $keyword = isset($_POST['csb_keyword']) ? sanitize_text_field($_POST['csb_keyword']) : '';
        $nd = isset($_POST['csb_nb_nodes']) ? intval($_POST['csb_nb_nodes']) : '';
    
        if (!empty($keyword) && !empty($nd) && isset($_POST['submit'])) {
            $generator = new CSB_Generator();
            $this->last_tree = $generator->generate_structure_array($keyword, $nd);
        }
    
        if (isset($_POST['structure'])) {
            $this->last_tree = $_POST['structure'];
            $this->handle_structure_actions($this->last_tree); 
            
            if (isset($_POST['csb_validate_publish'])) {
                $this->process_structure();
                // echo "<br>";echo "<br>";echo "<br>";
                // print_r($this->last_tree);
                // echo "<br>";echo "<br>";echo "<br>";
                echo '<div class="notice notice-success is-dismissible"><p>✅ Articles publiés avec succès.</p></div>';
            }
        }
    
        echo '<div class="wrap">';
        echo '<h1>Générateur de Cocon Sémantique</h1>';
        $this->render_keyword_form($keyword, $nd);
        $this->render_structure_form($this->last_tree); // ✅ toujours affiché, même après publication
        echo '</div>';
    
        echo '<div style="margin: 1em 0; padding: 1em; border-left: 4px solid #0073aa; background: #f1f1f1;">';
        echo '<p><strong>🔐 Clé API :</strong> <a href="' . admin_url('admin.php?page=csb_settings') . '">Configurer ici</a></p>';
        echo '</div>';
    
        if (!empty($this->last_tree)) {
            echo '<div class="wrap"><h2>🔗 Articles publiés</h2><ul>';
            $this->render_links_to_articles($this->last_tree);
            echo '</ul></div>';
        }
    }
    

    private function render_keyword_form($keyword, $nd) {
        echo '<form method="post">';
        echo '<table class="form-table">';
        echo '<tr><th><label for="csb_keyword">Mot-clé principal</label></th>';
        echo '<td><input type="text" name="csb_keyword" value="' . esc_attr($keyword) . '" class="regular-text" required></td></tr>';
        echo '<tr><th><label for="csb_nb_nodes">Nombre de sous-niveaux</label></th>';
        echo '<td><input type="number" name="csb_nb_nodes" value="' . esc_attr($nd) . '" class="regular-text" required></td></tr>';
        echo '</table>';
        submit_button('Générer la structure', 'primary', 'submit');
        echo '</form>';
    }

    private function render_structure_form($tree, $prefix = 'structure', $level = 0) {
        echo '<form method="post">';
        echo '<fieldset style="padding: 1em; border: 1px solid #ccd0d4; background: #fff; margin-bottom: 1em;">';
        echo '<legend style="font-weight: bold;">Structure générée</legend>';
    
        $this->render_structure_fields($tree, $prefix, $level);
    
        echo '</fieldset>';
        submit_button('Valider et publier', 'primary', 'csb_validate_publish');
        echo '</form>';
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


        // 🔗 Injecter les liens dans chaque nœud de contenu
        $linker = new CSB_Linker();
        $linker->add_links_to_structure($this->last_tree);
        
        $generator = new CSB_Generator();
        // echo "<br><br><br>";
        // print_r("////////////////////////////////BEFORE///////////////////////////");
        // echo "<br>";
        // echo '<pre>';
        // print_r($this->last_tree);
        // echo '</pre>';
        $generator->generate_full_content($this->last_tree);
        echo "<br><br><br>";
        print_r("////////////////////////////////AFTER///////////////////////////");
        echo "<br>";
        echo '<pre>';
        print_r($this->last_tree);
        echo '</pre>';
        $publisher = new CSB_Publisher();
        $publisher->publish_structure($this->last_tree);
    }

    private function generate_slug($title) {
        $slug = strtolower($title);
        $slug = remove_accents($slug);
        $slug = preg_replace('/[^a-z0-9]+/', '-', $slug);
        return trim($slug, '-');
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
       

    private function render_links_to_articles($tree){
        foreach ($tree as $slug => $node) {
            if (!empty($node['post_id'])) {
                $url = get_permalink($node['post_id']);
                $title = esc_html($node['title']);
                echo "<li><a href='" . esc_url($url) . "' target='_blank'>🔗 $title</a></li>";
            }
    
            if (!empty($node['children'])) {
                $this->render_links_to_articles($node['children']); // récursif
            }
        }
    
        echo '</ul></div>';
    }
      
}
