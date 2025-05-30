<?php
/**
 * Plugin Name: Cocon Sémantique
 * Description: Génère automatiquement une structure de cocon sémantique avec contenus, liens et images.
 * Version: 0.1
 * Author: Nicolas Darietto
 */

if (!defined('ABSPATH')) exit; // Sécurité

define('CSB_PATH', plugin_dir_path(__FILE__));
define('CSB_URL', plugin_dir_url(__FILE__));

// Inclure les fichiers du plugin
add_action('init', function () {
    if (defined('DOING_AJAX') && DOING_AJAX && session_status() === PHP_SESSION_ACTIVE) {
        session_write_close();   // libère le verrou avant l’appel à tes callbacks
    }
}, 0); // priorité 0 = le plus tôt possible


$includes = [
    'includes/class-csb-linker.php',
    'includes/class-csb-admin.php',
    'includes/class-csb-settings.php',
    'includes/class-csb-publisher.php',
    'includes/class-csb-generator.php',
    'includes/pront/class-csb-context-pront.php',
    'includes/pront/class-prompt-context.php',
    'includes/pront/trait-prompt-rules.php',
    'includes/pront/interface-csb-prompt-provider.php',
];

foreach ($includes as $file) {
    $path = CSB_PATH . $file;
    if (file_exists($path)) {
        require_once $path;
    } elseif (defined('WP_DEBUG') && WP_DEBUG) {
        error_log("[CSB ERROR] Fichier introuvable : $path");
    }
}



// if (function_exists('opcache_reset')) {
//     opcache_reset();
// }


function csb_initialize_plugin() {
    $defaultImage = plugin_dir_url(__FILE__) . 'assets/img/defaultImage.png';
    $api_key = get_option('csb_openai_api_key');
    $prompter=new CSB_CustomPrompts();

    $generator = new CSB_Generator(
        $api_key
    );

    // Chemins relatifs pour JS et CSS
    $jsPath = 'assets/js/admin.js';
    $cssPath = 'assets/css/csb-front.css';

    // Instanciation avec les chemins
    new CSB_Admin($generator,$prompter,$jsPath, $cssPath,$defaultImage);
    new CSB_Settings();
}

// Initialisation différée
add_action('plugins_loaded', function () {
    try {
        csb_initialize_plugin();
    } 
    catch (Throwable $e) {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('[CSB ERROR] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
        }
    }
});

add_action('wp_enqueue_scripts', function () {
    if (is_singular('post') && get_post_meta(get_the_ID(), '_csb_generated', true)) {
        wp_enqueue_style(
            'csb_front_css',
            plugin_dir_url(__FILE__) . 'assets/css/csb-front.css',
            [],
            time() 
        );
    }
});
