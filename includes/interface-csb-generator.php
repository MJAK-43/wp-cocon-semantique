<?php
if (!defined('ABSPATH')) exit;

interface GeneratorInterface {
    public function generateStructure(string $keyword, int $depth = 1, bool $test = false): string;
    public function generateImage(string $title, string $keyword, bool $test = false): string;
    public function generateIntro(string $title, string $structure, string $slug, bool $test): string;
    public function generateDevelopment(string $title, string $structure, bool $test): string;
    public function generateConclusion(string $title, string $structure, string $slug, bool $test): string;
    public function generateLeaf(string $title, string $structure, int $nb, bool $test = false): string;
}
