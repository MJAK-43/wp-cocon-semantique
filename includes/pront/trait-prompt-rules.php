<?php
if (!defined('ABSPATH')) exit;

trait PromptRulesTrait
{
    protected array $generalRulesContents = [
        "- HTML pur compatible WordPress (pas de <div>, commentaires ou Markdown).",
        "- Balises autorisées : <p>, <h3>, <strong>, <u>, <ul>, <li>, <a>.",
        //"- Style fluide, professionnel, engageant, adapté aux courtiers en assurance.",
    ];


    protected array $fullRules = [
        "- L'article commence par une introduction (sans titre), suivie de {n} parties (titres en <h3>), puis d’une conclusion (sans titre).",
        "- Utilise exactement les titres fournis, sans les reformuler.",
        "- Aucun lien dans l’introduction ni dans la conclusion.",
        //"- Chaque partie contient 1 ou 2 paragraphes <p> (ajoute <ul><li> si utile).",
    ];


    protected array $introRules = [
        "- Structure en 2 à 3 paragraphes <p>, sans utiliser de <div>.",
        "- Ne commence jamais par un titre comme « Introduction » (même sans balise HTML).",
        "- Ne jamais inclure de lien",
        "- Utilise un ton engageant, fluide, accessible, qui donne envie de lire la suite.",
        "- Pas de titres <h1> ou <h2>.",
        "- Ne commence jamais par « Cet article va parler de… ».",
    ];


    protected array $developmentRules = [
        "- Ne pas utiliser de <div>.",
        "- Ajoute un titre unique en <h3> : exactement celui fourni, sans reformulation.",
        "- Ajoute 1 ou 2 paragraphes <p> clairs et optimisés SEO.",
        "- Ne jamais inclure de lien",
        "- Ajoute une <ul><li> si tu présentes plusieurs éléments concrets à énumérer.",
        "- Structure uniquement avec <h3>, <p> et éventuellement <ul><li>.",
    ];

    
    protected array $conclusionRules = [
        "- Résume en 2 paragraphes maximum.",
        "- Ne commence jamais par un titre comme « Conclusion » (même sans balise HTML).",
        "- Ne jamais inclure de lien",
        "- Rappelle les mots-clés importants en <strong>.",
        "- Termine par une phrase engageante qui incite à la réflexion ou à l’action.",
        "- Structure uniquement avec <p> et <strong>, sans <div>.",
        "- Pas de liens ni d’ouverture vers d’autres articles.",
    ];

    protected array $structureRules = [
        //"- Arbre équilibré de 4 niveaux.",
        //"- Chaque nœud non-feuille a 2 sous-nœuds.",
        //"- Tous les nœuds de niveau 4 sont des feuilles.",
        "- Pas de commentaires, balises ou lignes vides.",
        //"- Format texte brut avec indentation de 4 espaces.",
        //"- Chaque ligne commence par « - » suivi d’un titre en français, clair, unique, avec Majuscule À Chaque Mot."
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

    protected string $structureExample = "";

    protected string $introExemple = "";
    protected string $developmentExemple = "";
    protected string $conclusionExemple = "";
    protected string $imageExemple ="";

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

        $rules_text = implode("\n", $rules);

        return <<<TEXT
    Contraintes :
    $rules_text
    TEXT;
    }

    protected function getRuleImage(): string {
        return "Consignes :\n" . implode("\n", $this->imageRules) . "\n\n" . $this->imageExemple;
    }

    protected function getRuleFull(string $keyword, array $subparts): string {
        $nbParties = count($subparts);
        

        // Génère les règles statiques
        $rules = array_map(
            fn($line) => str_replace('{n}', $nbParties, $line),
            $this->fullRules
        );

        // Ajoute les règles dynamiques pour chaque lien
        foreach ($subparts as $titre => $url) {
            if (!empty($url)) {
                $rules[] = "- Pour la partie « $titre », tu dois intégrer le lien suivant de façon naturelle et pertinente dans le texte : $url";
                $rules[] = "- Le lien HTML doit respecter strictement ce format : <a href=\"$url\">$titre</a>";
            }
        }

        // Ajoute les règles générales
        $rules = array_merge($rules, $this->generalRulesContents);

        return "Consignes générales :\n" . implode("\n", $rules);
    }


}
