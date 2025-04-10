<?php
if (!defined('ABSPATH')) exit;

class CSB_Admin {
    private $last_tree = [];

    public function __construct() {
        add_action('admin_menu', [$this, 'add_admin_menu']);
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

    public function render_admin_page() {
        $keyword = isset($_POST['csb_keyword']) ? sanitize_text_field($_POST['csb_keyword']) : '';
        $nd = isset($_POST['csb_nb_nodes']) ? intval($_POST['csb_nb_nodes']) : '';

        if (!empty($keyword) && !empty($nd) && isset($_POST['submit'])) {
            $generator = new CSB_Generator();
            $this->last_tree = $generator->generate_structure_array($keyword, $nd);
        }

        if (isset($_POST['csb_validate_publish']) && isset($_POST['structure'])) {
            $this->last_tree = $_POST['structure'];
            $this->process_structure($this->last_tree);
            echo '<div class="notice notice-success is-dismissible"><p>✅ Articles publiés avec succès.</p></div>';
        }

        echo '<div class="wrap">';
        echo '<h1>Générateur de Cocon Sémantique</h1>';
        $this->render_keyword_form($keyword, $nd);
        $this->render_structure_form($this->last_tree);
        echo '</div>';
        echo '<div style="margin: 1em 0; padding: 1em; border-left: 4px solid #0073aa; background: #f1f1f1;">';
        echo '<p><strong>🔐 Clé API :</strong> <a href="' . admin_url('admin.php?page=csb_settings') . '">Configurer ici</a></p>';
        echo '</div>';
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
        $this->render_structure_fields($tree, $prefix, $level);
        submit_button('Valider et publier', 'primary', 'csb_validate_publish');
        echo '</form>';
    }

    private function render_structure_fields($tree, $prefix, $level) {
        echo '<ul style="list-style-type: none; margin-left: 0;">';
        foreach ($tree as $index => $node) {
            $node_prefix = $prefix . "[$index]";
            $indent = str_repeat('&nbsp;', $level * 4);
            echo '<li style="margin-bottom: 8px;">';
            echo $indent . '- <input type="text" name="' . esc_attr($node_prefix . '[title]') . '" value="' . esc_attr($node['title']) . '" class="regular-text" required />';
            echo ' <button type="submit" name="delete_node" value="' . esc_attr($node_prefix) . '">🗑️</button> ';
            echo '<button type="submit" name="add_child" value="' . esc_attr($node_prefix) . '">➕ Sous-thème</button>';
            if (!empty($node['children'])) {
                $this->render_structure_fields($node['children'], $node_prefix . '[children]', $level + 15);
            }
            echo '</li>';
        }
        echo '</ul>';
    }

    private function process_structure($tree) {
        $generator = new CSB_Generator();
        $generator->generate_full_content($tree);
        $publisher = new CSB_Publisher();
        $publisher->publish_structure($tree);
    }

    private function generate_slug($title) {
        $slug = strtolower($title);
        $slug = remove_accents($slug);
        $slug = preg_replace('/[^a-z0-9]+/', '-', $slug);
        return trim($slug, '-');
    }
}
