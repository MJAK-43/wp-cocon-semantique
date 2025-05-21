<?php
if (!defined('ABSPATH')) exit;

/**
 * Interface pour tous les fournisseurs de prompts (ex: SEO, Ecommerce, etc.)
 */
interface PromptProviderInterface
{
    public function structure(string $keyword, int $depth, int $breadth): string;

    public function intro(string $title, string $structure): string;

    public function image(string $title,string $keyword): string;

    public function development(string $title, string $structure): string;

    public function conclusion(string $title, string $structure): string;

    public function fullArticle(string $keyword,string $title, string $structure, array $subparts): string;
}
