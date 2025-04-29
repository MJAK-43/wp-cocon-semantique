<?php
if (!defined('ABSPATH')) exit;

/**
 * Interface pour tous les fournisseurs de prompts (ex: SEO, Ecommerce, etc.)
 */
interface PromptProviderInterface
{
    public function structure(string $keyword, int $depth): string;

    public function intro(string $title, string $structure): string;

    public function image(string $title): string;

    public function development(string $title, string $structure): string;

    public function leafDevelopment(string $title, string $structure, int $number): string;

    public function conclusion(string $title, string $structure): string;
}
