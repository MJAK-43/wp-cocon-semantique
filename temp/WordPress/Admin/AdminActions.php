<?php

namespace CSB\WordPress\Admin;

use CSB\Core\Generator\ContentGenerator;
use CSB\Core\Generator\ContentApiCaller;
use CSB\Interfaces\PromptProviderInterface;

if (!defined('ABSPATH')) exit;

/**
 * Contient les traitements côté admin (AJAX, sauvegarde, validation...).
 */
class AdminActions
{
    private ContentGenerator $generator;

    public function __construct(ContentGenerator $generator)
    {
        $this->generator = $generator;
    }

    /**
     * Enregistre les hooks liés au back-office.
     */
    public function register(): void{
        add_action('admin_menu', [$this, 'addAdminPage']);
        add_action('admin_enqueue_scripts', [AdminUI::class, 'enqueueAssets']);
        add_action('wp_ajax_csb_generate_structure', [$this, 'handleStructureGeneration']);
    }

    /**
     * Ajoute le menu "Cocon Sémantique" dans l’admin.
     */
    public function addAdminPage(): void{
        add_menu_page(
            'Cocon Sémantique',
            'Cocon Sémantique',
            'manage_options',
            'csb-admin',
            [AdminUI::class, 'render'],
            'dashicons-networking',
            56
        );
    }

    /**
     * Gère la requête AJAX de génération de structure.
     */
    public function handleStructureGeneration(): void{
        check_ajax_referer('csb_ajax_nonce', 'nonce');

        $keyword = sanitize_text_field($_POST['keyword'] ?? '');
        $depth   = (int) ($_POST['depth'] ?? 2);
        $breadth = (int) ($_POST['breadth'] ?? 2);

        if (empty($keyword)) {
            wp_send_json_error('Mot-clé manquant.');
        }

        try {
            $structure = $this->generator->generateStructure($keyword, $depth, $breadth);
            wp_send_json_success(['structure' => $structure]);
        } catch (\Throwable $e) {
            wp_send_json_error('Erreur : ' . $e->getMessage());
        }
    }
}
