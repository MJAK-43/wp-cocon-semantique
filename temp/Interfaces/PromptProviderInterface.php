<?php

if (!defined('ABSPATH')) exit;

namespace CSB\Interfaces;

/**
 * Interface PromptProviderInterface
 *
 * Définit les méthodes requises pour tout fournisseur de prompts (ex : SEO, eCommerce, etc.).
 * Permet de générer différents éléments de contenu à partir d'un mot-clé ou d'une structure.
 */
interface PromptProviderInterface
{
    /**
     * Génère un prompt pour construire une structure arborescente.
     *
     * @param string $keyword  Mot-clé principal.
     * @param int $depth       Profondeur de la structure (niveaux).
     * @param int $breadth     Largeur (nombre de sous-parties par niveau).
     * @return string          Prompt pour générer la structure.
     */
    public function structure(string $keyword, int $depth, int $breadth): string;

    /**
     * Génère un prompt pour l'introduction d’un article.
     *
     * @param string $title      Titre de l’article.
     * @param string $structure  Structure globale de l’article.
     * @return string            Prompt pour l’introduction.
     */
    public function intro(string $title, string $structure): string;

    /**
     * Génère un prompt pour créer une image illustrant l’article.
     *
     * @param string $title    Titre de l’article.
     * @param string $keyword  Mot-clé principal.
     * @return string          Prompt pour l’image.
     */
    public function image(string $title, string $keyword): string;

    /**
     * Génère un prompt pour le développement (partie principale) de l’article.
     *
     * @param string $title      Titre de l’article ou de la section.
     * @param string $structure  Structure détaillée à développer.
     * @return string            Prompt pour le développement.
     */
    public function development(string $title, string $structure): string;

    /**
     * Génère un prompt pour la conclusion de l’article.
     *
     * @param string $title      Titre de l’article.
     * @param string $structure  Structure de l’article.
     * @return string            Prompt pour la conclusion.
     */
    public function conclusion(string $title, string $structure): string;

    /**
     * Génère un prompt pour rédiger l'article complet (intro, développement, conclusion).
     *
     * @param string $keyword    Mot-clé principal.
     * @param string $title      Titre de l’article.
     * @param string $structure  Structure de l’article.
     * @param array $subparts    Liste des titres des sous-parties à intégrer.
     * @return string            Prompt pour l’article complet.
     */
    public function fullArticle(string $keyword, string $title, string $structure, array $subparts): string;
}
