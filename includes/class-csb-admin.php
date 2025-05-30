<?php
if (!defined('ABSPATH')) exit;
//require_once __DIR__ . '/prompt/class-prompt-context.php';

class CSB_Admin {
    private int $nb;
    private $mapIdPost=[];
    private $mapIdPostLoaded=[];
    private GeneratorInterface $generator;
    private PromptProviderInterface $prompter;
    private $publisher;
    private $linker; 
    private static int $depth=4;
    private string $jsFile;
    private string $cssFile;
    private string $defaultImage;
    //private int $breadth;




    // private static $minExecutionTime=600;
    // private static $minInputTime=60;
    // private static $minSize=32;


    private static $minExecutionTime=20;
    private static $minInputTime=0;
    private static $minSize=0;

    private static $minExecutionTimeForSafe=60;


    private bool $debugModStructure=false;
    private bool $debugModContent=false;
    private bool $debugModImage=false;


    public function __construct(GeneratorInterface $generator,PromptProviderInterface $prompter,string $jsFile,string $cssFile,string $defaultImage) {
        add_action('admin_menu', [$this, 'add_admin_menu']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets']);
        add_action('wp_ajax_csb_process_node', [$this, 'ajaxProcessNode']); 
        add_action('wp_ajax_csb_regenerate_image', [$this, 'ajaxRegenerateImage']);

        $this->nb=3;
        $this->jsFile=$jsFile;
        $this->cssFile=$cssFile;
        $this->prompter=$prompter;
        $this->defaultImage=$defaultImage;

        //echo "DOG";
        // add_action('admin_init', [$this, 'maybe_delete_author_posts']);
        //$this->generator= new CSB_Generator(new CSB_Prompts());
        $this->generator= $generator;
        $this->publisher=  new CSB_Publisher();
        $this->linker = new CSB_Linker();
    }


    private static function checkServerRequirements(): array {
        $errors = [];
        $maxExecutionTime = (int) ini_get('max_execution_time');
        $maxInputTime = (int) ini_get('max_input_time');

        // Comparaisons
        if (($maxExecutionTime < self::$minExecutionTime)&&($maxExecutionTime>0)) {
            $errors[] = "⏱️ `max_execution_time` est trop bas : $maxExecutionTime (minimum requis : " . self::$minExecutionTime . ")";
        }

        if (($maxInputTime < self::$minInputTime)&&($maxInputTime>0)) {
            $errors[] = "📥 `max_input_time` est trop bas : $maxInputTime (minimum requis : " . self::$minInputTime . ")";
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


    private function capitalizeEachWord($text) {
        $text = strtolower($text);
        $text = preg_replace('#[/\\\\]+#', ' ', $text);
        $text = ucwords($text);
        return $text;
    }


    private function checkDegradedModeNotice(): void {
        $maxTime = (int) ini_get('max_execution_time');

        if ($maxTime < self::$minExecutionTimeForSafe) {
            echo '<div class="notice notice-warning is-dismissible">';
            echo '<p>⚠️ <strong>Mode dégradé activé</strong> : la génération des articles utilise un mode rapide (moins précis) car la configuration de votre serveur limite <code>max_execution_time</code> à <strong>' . esc_html($maxTime) . 's</strong>.';
            echo ' Pour un fonctionnement optimal, augmentez cette valeur à au moins <strong>' . self::$minExecutionTimeForSafe . 's</strong>.</p>';
            echo '</div>';
        }
    }



    public function render_admin_page() {

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
                //error_log(print_r($context));
                $context = new PromptContext($context_data);
                $this->processStructure(
                    $this->mapIdPost,
                    $keyword,
                    $context,
                    $use_existing_root,
                    $existing_root_url
                );


                foreach ($this->mapIdPost as $post_id => $node) {
                    $this->mapIdPost[$post_id]["imageDescription"]=$this->processImageDescription($post_id, $this->mapIdPost, $keyword, $context);
                    //$this->processImageDescription($post_id, $this->mapIdPost, $keyword, $context);
                }


                // echo '<pre style="white-space: pre-wrap; background:#f7f7f7; padding:1em; border:1px solid #ccc;">';
                // print_r($this->mapIdPost);
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


    private function renderStructureForm($prefix = 'structure', $level = 0, $use_existing_root = 0, $existing_root_url = ''){
        echo '<form method="post">';
        echo '<fieldset class="csb-fieldset">';
        echo '<legend>Structure générée</legend>';

        // Affichage à partir de la racine (parent_id null)
        $this->renderStructureFields(null, $prefix, 0, $use_existing_root==0);

        echo '</fieldset>';

        if ($use_existing_root) {
            echo '<input type="hidden" name="use_existing_root" value="1" />';
        }
        if (!empty($existing_root_url)) {
            echo '<input type="hidden" name="existing_root_url" value="' . esc_attr($existing_root_url) . '" />';
        }

        echo '<div class="csb-structure-actions">';
        echo '<button type="button" id="csb-generate-all" class="button button-primary">🚀 Tout générer</button> ';
        echo '<button type="submit" name="csb_stop_generation" class="button">🛑 Stopper la génération</button> ';
        echo '<button type="submit" name="csb_clear_structure" class="button button-danger" onclick="return confirm(\'Supprimer la structure et tous les brouillons ?\');">Supprimer le cocon</button>';
        echo '</div>';

        echo '</form>';
    }


    private function renderKeywordForm($keyword, $nb) {
        $use_existing_root = isset($_POST['use_existing_root']) ? 1 : 0;

        $original_url = isset($_POST['existing_root_url']) ? sanitize_text_field($_POST['existing_root_url']) : '';
        $existing_root_url = $this->sanitizeToRelativeUrl($original_url); // <-- Conversion ici
        
        $product = isset($_POST['csb_product']) ? sanitize_text_field($_POST['csb_product']) : '';
        $demographic = isset($_POST['csb_demographic']) ? sanitize_text_field($_POST['csb_demographic']) : '';

        
        echo '<form method="post">';
        echo '<table class="form-table">';

        // Champ mot-clé
        echo '<tr><th><label for="csb_keyword">Mots Clés principaux</label></th>';
        echo '<td><input type="text" id="csb_keyword" name="csb_keyword" value="' . esc_attr($keyword) . '" class="regular-text" required></td></tr>';

        // Champ nombre de niveaux
        echo '<tr><th><label for="csb_nb_nodes">Nombre de sous-niveaux</label></th>';
        echo '<td><input type="number" id="csb_nb_nodes" name="csb_nb_nodes" value="' . esc_attr($nb) . '" min="1" max="5" required class="regular-text" /></td></tr>';

        // Case à cocher
        echo '<tr><th><label for="use_existing_root">Utiliser un article racine existant</label></th>';
        echo '<td><input type="checkbox" id="use_existing_root" name="use_existing_root" value="1" ' . checked(1, $use_existing_root, false) . '></td></tr>';

        // Champ URL
        echo '<tr><th><label for="existing_root_url">URL de l’article racine</label></th>';
        echo '<td><input type="text" id="existing_root_url" name="existing_root_url" value="' . esc_attr($existing_root_url) . '" class="regular-text">';
        echo '<p class="description">Uniquement une URL relative (ex: /mon-article)</p>';

        if (!empty($original_url) && str_starts_with($original_url, 'http')) {
            echo '<p>❗ L’URL absolue a été automatiquement convertie en lien relatif';
        }

        echo '</td></tr>';

        // // Champ Activité
        // echo '<tr><th><label for="csb_activity">Activité</label></th>';
        // echo '<td><input type="text" id="csb_activity" name="csb_activity" value="' . esc_attr($activity) . '" class="regular-text">';
        // echo '<p class="description">Ex : artisan, e-commerçant, coach, etc.</p></td></tr>';

        // Champ Produit
        echo '<tr><th><label for="csb_product">Produit vendu</label></th>';
        echo '<td><input type="text" id="csb_product" name="csb_product" value="' . esc_attr($product) . '" class="regular-text">';
        echo '<p class="description">Ex : formations, bijoux, vêtements, accompagnement, etc.</p></td></tr>';


        // Champ Démographique
        echo '<tr><th><label for="csb_demographic">Démographique</label></th>';
        echo '<td><input type="text" id="csb_demographic" name="csb_demographic" value="' . esc_attr($demographic) . '" class="regular-text">';
        echo '<p class="description">Ex : parents, retraités, étudiants, etc.</p></td></tr>';

        echo '</table>';
        submit_button('Générer la structure', 'primary', 'submit');
        echo '</form>';
    }


    private function renderStructureFields($parent_id, string $prefix, int $level, bool $generation = true) {
        echo '<ul class="csb-structure-list level-' . $level . '" style="--level: ' . $level . ';">';

        foreach ($this->mapIdPost as $id => $node) {
            if ($node['parent_id'] !== $parent_id) continue;

            $isLeaf = empty($node['children_ids']);
            $isVirtual = $id < 0;
            $node_prefix = $prefix . "[" . $id . "]";
            $readonly = $generation ? '' : 'readonly';

            echo '<li class="csb-node-item">';
            echo '<div class="csb-node-controls">';
            echo '<span class="csb-node-indent">-</span>';

            echo '<input type="text" name="' . esc_attr($node_prefix . '[title]') . '" value="' . esc_attr($node['title']) . '" class="regular-text" ' . $readonly . ' required />';

            if ($isLeaf) {
                //echo ' <em>(feuille)</em>';
            }


            if ($generation) {
                echo '<button type="button" class="button csb-generate-node" data-post-id="' . esc_attr($id) . '">⚙️ Générer </button>';
                echo '<button type="button" class="button csb-regenerate-image" data-post-id="' . esc_attr($id) . '">🎨 Régénérer l’image</button>';
            }

            echo '<span class="csb-node-status" data-post-id="' . esc_attr($id) . '"></span>';
            echo '</div>';

            // Récursion
            $this->renderStructureFields($id, $prefix, $level + 1, $generation);
            echo '</li>';
        }

        echo '</ul>';
    }


    private function renderLoadedStructure(?int $parent_id = null, int $level = 0): void {
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
    
            $this->renderLoadedStructure($id, $level + 1); // récursif
    
            echo '</li>';
        }
    
        echo '</ul>';
    }


    public function ajaxRegenerateImage() {
        $result = [
            'success' => false,
            'data' => ['message' => 'Erreur inconnue'],
            'code' => 500
        ];

        if (!current_user_can('manage_options') || !check_ajax_referer('csb_nonce', 'nonce', false)) {
            $result['data']['message'] = 'Non autorisé';
            $result['code'] = 403;
        } else {
            $post_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;
            $keyword = isset($_POST['csb_keyword']) ? sanitize_text_field($_POST['csb_keyword']) : '';
            $product = isset($_POST['csb_product']) ? sanitize_text_field($_POST['csb_product']) : null;
            $demographic = isset($_POST['csb_demographic']) ? sanitize_text_field($_POST['csb_demographic']) : null;

            $context_data = ['keyword' => $keyword];
            if (!empty($product)) $context_data['product'] = $product;
            if (!empty($demographic)) $context_data['demographic'] = $demographic;
            $context = new PromptContext($context_data);

            $this->mapIdPost = get_option('csb_structure_map', []);
            if (!isset($this->mapIdPost[$post_id])) {
                $result['data']['message'] = 'Nœud introuvable';
                $result['code'] = 400;
            } else {
                $title = $this->mapIdPost[$post_id]['title'];

                try {
                    $description = $this->generator->generateTexte($title, false, '', $this->prompter->image($keyword, $title, $context), $this->debugModContent);
                    $this->mapIdPost[$post_id]['imageDescription'] = $description;

                    $image_url = $this->generator->generateImage($title, $description, $context, $this->defaultImage, false);
                    $this->publisher->setFeaturedImage($post_id, $image_url);
                    update_option('csb_structure_map', $this->mapIdPost);

                    $result['success'] = true;
                    $result['data'] = ['message' => 'Image régénérée avec succès'];
                    $result['code'] = 200;
                } catch (\Throwable $e) {
                    $result['data'] = ['message' => 'Erreur lors de la régénération', 'details' => $e->getMessage()];
                    $result['code'] = 500;
                }
            }
        }

        if ($result['success']) {
            wp_send_json_success($result['data'], $result['code']);
        } else {
            wp_send_json_error($result['data'], $result['code']);
        }
    }



    public function ajaxProcessNode() {
        //error_log("⚙️ AJAX reçu : " . json_encode($_POST));

        $result = [
            'success' => false,
            'data' => ['message' => 'Erreur inconnue'],
            'code' => 500
        ];

        $post_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;

        if (empty($this->mapIdPost)) {
            $this->mapIdPost = get_option('csb_structure_map', []);
        }

        $tempTitle = $this->mapIdPost[$post_id]['title'] ?? '(titre inconnu)';
        $startTime = microtime(true); 
        error_log("🟡 [START] ajaxProcessNode de \"$tempTitle\" lancé à " . date('H:i:s'));


        $hasPermission = current_user_can('manage_options');
        $nonceValid = check_ajax_referer('csb_nonce', 'nonce', false);

        if (!$hasPermission) {
            error_log("⛔ Accès refusé : utilisateur sans permission");
            $result['data']['message'] = 'Non autorisé';
            $result['code'] = 403;
        } 
        else {
            if (!$nonceValid) {
                error_log("⛔ Nonce invalide");
                $result['data']['message'] = 'Nonce invalide';
                $result['code'] = 403;
            } 
            else {
                if (empty($_POST)) {
                    error_log("❗ Requête AJAX vide");
                    $result['data']['message'] = 'Requête vide';
                    $result['code'] = 400;
                } 
                else {
                    session_write_close();

                    if (empty($this->mapIdPost)) {
                        $this->mapIdPost = get_option('csb_structure_map', []);
                        //error_log("📦 mapIdPost chargé : " . implode(',', array_keys($this->mapIdPost)));
                    }

                    $structure = $_POST['structure'] ?? [];
                    if (!empty($structure)) {
                        $this->updateMapFromPost($this->mapIdPost, $structure);
                        //error_log("✍️ Structure mise à jour");
                    } 
                    else {
                        error_log("⚠️ Aucune structure transmise");
                    }

                    $post_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;

                    if ($post_id === 0) {
                        error_log("❌ post_id manquant ou invalide");
                        $result['data']['message'] = 'post_id manquant ou invalide';
                        $result['code'] = 400;
                    } 
                    else {
                        if (!isset($this->mapIdPost[$post_id])) {
                            error_log("❌ post_id $post_id introuvable dans la map");
                            $result['data']['message'] = 'post_id introuvable dans la structure';
                            $result['code'] = 400;
                        } 
                        else {
                            $nb = $this->nb;
                            $keyword = isset($_POST['csb_keyword']) ? sanitize_text_field($_POST['csb_keyword']) : '';
                            $product = !empty($_POST['csb_product']) ? sanitize_text_field($_POST['csb_product']) : null;
                            $demographic = !empty($_POST['csb_demographic']) ? sanitize_text_field($_POST['csb_demographic']) : null;

                            try {
                                //error_log("🚀 Lancement de processNode pour post_id: $post_id");
                                $this->processNode($post_id, $this->mapIdPost, $nb, $keyword, $product, $demographic);

                                update_option('csb_structure_map', $this->mapIdPost);

                                $result['success'] = true;
                                $result['data'] = [
                                    'message' => 'Nœud généré avec succès',
                                    'link' => $this->mapIdPost[$post_id]['link'] ?? '',
                                    'tokens' => $this->generator->getTokensUsed()
                                ];
                                $result['code'] = 200;
                            } 
                            catch (\Throwable $e) {
                                error_log("❌ Erreur dans processNode($post_id) : " . $e->getMessage());
                                $result['data'] = [
                                    'message' => 'Une erreur est survenue lors de la génération.',
                                    'details' => $e->getMessage()
                                ];
                                $result['code'] = 500;
                            }
                        }
                    }
                }
            }
        }

        $endTime = microtime(true);
        $duration = number_format($endTime - $startTime, 3);
        error_log("✅ [END] ajaxProcessNode terminé à " . date('H:i:s') . " (⏱️ $duration sec)");


        // ✅ Unique retour à la fin
        if ($result['success']) {
            wp_send_json_success($result['data'], $result['code']);
        } else {
            wp_send_json_error($result['data'], $result['code']);
        }
    }



    public function enqueue_admin_assets() {
        wp_enqueue_script(
            'csb-admin',
            plugin_dir_url(__DIR__) . $this->jsFile,
            ['jquery'],
            filemtime(plugin_dir_path(__DIR__) . $this->jsFile),
            true
        );

        // CSS
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


    private function toBulletArchitecture(array $map, int $current_id = null, int $indent = 0): string {
        $out = '';

        foreach ($map as $id => $node) {
            if ($node['parent_id'] === $current_id) {
                $out .= str_repeat('    ', $indent) . "- {$node['title']}\n";

                if (!empty($node['children_ids'])) {
                    $out .= $this->toBulletArchitecture($map, $id, $indent + 1);
                }
            }
        }
        return $out;
    }


    private function slugify(string $text): string {
        // 1. Translittération : convertit les accents et caractères spéciaux
        $text = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text);

        // 2. Mise en minuscule
        $text = strtolower($text);

        // 3. Remplace les caractères non alphanumériques par des tirets
        $text = preg_replace('/[^a-z0-9]+/', '-', $text);

        // 4. Supprime les tirets en début ou fin
        return trim($text, '-');
    }


    private function processStructure(array &$map,string $keyword,PromptContext $context,bool $use_existing_root=false,string $existing_root_url=null){
        $prompt=$this->prompter->structure($keyword,3,3,$context);
        
        $raw = $this->generator->generateTexte($keyword, $this->debugModContent, "", $prompt, true); 
        //error_log($raw);
        $this->mapIdPost = $this->convertStructureToMap($raw, $keyword, $use_existing_root ? $existing_root_url : null);
        //print_r($this->mapIdPost);
    }


    private function processImageDescription(int $post_id, array &$map, string $keyword, PromptContext $context): string {
        if ($this->debugModImage) 
            return ''; 
        else{
            $title = $map[$post_id]['title'];

            // 1. Générer le prompt d'image
            $prompt = $this->prompter->image($keyword, $title, $context);

            // 2. Générer la description via GPT
            $description = $this->generator->generateTexte($title, false, '', $prompt, $this->debugModContent);

            return $description ?: '';
        }
    }





    private function processNode(
        int $post_id,
        array &$map,
        int $nb,
        string $keyword,
        ?string $product = null,
        ?string $demographic = null): void {
        $maxTime = (int) ini_get('max_execution_time');

        if (isset($map[$post_id])) {
            try {
                // Construction du PromptContext avec uniquement les champs utiles
                $context_data = ['keyword' => $keyword];
                if (!empty($product)) {
                    $context_data['product'] = $product;
                }
                if (!empty($demographic)) {
                    $context_data['demographic'] = $demographic;
                }
                $context = new PromptContext($context_data);

                // Choix du mode de génération
                $result = $this->processNodeFast($post_id, $map, $nb, $keyword, $context);
                //error_log("resultat génération $result");
                // Génération de l’image
                $imageDescription = $map[$post_id]['imageDescription'] ?? '';
                // if($imageDescription=='')
                //     error_log("Description image vide");
                // else
                //     error_log("Image description $imageDescription");

                $title = $map[$post_id]['title'];
                if (!$this->debugModImage) {
                    $image_url = $this->generator->generateImage($title, $imageDescription, $context, $this->defaultImage, $this->debugModImage);
                    $this->publisher->setFeaturedImage($post_id, $image_url);
                }
                
    
                // Ajout des liens internes
                $links = $this->linker->generateStructuredLinks($map, $post_id);
                $content = $result . $links;
                //error_log("Contenue :  $content");
                $this->publisher->fillAndPublishContent($post_id, $content);
                //error_log("Arrive à la fin");

            } 
            catch (\Throwable $e) {
                error_log("Erreur dans processNode pour post_id $post_id : " . $e->getMessage());
            }
        }
    }


    private function processNodeSafe(int $post_id, array &$map, int $nb, string $keyword,PromptContext $context): string {
        // $node = $map[$post_id];
        // $title = $node['title'];
        // $slug = get_post_field('post_name', $post_id);
        // $structure = $this->toBulletArchitecture($map);

        // // 🔸 Introduction
        // $intro = $this->generator->generateIntro($title, $structure, $context, $this->debugModContent);
        // $intro = "<div id='csb-intro-$slug' class='csb-content csb-intro'>$intro</div>";

        // // 🔸 Développements
        // $developments_html = '';

        // if (!empty($node['children_ids'])) {
        //     foreach ($node['children_ids'] as $child_id) {
        //         if (isset($map[$child_id])) {
        //             $child = $map[$child_id];
        //             $child_title = $child['title'];
        //             $child_slug = $this->slugify($child_title);
        //             $dev = $this->generator->generateDevelopment($child_title, $structure, $context,$this->debugModContent);
        //             $block_id = ($child_id < 0) ? "csb-leaf-$child_slug" : "csb-development-$child_slug";
        //             $dev_html = "<div id='$block_id' class='csb-content csb-development'>$dev</div>";

        //             if ($child_id >= 0) {
        //                 $link = '<p>Pour en savoir plus, découvrez notre article sur <a href="' . esc_url($child['link']) . '">' . esc_html($child_title) . '</a>.</p>';
        //                 $dev_html .= $link;
        //             }

        //             $developments_html .= $dev_html;
        //         }
        //     }
        // }

        // // Conclusion
        // $conclusion = $this->generator->generateConclusion($title, $structure,  $context,$this->debugModContent);
        // $conclusion = "<div id='csb-conclusion-$slug' class='csb-content csb-conclusion'>$conclusion</div>";
        // return $intro . $developments_html . $conclusion . '<!-- Mode sécurisé -->';
    }

    
    private function processNodeFast(int $post_id, array &$map, int $nb, string $keyword,PromptContext $context): string {        
        $node = $map[$post_id];
        $title = $node['title'];
        $structure = $this->toBulletArchitecture($map);
        $subparts = [];

        $isLeaf = $this->isLeafNode($post_id, $map);

        if ($isLeaf) {
            // Feuille réelle : récupérer les titres des enfants virtuels (niveau 3)
            $prompt= $this->prompter->fullArticleLeaf($keyword,$title,$context);
        } 
        else {
            // Article non-feuille : récupérer les titres + liens des enfants réels
            foreach ($node['children_ids'] as $child_id) {
                if (isset($map[$child_id]) && $map[$child_id]['post_id'] >= 0) {
                    $child = $map[$child_id];
                    $subparts[$child['title']] = $child['link'];
                }
            }
            $prompt= $this->prompter->fullArticle($keyword,$title,$subparts,$context);
        }

        // Génération du contenu HTML 
        $content = $this->generator->generateTexte($title,$this->debugModContent,"",$prompt);
        //error_log("Contenue dans node $content");
        return '<article class="article-csb">' . $content . '<!-- Mode rapide -->' . '</article>';

    
    }


    private function isLeafNode(int $node_id, array $map): bool {
        foreach ($map[$node_id]['children_ids'] ?? [] as $child_id) {
            if (isset($map[$child_id]) && $map[$child_id]['post_id'] >= 0) {
                return false; // Il a au moins un enfant réel
            }
        }

        // Aucun enfant réel trouvé ⇒ c’est une feuille
        return true;
    }


    private function sanitizeToRelativeUrl(string $url): string {
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


    private function createMapEntry(string $title, ?int $parent_id, ?string $forced_link = null, int $level = 0, ?int $post_id = null): array {
        $link = '';

        if ($post_id === null) {
            // Création classique
            $post_id = $this->publisher->createPostDraft($title, $level, $parent_id);
            $this->publisher->storeMeta($post_id, $level, $parent_id);

            $link = ($level === 0 && $forced_link !== null) 
                ? $forced_link 
                : '/' . get_post_field('post_name', $post_id);
        } else {
            // Post déjà existant fourni (ex: racine existante)
            $link = ($forced_link !== null) 
                ? $forced_link 
                : '/' . get_post_field('post_name', $post_id);

            if ($level === 0) {
                $this->publisher->markAsRoot($post_id);
            }
        }

        return [
            'post_id'      => $post_id,
            'title'        => $title,
            'link'         => $link,
            'parent_id'    => $parent_id,
            'children_ids' => [],
            'level'        => $level
        ];
    }


    private function parseStructureLines(string $raw): array {
        $lines = explode("\n", trim($raw));
        $parsed = [];

        foreach ($lines as $index => $line) {
            if (preg_match('/^(\s*)-\s*(.+)$/', $line, $matches)) {
                $indent = strlen($matches[1]);
                $title = trim($matches[2]);
                $level = intval($indent / 4);
                $parsed[] = [
                    'index' => $index,
                    'level' => $level,
                    'title' => $title,
                    'raw_indent' => $indent
                ];
            }
        }

        return $parsed;
    }


    private function buildMapFromParsedLines(array $parsed_lines, ?string $forced_link = null): array {
        $map = [];
        $stack = [];
        $total = count($parsed_lines);

        foreach ($parsed_lines as $i => $item) {
            $level = $item['level'];
            $title = $item['title'];

            $parent_id = $level === 0 ? null : ($stack[$level - 1]['post_id'] ?? null);

            // Résolution de l'ID pour la racine si nécessaire
            $resolved_post_id = null;
            if ($level === 0 && $forced_link !== null) {
                $post = get_page_by_path(ltrim($forced_link, '/'), OBJECT, ['post', 'page']);
                if ($post) {
                    $resolved_post_id = $post->ID;
                }
            }

            $entry = $this->createMapEntry($title, $parent_id, $level === 0 ? $forced_link : null, $level, $resolved_post_id);
            $post_id = $entry['post_id'];

            $map[$post_id] = $entry;
            $stack[$level] = &$map[$post_id];

            if ($parent_id !== null && isset($map[$parent_id])) {
                $map[$parent_id]['children_ids'][] = $post_id;
            }
        }

        return $map;
    }


    public function convertStructureToMap(string $raw, string $keyword, ?string $forced_link = null): array {
        $parsed_lines = $this->parseStructureLines($raw);

        // Injecte le mot-clé principal comme racine
        array_walk($parsed_lines, function (&$item) {
            $item['level'] += 1; // Décale les niveaux
        });

        array_unshift($parsed_lines, [
            'index' => -1,
            'level' => 0,
            'title' => $keyword,
            'raw_indent' => 0
        ]);

        return $this->buildMapFromParsedLines($parsed_lines, $forced_link);
    }



    private function updateMapFromPost(array &$map, array $posted_structure): void {
        foreach ($posted_structure as $post_id => $node_data) {
            if (isset($map[$post_id]) && isset($node_data['title'])) {
                $new_title = sanitize_text_field($node_data['title']);

                if ($map[$post_id]['title'] != $new_title) {
                    $this->publisher->updatePostTitleAndSlug($post_id, $new_title);
                    if ($post_id > 0) {
                        $map[$post_id]['link'] = '/' . get_post_field('post_name', $post_id);
                    } 
                    // else {
                    //     $map[$post_id]['link'] = '/leaf-' . sanitize_title($new_title);
                    // }
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

    
    private function renderLoadExistingButton(int $post_id): void {
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
        $parent_id = get_post_meta($post_id, '_csb_parent_id', true);
        $level = get_post_meta($post_id, '_csb_level', true);

        // Forcer le marquage si manquant
        if ($level === '' || $level === false) {
            update_post_meta($post_id, '_csb_level', 0);
            $level = 0;
        }
        if ($parent_id === '' || $parent_id === false) {
            update_post_meta($post_id, '_csb_parent_id', 0);
            $parent_id = 0;
        }

        $map[$post_id] = [
            'post_id'      => $post_id,
            'title'        => $title,
            'link'         => wp_make_link_relative(get_permalink($post_id)),
            'parent_id'    => intval($parent_id) ?: null,
            'children_ids' => [],
            'level'        => intval($level),
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

