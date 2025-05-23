<?php

if (!defined('ABSPATH')) exit;

namespace CSB\Interfaces;

/**
 * Interface GeneratorInterface
 *
 * Définit les méthodes pour générer le contenu textuel d’un article
 * (structure, parties, article complet, image) à partir des prompts fournis.
 */
interface GeneratorInterface
{
    /**
     * Génère la structure d’un cocon sémantique.
     *
     * @param string $keyword  Mot-clé principal.
     * @param int $depth       Profondeur de la structure (niveaux).
     * @param int $breadth     Largeur (nombre de sous-parties par niveau).
     * @param bool $test       Si vrai, retourne un contenu factice de test.
     * @return string          Structure textuelle générée.
     */
    public function generateStructure(string $keyword, int $depth, int $breadth, bool $test = false): string;

    /**
     * Génère une image à partir d’un titre et d’un mot-clé.
     *
     * @param string $title    Titre de l’article.
     * @param string $keyword  Mot-clé principal.
     * @param bool $test       Si vrai, retourne une URL ou une image fictive.
     * @return string          URL de l’image générée ou contenu associé.
     */
    public function generateImage(string $title, string $keyword, bool $test = false): string;

    /**
     * Génère l’introduction d’un article.
     *
     * @param string $title      Titre de l’article.
     * @param string $structure  Structure complète de l’article.
     * @param bool $test         Si vrai, retourne une intro factice.
     * @return string            Introduction générée.
     */
    public function generateIntro(string $title, string $structure, bool $test): string;

    /**
     * Génère le développement (corps) de l’article.
     *
     * @param string $title      Titre de la section ou de l’article.
     * @param string $structure  Structure détaillée à développer.
     * @param bool $test         Si vrai, retourne un développement factice.
     * @return string            Développement généré.
     */
    public function generateDevelopment(string $title, string $structure, bool $test): string;

    /**
     * Génère la conclusion de l’article.
     *
     * @param string $title      Titre de l’article.
     * @param string $structure  Structure de l’article.
     * @param bool $test         Si vrai, retourne une conclusion fictive.
     * @return string            Conclusion générée.
     */
    public function generateConclusion(string $title, string $structure, bool $test): string;

    /**
     * Génère l’article complet en une seule requête (intro, développement, conclusion).
     *
     * @param string $keyword    Mot-clé principal.
     * @param string $title      Titre de l’article.
     * @param string $structure  Structure détaillée.
     * @param array $subparts    Liste des sous-parties à intégrer.
     * @param bool $test         Si vrai, retourne un article fictif.
     * @return string            Contenu HTML complet de l’article.
     */
    public function generateFullContent(string $keyword, string $title, string $structure, array $subparts, bool $test = false): string;
}
