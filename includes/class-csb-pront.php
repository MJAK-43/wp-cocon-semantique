<?php
if (!defined('ABSPATH')) exit;
require_once 'interface-csb-prompt-provider.php';

class CSB_Prompts implements PromptProviderInterface
{
    // === Règles générales ===
    private array $generalRulesContents = [
        "- N'utilise jamais de blocs de code Markdown (comme ```html ou ```), ni aucun format similaire.",
        "- Génère uniquement du HTML pur. Aucune balise <div>, aucun commentaire, aucun Markdown.",
        "- Utilise uniquement les balises HTML suivantes : <p>, <h3>, <strong>, <u>, <ul>, <li>, <a>.",
        "- Mets en <strong> les mots-clés importants pour le SEO.",
        "- Mets en <u> les idées clés qui renforcent l’intention SEO ou la stratégie de conversion.",
        "- Adopte un style fluide, naturel, engageant et professionnel, avec un vocabulaire riche et adapté à la lecture web.",
    ];

    private array $fullRules = [
        "- L'article doit contenir une introduction, EXACTEMENT N parties, et une conclusion.",
        "- Ne commence jamais l’introduction par un titre comme « Introduction » (même sans balise HTML).",
        "- Ne commence jamais la conclusion par un titre comme « Conclusion » (même sans balise HTML).",
        "- Tu dois générer EXACTEMENT N parties. Ne regroupe pas, ne divise pas, ne rajoute pas d'autres sections.",
        "- Tu dois utiliser **exactement** les titres fournis pour chaque partie. Ne les reformule pas.",
        "- Si un lien est associé à une partie, ajoute **à la fin de cette partie** un paragraphe HTML au format strict suivant :",
        "  <p>Pour en savoir plus, découvrez notre article sur <a href=\"URL\">Titre</a></p>",
        "- Ne modifie ni le texte du lien ni sa structure HTML.",
        "- N’ajoute jamais de lien HTML dans l’introduction ni dans la conclusion.",
    ];



    // === Règles spécifiques par type ===
    private array $introRules = [
        "- Structure en 2 à 3 paragraphes <p>, sans utiliser de <div>.",
        "- Ne commence jamais par un titre comme « Introduction » (même sans balise HTML).",
        "- Ne jamais inclure de lien dans l’introduction ni dans la conclusion.",
        "- Utilise un ton engageant, fluide, accessible, qui donne envie de lire la suite.",
        "- Pas de titres <h1> ou <h2>.",
        "- Ne commence jamais par « Cet article va parler de… ».",
    ];



    private array $developmentRules = [
        "- Ne pas utiliser de <div>.",
        "- Ajoute un titre unique en <h3> : exactement celui fourni, sans reformulation.",
        "- Ajoute 1 ou 2 paragraphes <p> clairs et optimisés SEO.",
        "- Ajoute une <ul><li> si tu présentes plusieurs éléments concrets à énumérer.",
        "- Structure uniquement avec <h3>, <p> et éventuellement <ul><li>.",
    ];


    private array $conclusionRules = [
        "- Résume en 2 paragraphes maximum.",
        "- Ne commence jamais par un titre comme « Conclusion » (même sans balise HTML).",
        "- Rappelle les mots-clés importants en <strong>.",
        "- Termine par une phrase engageante qui incite à la réflexion ou à l’action.",
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
        "- Les titres sont en français, uniques, explicites, sans termes génériques, et avec Majuscule À Chaque Mot.",
        "- AUCUN commentaire, AUCUNE balise, AUCUNE ligne vide.",
    ];

    
    private array $imageRules = [
        "- Donne une description visuelle concise (moins de 12 mots).",
        "- Concentre-toi sur le contenu spécifique du titre, pas sur le mot-clé général.",
        "- Sois visuel et précis : objets, lieux, actions, ambiance.",
        "- Utilise un style populaire et concret (pas technique).",
        "- Évite les termes vagues comme 'illustration SEO' ou 'concept'.",
        "- Rédige une phrase directe, sans point final.",
    ];


    
    // === Rôle et exemples ===
    private string $roleStructure = "un expert SEO spécialisé en cocon sémantique.";
    private string $roleImage = "un expert en iconographie et banques d’images comme Freepik.";


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
    private string $imageExemple = <<<TXT
        Exemples :
        - Femme concentrée écrivant sur un ordinateur portable dans un café
        - Carte mentale colorée sur tableau blanc avec post-it
        - Jeune homme souriant montrant écran de téléphone
        TXT;

    
    // === Méthodes de règles ===
    protected function getRuleIntro(): string {
        return "Consignes :\n" . implode("\n", array_merge($this->introRules, $this->generalRulesContents)) . "\n\n" . $this->introExemple;
    }

