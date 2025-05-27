<?php

if (!defined('ABSPATH')) exit;

class CSB_Admin_Controller {
    private StructureManager $structureManager;
    private NodeProcessor $nodeProcessor;
    private AdminRenderer $adminRenderer;
    private string $jsFile;
    private string $cssFile;
    private int $nb = 3;

    public function __construct(
        StructureManager $structureManager,
        NodeProcessor $nodeProcessor,
        AdminRenderer $adminRenderer,
        string $jsFile,
        string $cssFile
    ) {
        $this->structureManager = $structureManager;
        $this->nodeProcessor = $nodeProcessor;
        $this->adminRenderer = $adminRenderer;
        $this->jsFile = $jsFile;
        $this->cssFile = $cssFile;

        add_action('admin_menu', [$this, 'addAdminMenu']);
        add_action('admin_enqueue_scripts', [$this, 'enqueueAssets']);
        add_action('wp_ajax_csb_process_node', [$this, 'ajaxProcessNode']);
    }

    public function addAdminMenu(): void {
        add_menu_page(
            'Cocon Sémantique',
            'Cocon Sémantique',
            'manage_options',
            'csb_admin',
            [$this, 'renderAdminPage'],
            'dashicons-networking',
            30
        );
    }

    public function enqueueAssets(): void {
        wp_enqueue_script(
            'csb-admin',
            plugin_dir_url(__DIR__) . $this->jsFile,
            ['jquery'],
            filemtime(plugin_dir_path(__DIR__) . $this->jsFile),
            true
        );

        wp_enqueue_style(
            'csb-admin-style',
            plugin_dir_url(__DIR__) . $this->cssFile,
            [],
            filemtime(plugin_dir_path(__DIR__) . $this->cssFile)
        );

        wp_localize_script('csb-admin', 'csbData', [
            'nonce' => wp_create_nonce('csb_nonce'),
            'ajaxurl' => admin_url('admin-ajax.php')
        ]);
    }

    public function renderAdminPage(): void {
        $keyword =$this->capitalizeEachWord(isset($_POST['csb_keyword']) ? sanitize_text_field($_POST['csb_keyword']) : '');
        $this->nb = isset($_POST['csb_nb_nodes']) ? intval($_POST['csb_nb_nodes']) : 3;
        $use_existing_root = isset($_POST['use_existing_root']) ? 1 : 0;
        $existing_root_url = isset($_POST['existing_root_url']) ? $_POST['existing_root_url'] : '';
        $existing_root_url = $this->sanitizeToRelativeUrl($existing_root_url);


        if (empty($this->mapIdPost)) {
            $this->mapIdPost = get_option('csb_structure_map', []);
        }

        // Traitement bouton Nettoyer
        if (isset($_POST['csb_clear_structure'])) {
            foreach ($this->mapIdPost as $node){
                $post_id = $node['post_id'];
                // Ne supprimer que les articles WordPress valides
                if ($post_id > 0 && get_post($post_id)) {
                    $this->publisher->deletePost($post_id);
                }
            }
                
            
            $this->mapIdPost = [];
            update_option('csb_structure_map', []);
            echo '<div class="notice notice-success is-dismissible"><p>🧹 Structure nettoyée et brouillons supprimés.</p></div>';
        }




        if (isset($_POST['load_existing_cocon'])) {
            $root_post_id = intval($_POST['load_existing_cocon']);
            $this->publisher->markAsRoot($root_post_id);
            $this->mapIdPostLoaded = $this->rebuildCoconFromRoot($root_post_id);
            update_option('csb_structure_map', $this->mapIdPost);
        }

        if((!empty($keyword) && !empty($this->nb) && isset($_POST['submit']))||(isset($_POST['structure']))){
            if (!empty($keyword) && !empty($this->nb) && isset($_POST['submit'])) {

                //$this->generator->setKeyword($keyword);
                $product = isset($_POST['csb_product']) ? sanitize_text_field($_POST['csb_product']) : null;
                $demographic = isset($_POST['csb_demographic']) ? sanitize_text_field($_POST['csb_demographic']) : null;

                $context_data = [];
                if (!empty($product)) $context_data['produit'] = $product;
                if (!empty($demographic)) $context_data['public'] = $demographic;
                error_log(print_r($context));
                $context = new PromptContext($context_data);
                $raw = $this->generator->generateStructure($keyword, self::$depth, $this->nb, $context, $this->debugModStructure);
                $this->mapIdPost = $this->convertStructureToMap($raw, $use_existing_root ? $existing_root_url : null);

                // echo '<pre style="white-space: pre-wrap; background:#f7f7f7; padding:1em; border:1px solid #ccc;">';
                // echo esc_html($raw);
                // echo '</pre>';
                
                update_option('csb_structure_map', $this->mapIdPost);
            }

            if (isset($_POST['structure'])) {
                $this->handleStructureActionsMap($this->mapIdPost);
                 // Synchronise les modifications utilisateur (titres)
                if (isset($_POST['structure']) && is_array($_POST['structure'])) {
                    $this->updateMapFromPost($this->mapIdPost, $_POST['structure']);
                    update_option('csb_structure_map', $this->mapIdPost);
                }

            }
        }


        if ($use_existing_root && !empty($existing_root_url)) {
            $first_node = reset($this->mapIdPost);
            if ($first_node && isset($first_node['post_id']) && $first_node['post_id'] > 0) {
                $this->publisher->markAsRoot($first_node['post_id']);
            }
        }


        // echo '<div id="csb-token-tracker">';
        // echo '<strong>🧠 Tokens utilisés :</strong> <span id="csb-token-count">0</span>';
        // echo '</div>';



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
            $this->checkDegradedModeNotice(); 
            $this->renderKeywordForm($keyword, $this->nb);
            $this->renderStructureForm('structure', 0, $use_existing_root, $existing_root_url);
        }

