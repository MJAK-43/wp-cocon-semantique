<?php
if (!defined('ABSPATH')) exit;


require_once __DIR__ . '/pront/class-prompt-context.php';

/**
 * Interface GeneratorInterface
 * Définit les méthodes de génération de contenu pour un cocon sémantique.
 */
interface GeneratorInterface
{

    public function generateImage(string $title, string $keyword, PromptContext $context,bool $test = false,string $getDefaultImage): string ;
    public function generateTexte(string $title, bool $test, 
    string $defaultContent, string $prompt, bool $preserveFormatting = false): string;
    
}
