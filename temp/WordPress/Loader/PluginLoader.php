<?php

namespace CSB\WordPress\Loader;

use CSB\Core\Generator\ContentApiCaller;
use CSB\Core\Generator\ContentGenerator;
use CSB\Core\Generator\ImageApiCaller;
use CSB\Core\Settings\SettingsManager;
use CSB\Core\Publisher\Publisher;
use CSB\Core\Linker\InternalLinkBuilder;
use CSB\WordPress\Admin\AdminActions;
use CSB\WordPress\Admin\AdminUI;
use CSB\WordPress\Hooks\HookManager;
use CSB\Interfaces\PromptProviderInterface;

if (!defined('ABSPATH')) exit;

/**
 * Initialise les services principaux du plugin et enregistre les hooks.
 */
class PluginLoader
{
    public static function init(PromptProviderInterface $promptProvider): void{
        // Instanciation des services de génération
        $contentApiCaller = new ContentApiCaller();
        $imageApiCaller = new ImageApiCaller();
        $generator = new ContentGenerator($promptProvider, $contentApiCaller, $imageApiCaller);

        // Services métiers
        $settings = new SettingsManager();
        $publisher = new Publisher();
        $linker = new InternalLinkBuilder();

        // Interface et actions admin
        $adminUI = new AdminUI();
        $adminActions = new AdminActions($adminUI, $generator);

        // Enregistrement des hooks
        $hookManager = new HookManager($adminActions);
        $hookManager->register();
    }
}
