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
        $nd = isset($_POST['nd_keyword']) ? intval($_POST['nd_keyword']) : '';
        $structure = '';

        // Si l'utilisateur clique sur "Générer"
        if (!empty($keyword) && !empty($nd) && isset($_POST['submit'])) {
            $generator = new CSB_Generator();
            $structure = $generator->generate_structure($keyword, $nd);
            //var_dump($structure);
        }

        // Si l'utilisateur clique sur "Valider et publier"
        if (isset($_POST['csb_validate_publish'])) {
            $this->handle_structure_submission($_POST['csb_structure']);
        }
        
        ?>
        <div class="wrap">
            <h1>Générateur de Cocon Sémantique1</h1>
            
            <?php $this->render_keyword_form($keyword, $nd, $structure);?>
        </div>

        <div style="margin: 1em 0; padding: 1em; border-left: 4px solid #0073aa; background: #f1f1f1;">
            <p><strong>🔐 Clé API :</strong>
                <a href="<?php echo admin_url('admin.php?page=csb_settings'); ?>">Configurer ici</a></p>
        </div>

        <?php if (!empty($structure)): ?>
            <h2>📂 Structure actuelle</h2>
            <pre style="background: #fff; padding: 1em; border: 1px solid #ccc;"><?php $this->render_structure_with_content($this->last_tree); ?></pre>
        <?php endif;
    }

    private function save_structure($raw_structure) {
        $clean = sanitize_textarea_field($raw_structure);
        update_option('csb_last_structure', $clean);
    }

    private function get_saved_structure() {
        return get_option('csb_last_structure', '');
    }

    private function parse_structure_to_array($text) {
        $lines = explode("\n", trim($text));
        $stack = [];
        $root = [];

        foreach ($lines as $line) {
            if (preg_match('/^\s*[-\x{2022}]\s*(.+)$/u', $line, $matches)) {
                $title = trim($matches[1]);
                $level = (strlen($line) - strlen(ltrim($line))) / 2;
                $node = ['title' => $title, 'children' => []];
                $node['slug'] = $this->generate_slug($node['title']);
                

                if ($level === 0) {
                    $root[] = $node;
                    $stack = [&$root[array_key_last($root)]];
                } else {
                    $parent = &$stack[$level - 1]['children'];
                    $parent[] = $node;
                    $stack[$level] = &$parent[array_key_last($parent)];
                }
            }
        }
        //var_dump($text);

        return $root;
    }

    private function render_structure_html($tree) {
        echo '<ul>';
        foreach ($tree as $node) {
            echo '<li>' . esc_html($node['title']);
            if (!empty($node['children'])) {
                $this->render_structure_html($node['children']);
            }
            echo '</li>';
        }
        echo '</ul>';
    }

    private function render_keyword_form($keyword, $nd, $structure) {
        ?>
        <form method="post" action="">
            <table class="form-table">
                <tr>
                    <th scope="row"><label for="csb_keyword">Mot-clé principal</label></th>
                    <td>
                        <input name="csb_keyword" type="text" id="csb_keyword" class="regular-text" value="<?php echo esc_attr($keyword); ?>" required>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="csb_nb_nodes">Nombre de sous-niveaux</label></th>
                    <td>
                        <input name="nd_keyword" type="number" id="csb_nb_nodes" class="regular-text" value="<?php echo esc_attr($nd); ?>" required>
                    </td>
                </tr>
                <?php $this->render_structure_textarea_html($structure); ?>
            </table>
    
            <?php
            submit_button('Générer la structure', 'primary', 'submit');
            submit_button('Valider et publier', 'secondary', 'csb_validate_publish');
            ?>
        </form>
        <?php
    }

    private function render_structure_textarea_html($structure) {
        if (empty($structure)) return;
        //echo 'DOG';

        //$structure_clean = preg_replace('/\[IMAGE:\s*.*?\]/', '', $structure);

        echo '<tr>';
        echo '  <th scope="row">Structure proposée</th>';
        echo '  <td>';
        echo '      <textarea name="csb_structure" rows="10" class="large-text">';
        echo esc_textarea($structure);
        echo '      </textarea>';
        echo '  </td>';
        //echo '<td>DOG</td>';
        echo '</tr>';
        
    }

    private function handle_structure_submission($structureText){
        $structureText = sanitize_textarea_field($structureText);
        //$structureText = trim(wp_unslash($structureText));

        $this->save_structure($structureText);
        
        echo "<p>$structureText</p>";  

        $tree = $this->parse_structure_to_array($structureText);
        echo "<p>"; 
        var_dump($tree);
        echo "</p>"; 
        // Génération de contenu avec contexte global
        //$generator = new CSB_Generator();
       // $generator->generate_full_content($tree);


        $this->last_tree = $tree;
    
    
        // Publication automatique des articles
        //$publisher = new CSB_Publisher();
        //$publisher->publish_structure($tree);
    
        echo '<div class="notice notice-success is-dismissible"><p>✅ Articles publiés avec succès.</p></div>';
    
        $structure = $structureText;
    }

    private function get_post_by_title($title) {
        $query = new WP_Query([
            'post_type'      => 'post',
            'post_status'    => 'publish',
            'title'          => $title,
            'posts_per_page' => 1,
        ]);
    
        return $query->have_posts() ? $query->posts[0] : null;
    }
    
    
    private function render_structure_with_content($tree) {
        echo '<ul style="list-style-type: disc;">';
        //var_dump($tree);
        foreach ($tree as $node) {
            echo '<li>';
            echo '<strong>' . esc_html($node['title']) . '</strong>';
            $post = $this->get_post_by_title($node['title']);
            if ($post) {
                $permalink = get_permalink($post);
                echo '<br><a href="' . esc_url($permalink) . '" target="_blank">🔗 Voir l’article</a>';
            }
            if (!empty($node['children'])) {
                $this->render_structure_with_content($node['children']);
            }
    
            echo '</li>';
        }
        echo '</ul>';
    }

    private function generate_slug($title) {
         $slug = strtolower($title);
         $slug = remove_accents($slug); 
         $slug = preg_replace('/[^a-z0-9]+/', '-', $slug); 
         $slug = trim($slug, '-'); 
         return $slug;
    }
    
        
    
}
