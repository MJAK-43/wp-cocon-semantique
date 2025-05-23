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



remove_filter('the_content', 'your_theme_category_display_function');

// Initialisation différée
add_action('plugins_loaded', function () {
    try {
        $defaultImage = plugin_dir_url(__FILE__) . 'assets/img/defaultImage.png';

        $generator = new CSB_Generator(
            new CSB_CustomPrompts(),
            $defaultImage
        );

        new CSB_Admin($generator);
        new CSB_Settings();

    } catch (Throwable $e) {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('[CSB ERROR] ' . $e->getMessage());
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
