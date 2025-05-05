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



// Initialisation différée
add_action('plugins_loaded', function () {
    new CSB_Admin();
    new CSB_Settings();
});
