<?php
if (!defined('ABSPATH')) exit;

trait PromptRulesTrait
{
    protected array $generalRulesContents = [
        "- N'utilise jamais de blocs de code Markdown (comme ```html ou ```), ni aucun format similaire.",
        "- Génère uniquement du HTML pur. Aucune balise <div>, aucun commentaire, aucun Markdown.",
        "- Utilise uniquement les balises HTML suivantes : <p>, <h3>, <strong>, <u>, <ul>, <li>, <a>.",
        "- Mets en <strong> les mots-clés importants pour le SEO.",
        "- Mets en <u> les idées clés qui renforcent l’intention SEO ou la stratégie de conversion.",
        "- Adopte un style fluide, naturel, engageant et professionnel, avec un vocabulaire riche et adapté à la lecture web.",
    ];

    protected array $fullRules = [
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

    protected array $introRules = [
        "- Structure en 2 à 3 paragraphes <p>, sans utiliser de <div>.",
        "- Ne commence jamais par un titre comme « Introduction » (même sans balise HTML).",
        "- Ne jamais inclure de lien dans l’introduction ni dans la conclusion.",
        "- Utilise un ton engageant, fluide, accessible, qui donne envie de lire la suite.",
        "- Pas de titres <h1> ou <h2>.",
        "- Ne commence jamais par « Cet article va parler de… ».",
    ];

    protected array $developmentRules = [
        "- Ne pas utiliser de <div>.",
        "- Ajoute un titre unique en <h3> : exactement celui fourni, sans reformulation.",
        "- Ajoute 1 ou 2 paragraphes <p> clairs et optimisés SEO.",
        "- Ajoute une <ul><li> si tu présentes plusieurs éléments concrets à énumérer.",
        "- Structure uniquement avec <h3>, <p> et éventuellement <ul><li>.",
    ];

    protected array $conclusionRules = [
        "- Résume en 2 paragraphes maximum.",
        "- Ne commence jamais par un titre comme « Conclusion » (même sans balise HTML).",
        "- Rappelle les mots-clés importants en <strong>.",
        "- Termine par une phrase engageante qui incite à la réflexion ou à l’action.",
        "- Structure uniquement avec <p> et <strong>, sans <div>.",
        "- Pas de liens ni d’ouverture vers d’autres articles.",
    ];

    protected array $structureRules = [
        "- EXACTEMENT {depth} niveaux de profondeur hiérarchique.",
        "- Chaque nœud NON-FEUILLE doit contenir EXACTEMENT {breadth} sous-éléments.",
        "- Chaque nœud FEUILLE se trouve uniquement au niveau {depth}.",
        "- La structure doit former un arbre complet et équilibré : pas de niveaux manquants.",
        "- Format en texte brut avec indentation de 4 espaces par niveau.",
        "- Chaque ligne commence par « - » suivi du titre du nœud.",
        "- Les titres sont en français, uniques, explicites, sans termes génériques, et avec Majuscule À Chaque Mot.",
        "- AUCUN commentaire, AUCUNE balise, AUCUNE ligne vide.",
    ];

    protected array $imageRules = [
        "- Donne une description visuelle concise (moins de 12 mots).",
        "- Concentre-toi sur le contenu spécifique du titre, pas sur le mot-clé général.",
        "- Sois visuel et précis : objets, lieux, actions, ambiance.",
        "- Utilise un style populaire et concret (pas technique).",
        "- Évite les termes vagues comme 'illustration SEO' ou 'concept'.",
        "- Rédige une phrase directe, sans point final.",
    ];

    protected string $roleStructure = "un expert SEO spécialisé en cocon sémantique.";
    protected string $roleImage = "un expert en iconographie et banques d’images comme Freepik.";

    protected string $structureExample = <<<TXT
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

    protected string $introExemple = "";
    protected string $developmentExemple = "";
    protected string $conclusionExemple = "";
    protected string $imageExemple = <<<TXT
        Exemples :
        - Femme concentrée écrivant sur un ordinateur portable dans un café
        - Carte mentale colorée sur tableau blanc avec post-it
        - Jeune homme souriant montrant écran de téléphone
    TXT;

    protected function getRuleIntro(): string {
        return "Consignes :\n" . implode("\n", array_merge($this->introRules, $this->generalRulesContents)) . "\n\n" . $this->introExemple;
    }

    protected function getRuleDevelopment(): string {
        return "Consignes :\n" . implode("\n", array_merge($this->developmentRules, $this->generalRulesContents)) . "\n\n" . $this->developmentExemple;
    }

    protected function getRuleConclusion(): string {
        return "Consignes :\n" . implode("\n", array_merge($this->conclusionRules, $this->generalRulesContents)) . "\n\n" . $this->conclusionExemple;
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
}
