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
require_once CSB_PATH . 'includes/class-csb-pront.php';
require_once CSB_PATH . 'includes/class-csb-linker.php';
require_once CSB_PATH . 'includes/class-csb-admin.php';
require_once CSB_PATH . 'includes/class-csb-settings.php';
require_once CSB_PATH . 'includes/class-csb-publisher.php';
require_once CSB_PATH . 'includes/class-csb-generator.php';




remove_filter('the_content', 'your_theme_category_display_function');

// Initialisation différée
add_action('plugins_loaded', function () {
    new CSB_Admin();
    new CSB_Settings();
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
