<?php
if (!defined('ABSPATH')) exit;


require_once __DIR__ . '/pront/class-prompt-context.php';

/**
 * Interface GeneratorInterface
 * Définit les méthodes de génération de contenu pour un cocon sémantique.
 */
interface GeneratorInterface
{
    /**
     * Génère une structure hiérarchique textuelle à partir d’un mot-clé principal.
     *
     * @param string $keyword Le mot-clé racine du cocon.
     * @param int $depth Le nombre de niveaux de profondeur.
     * @param int $breadth Le nombre de sous-éléments par niveau.
     * @param PromptContext $context Contexte personnalisé : produit, audience, etc.
     * @param bool $test Si vrai, renvoie un contenu par défaut au lieu d’appeler l’API.
     * @return string La structure textuelle générée.
     */
    public function generateStructure(string $keyword, int $depth, int $breadth, PromptContext $context, bool $test = false): string;

    /**
     * Génère une image via une description textuelle optimisée.
     *
     * @param string $title Le titre de l’article à illustrer.
     * @param string $keyword Le mot-clé SEO associé à l’article.
     * @param PromptContext $context Contexte personnalisé : produit, audience, etc.
     * @param bool $test Si vrai, renvoie une image par défaut.
     * @return string URL de l’image générée ou par défaut.
     */
    public function generateImage(string $title, string $keyword, PromptContext $context, bool $test = false): string;

    /**
     * Génère une introduction HTML pour un article.
     *
     * @param string $title Le titre de l’article.
     * @param string $structure La structure complète du cocon.
     * @param PromptContext $context Contexte personnalisé : produit, audience, etc.
     * @param bool $test Si vrai, renvoie une intro par défaut.
     * @return string HTML généré.
     */
    public function generateIntro(string $title, string $structure, PromptContext $context, bool $test): string;

    /**
     * Génère un bloc de développement HTML pour un sous-titre donné.
     *
     * @param string $title Le titre de la partie à développer.
     * @param string $structure La structure complète du cocon.
     * @param PromptContext $context Contexte personnalisé : produit, audience, etc.
     * @param bool $test Si vrai, renvoie un développement par défaut.
     * @return string HTML généré.
     */
    public function generateDevelopment(string $title, string $structure, PromptContext $context, bool $test): string;

    /**
     * Génère une conclusion HTML pour un article.
     *
     * @param string $title Le titre de l’article.
     * @param string $structure La structure complète du cocon.
     * @param PromptContext $context Contexte personnalisé : produit, audience, etc.
     * @param bool $test Si vrai, renvoie une conclusion par défaut.
     * @return string HTML généré.
     */
    public function generateConclusion(string $title, string $structure, PromptContext $context, bool $test): string;

    /**
     * Génère un article complet avec introduction, développements, conclusion.
     *
     * @param string $keyword Le mot-clé principal de l’article.
     * @param string $title Le titre de l’article.
     * @param string $structure La structure complète du cocon.
     * @param array $subparts Liste des sous-titres avec ou sans liens HTML.
     * @param PromptContext $context Contexte personnalisé : produit, audience, etc.
     * @param bool $test Si vrai, renvoie un article par défaut.
     * @return string HTML complet de l’article.
     */
    public function generateFullContent(string $keyword, string $title, string $structure, array $subparts, PromptContext $context, bool $test = false): string;
}
