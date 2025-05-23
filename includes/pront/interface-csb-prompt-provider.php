<?php
if (!defined('ABSPATH')) exit;

require_once 'class-prompt-context.php'; // Assure-toi de charger PromptContext

/**
 * Interface pour tous les fournisseurs de prompts (ex: SEO, Ecommerce, etc.)
 */
interface PromptProviderInterface
{
    
    public function intro(string $title, string $structure, PromptContext $customiser): string;
    public function development(string $title, string $structure, PromptContext $context): string;    
    public function conclusion(string $title, string $structure, PromptContext $context): string;
    public function image(string $keyword, string $title, PromptContext $context): string;
    public function structure(string $keyword, int $depth, int $breadth, PromptContext $context): string;
    public function fullArticle(string $keyword, string $title,array $subparts, PromptContext $context): string;
}
