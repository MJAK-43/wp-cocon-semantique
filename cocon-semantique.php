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
require_once CSB_PATH . 'includes/class-csb-admin.php';
new CSB_Admin();
require_once CSB_PATH . 'includes/class-csb-settings.php';
new CSB_Settings();
require_once CSB_PATH . 'includes/class-csb-publisher.php';
new CSB_Publisher();
require_once CSB_PATH . 'includes/class-csb-generator.php';
new CSB_Generator();
if (file_exists(CSB_PATH . 'includes/linker.php')) require_once CSB_PATH . 'includes/linker.php';