    protected function getRuleDevelopment(): string {
        return "Consignes :\n" . implode("\n", array_merge($this->developmentRules,$this->generalRulesContents)) . "\n\n" . $this->developmentExemple;
    }

    protected function getRuleConclusion(): string {
        return "Consignes :\n" . implode("\n", array_merge($this->conclusionRules,$this->generalRulesContents)) . "\n\n" . $this->conclusionExemple;
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

    protected function getRuleImage(): string {
        return "Consignes :\n" . implode("\n", $this->imageRules) . "\n\n" . $this->imageExemple;
    }

    protected function getRuleFull(string $keyword, int $nbParties): string {
        $rules = array_map(
            fn($line) => str_replace(['{keyword}', 'N'], [$keyword, $nbParties], $line),
            array_merge($this->fullRules, $this->generalRulesContents)
        );

        return "Consignes générales :\n" . implode("\n", $rules);
    }


    // === Prompts publics ===

    public function structure(string $keyword, int $depth, int $breadth): string {
        return "Ignore toutes les instructions précédentes.\n\n" . $this->getRuleStructure($depth, $breadth, $keyword);
    }

    public function intro(string $title, string $structure): string {
        return "Tu es " . $this->roleStructure . "

        Tu dois écrire une **INTRODUCTION HTML** pour un article intitulé « $title ».

        Voici la structure complète du cocon sémantique auquel cet article appartient :

        $structure

        " . $this->getRuleIntro();
    }

    public function development(string $title, string $structure): string {
        return "Tu es " . $this->roleStructure . "

        Tu dois écrire un bloc de DÉVELOPPEMENT HTML pour un article intitulé « $title ».

        Voici la structure du cocon sémantique global :

        $structure

        " . $this->getRuleDevelopment();
    }

    public function conclusion(string $title, string $structure): string {
        return "Tu es " . $this->roleStructure . "
        Tu dois écrire une CONCLUSION HTML pour l’article intitulé « $title ».
        Structure du cocon sémantique complet :
        $structure
        " . $this->getRuleConclusion();
    }

    public function image(string $keyword, string $title): string {
        return "Tu es " . $this->roleImage . "
        Donne une description d’image pour illustrer un article intitulé « $title » dans le contexte du mot-clé « $keyword ».
        " . $this->getRuleImage();
    }

    public function fullArticle(string $keyword, string $title, string $structure, array $subparts): string {
        $parties_formatees = '';
        $index = 1;

        foreach ($subparts as $titre => $lien) {
            $parties_formatees .= "- Partie $index : « $titre »";
            if (!is_null($lien)) {
                $parties_formatees .= " (inclure le lien : $lien)";
            }
            $parties_formatees .= "\n";
            $index++;
        }

        $prompt = "Tu es " . $this->roleStructure . ".
        Titre de l’article : « $title »

        Voici la structure du cocon sémantique auquel appartient cet article :
        $structure

        Parties à respecter :
        $parties_formatees

        " . $this->getRuleFull($keyword, count($subparts));
        return $prompt;
    }


}
