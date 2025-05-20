<?php
if (!defined('ABSPATH')) exit;
require_once 'interface-csb-prompt-provider.php';

class CSB_Prompts implements PromptProviderInterface
{
    // === Règles générales ===
    private  array $generalRules = [
        "- Ne pas utiliser de blocs ```html ni de Markdown.",
        "- Utiliser uniquement du HTML pur avec les balises autorisées.",
        "- Mettre en <strong> les mots-clés importants.",
        "- Souligner <u> les idées stratégiques si pertinent.",
        "- Style fluide, naturel, engageant avec un vocabulaire riche et SEO.",
    ];

    // === Règles spécifiques par type ===
    private array $introRules = [
        "- Structure en 2 à 3 paragraphes <p>, sans utiliser de <div>.",
        "- Utilise un ton engageant, accessible, fluide et vendeur.",
        "- Pas de titres <h1> ou <h2>.",
        "- Ne commence jamais par « Cet article va parler de… ».",
    ];

    private array $developmentRules = [
        "- Ne pas utiliser de <div>.",
        "- Ajoute un titre unique en <h3> : le sujet exact traité.",
        "- Ajoute 1 ou 2 paragraphes <p> clairs et optimisés SEO.",
        "- Ajoute une <ul><li> si cela améliore la lisibilité.",
        "- Structure uniquement avec <h3>, <p> et éventuellement <ul><li>.",
    ];

    private array $conclusionRules = [
        "- Résume en 2 paragraphes maximum.",
        "- Rappelle les mots-clés importants en <strong>.",
        "- Termine par une phrase inspirante ou engageante.",
        "- Structure uniquement avec <p> et <strong>, sans <div>.",
        "- Pas de liens ni d’ouverture vers d’autres articles.",
    ];

    private array $structureRules = [
        "- EXACTEMENT {depth} niveaux de profondeur hiérarchique.",
        "- Chaque nœud NON-FEUILLE doit contenir EXACTEMENT {breadth} sous-éléments.",
        "- Chaque nœud FEUILLE se trouve uniquement au niveau {depth}.",
        "- La structure doit former un arbre complet et équilibré : pas de niveaux manquants.",
        "- Format en texte brut avec indentation de 4 espaces par niveau.",
        "- Chaque ligne commence par « - » suivi du titre du nœud.",
        "- Les titres sont en français, clairs, uniques, avec Majuscule À Chaque Mot.",
        "- AUCUN commentaire, AUCUNE balise, AUCUNE ligne vide.",
    ];

    // === Rôle et exemples ===
    private string $roleStructure = "Tu es un expert SEO spécialisé en cocon sémantique.";

    //  Exemples à compléter ultérieurement
    private string $structureExample = <<<TXT
            Exemple pour profondeur = 4 et largeur = 2 :

            - Sujet Principal
                - Thème A
                    - Sous-thème A1
                        - Point A1a
                        - Point A1b
                    - Sous-thème A2
                        - Point A2a
                        - Point A2b
                - Thème B
                    - Sous-thème B1
                        - Point B1a
                        - Point B1b
                    - Sous-thème B2
                        - Point B2a
                        - Point B2b
            TXT;
    private string $introExemple = "";
    private string $developmentExemple = "";
    private string $conclusionExemple = "";
    private string $imageExemple = "";

    // === Méthodes de règles ===
    protected function getRuleIntro(): string {
        return "Consignes :\n" . implode("\n", array_merge($this->introRules, $this->generalRules)) . "\n\n" . $this->introExemple;
    }

    protected function getRuleDevelopment(): string {
        return "Consignes :\n" . implode("\n", array_merge($this->developmentRules, this->generalRules)) . "\n\n" . $this->developmentExemple;
    }

    protected function getRuleConclusion(): string {
        return "Consignes :\n" . implode("\n", array_merge($this->conclusionRules, this->generalRules)) . "\n\n" . $this->conclusionExemple;
    }

    protected function getRuleStructure(int $depth, int $breadth, string $keyword): string {
        $rules = array_map(
            fn($r) => str_replace(['{depth}', '{breadth}'], [$depth, $breadth], $r),
            $this->structureRules
        );

        return "{$this->roleStructure}

        Ta mission : générer une structure STRICTEMENT conforme à ces règles :

        " . implode("\n", $rules) . "

        Mot-clé racine : « $keyword »

        {$this->structureExample}";
    }

    // === Prompts publics ===

    public function structure(string $keyword, int $depth, int $breadth): string {
        return "return Ignore toutes les instructions précédentes.\n\n" . $this->getRuleStructure($depth, $breadth, $keyword);
    }

    public function intro(string $title, string $structure): string {
        return "Tu es un rédacteur SEO professionnel expert de WordPress.

        Tu dois écrire une **INTRODUCTION HTML** pour un article intitulé « $title ».

        Voici la structure complète du cocon sémantique auquel cet article appartient :

        $structure

        " . $this->getRuleIntro();
    }

    public function development(string $title, string $structure): string {
        return "Tu es un expert en rédaction SEO sur WordPress.

        Tu dois écrire un bloc de DÉVELOPPEMENT HTML pour un article intitulé « $title ».

        Voici la structure du cocon sémantique global :

        $structure

        " . $this->getRuleDevelopment();
    }

    public function conclusion(string $title, string $structure): string {
        return "Tu es un rédacteur SEO confirmé.
        Tu dois écrire une CONCLUSION HTML pour l’article intitulé « $title ».
        Structure du cocon sémantique complet :
        $structure
    " . $this->getRuleConclusion();
    }

    public function image(string $keyword, string $title): string {
        return "Imagine une photographie réaliste liée au sujet \"$title\" dans le contexte de \"$keyword\". 
        Donne une description très concrète, visuelle, simple et populaire, en moins de 10 mots. 
        Utilise des termes qu’on trouverait dans une banque d’images.
        {$this->imageExemple}";
    }
}