        echo '</div>';

        echo '<div class="csb-api-settings">';
        echo '<p><strong>🔐 Clé API :</strong> <a href="' . admin_url('admin.php?page=csb_settings') . '">Configurer ici</a></p>';
        echo '</div>';



        $roots = $this->publisher->getAllRootNodesFromMeta();
        echo '<h2>🌳 Racines des cocons existants</h2><ul>';
        foreach ($roots as $root) {
            echo '<li><strong>' . esc_html($root['title']) . '</strong> - ';
            $this->renderLoadExistingButton($root['post_id']);
            echo '</li>';
        }
        echo '</ul>';


        if (!empty($this->mapIdPostLoaded)) {
            echo '<h2>📂 Structure du cocon chargé</h2>';
            $this->renderLoadedStructure();
        }
    }

    public function ajaxProcessNode() {
        $result = null;

        if (!current_user_can('manage_options') || !check_ajax_referer('csb_nonce', 'nonce', false)){
            $result = [
                'success' => false,
                'data' => ['message' => 'Non autorisé'],
                'code' => 403
            ];
        } 
        else{
            session_write_close(); 

            if (empty($this->mapIdPost)) {
                $this->mapIdPost = get_option('csb_structure_map', []);
            }

            $structure = $_POST['structure'] ?? [];
            $this->updateMapFromPost($this->mapIdPost, $structure);
            // $stringmMapIdPost="";
            // foreach ($array as $key => $value) {
            //     $stringmMapIdPost .= "$key = $value; ";
            // }
            // error_log("$stringmMapIdPost");

            $post_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;
            $nb = $this->nb;

            if (!$post_id || !isset($this->mapIdPost[$post_id])) {
                $result = [
                    'success' => false,
                    'data' => ['message' => 'Post ID invalide ou introuvable'],
                    'code' => 400
                ];
            } 
            else{
                try{
                    $keyword = reset($this->mapIdPost)['title'] ?? '';
                    $product = !empty($_POST['csb_product']) ? sanitize_text_field($_POST['csb_product']) : null;
                    $demographic = !empty($_POST['csb_demographic']) ? sanitize_text_field($_POST['csb_demographic']) : null;

                    $this->processNode($post_id, $this->mapIdPost, $nb, $keyword, $product, $demographic);

                    update_option('csb_structure_map', $this->mapIdPost);

                    $result = [
                        'success' => true,
                        'data' => [
                            'message' => 'Nœud généré avec succès',
                            'link' => $this->mapIdPost[$post_id]['link'] ?? '',
                            'tokens' => $this->generator->getTokensUsed()
                        ],
                        'code' => 200
                    ];
                } 
                catch (\Throwable $e) {
                    //error_log("❌ Erreur lors de la génération du nœud $post_id : " . $e->getMessage());

                    $result = [
                        'success' => false,
                        'data' => [
                            'message' => '❌ Une erreur est survenue lors de la génération.',
                            'details' => $e->getMessage()
                        ],
                        'code' => 500
                    ];
                }
            }
        }

        
        if ($result['success']){
            wp_send_json_success($result['data'], $result['code']);
        } 
        else{
            wp_send_json_error($result['data'], $result['code']);
        }
    }
}