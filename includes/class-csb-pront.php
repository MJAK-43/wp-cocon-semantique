<?php
if (!defined('ABSPATH')) exit;
require_once 'interface-csb-prompt-provider.php';

class CSB_Prompts implements PromptProviderInterface
{
    // === Règles générales ===
    private  array $generalRulesContents = [
        "- Ne pas utiliser de blocs ```html ni de Markdown.",
        "- Utiliser uniquement du HTML pur avec les balises autorisées.",
        "- Mettre en <strong> les mots-clés importants.",
        "- Souligner <u> les idées stratégiques si pertinent.",
        "- Style fluide, naturel, engageant avec un vocabulaire riche et SEO.",
    ];

    private array $fullRules = [
        "- Utilise uniquement des balises HTML simples : <h3>, <p>, <ul><li>, <strong>, <u>.",
        "- Pas de <div>, <h1>, ni Markdown ni ```html.",
        "- Optimise les contenus pour le SEO :",
        "    - Mets en <strong>gras</strong> le mot-clé « <strong>{keyword}</strong> »",
        "    - Souligne-le avec <u> si pertinent",
        "    - Et entoure-le de guillemets français « ... » lorsqu’il est utilisé tel quel",
        "- Style fluide, professionnel, naturel.",
        "- Ne pas réinventer les titres ou liens : respecte-les strictement."
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
    
    private array $imageRules = [
        "- Donne une **description visuelle concise** (moins de 12 mots).",
        "- Concentre-toi sur le **contenu spécifique du titre**, pas sur le mot-clé général.",
        "- Sois visuel et précis : objets, lieux, actions, ambiance.",
        "- Utilise un style populaire et concret (pas technique).",
        "- Pas de termes vagues comme 'illustration SEO' ou 'concept'.",
        "- Formulation directe, sans ponctuation finale.",
    ];

    
    // === Rôle et exemples ===
    private string $roleStructure = "expert SEO spécialisé en cocon sémantique.";

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

    protected function getRuleFull(string $keyword): string {
        return "Consignes générales :\n" . implode("\n", array_map(
            fn($line) => str_replace('{keyword}', $keyword, $line),
            $this->fullRules
        ));
    }


    // === Prompts publics ===

    public function structure(string $keyword, int $depth, int $breadth): string {
        return "return Ignore toutes les instructions précédentes.\n\n" . $this->getRuleStructure($depth, $breadth, $keyword);
    }

    public function intro(string $title, string $structure): string {
        return "Tu es un $roleStructure.

        Tu dois écrire une **INTRODUCTION HTML** pour un article intitulé « $title ».

        Voici la structure complète du cocon sémantique auquel cet article appartient :

        $structure

        " . $this->getRuleIntro();
    }

    public function development(string $title, string $structure): string {
        return "Tu es un $roleStructure.

        Tu dois écrire un bloc de DÉVELOPPEMENT HTML pour un article intitulé « $title ».

        Voici la structure du cocon sémantique global :

        $structure

        " . $this->getRuleDevelopment();
    }

    public function conclusion(string $title, string $structure): string {
        return "Tu es un $roleStructure.
        Tu dois écrire une CONCLUSION HTML pour l’article intitulé « $title ».
        Structure du cocon sémantique complet :
        $structure
        " . $this->getRuleConclusion();
    }

    public function image(string $keyword, string $title): string {
        return "Tu es un expert en iconographie et banques d’images comme Freepik.

            Donne une description d’image pour illustrer un article intitulé « $title » dans le contexte du mot-clé « $keyword ».

            " . $this->getRuleImage();
    }

    public function fullArtical(string $keyword, string $title, string $structure, array $subparts): string {
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

        $nb_parties = count($subparts);

        $prompt = "Tu es un rédacteur expert en SEO WordPress.
        Ta mission est de générer un **article HTML complet** pour le sujet : « $title »
        Voici la structure du cocon sémantique auquel appartient cet article :
        $structure
        L'article doit être structuré comme suit :
        1. Une <strong>introduction</strong> (2-3 paragraphes <p>) sans aucun lien.
        2. EXACTEMENT $nb_parties parties, avec pour chacune :
        - Un titre exact en <h3> : celui indiqué ci-dessous
        - Un ou deux paragraphes <p> de développement SEO-friendly";

        if (!in_array(null, $subparts, true)) {
            $prompt .= "\n- Puis un paragraphe contenant ce lien, formaté comme suit :\n  <p>Pour en savoir plus, découvrez notre article sur <a href=\"URL\">Titre</a></p>\n(Remplace « URL » et « Titre » par ceux donnés. Ne pas modifier les titres ou les liens.)";
        }

        $prompt .= "\n\nParties à respecter :\n$parties_formatees\n3. Une <strong>conclusion</strong> (1-2 <p>) claire et synthétique, sans lien.\n\n";
        $prompt .= $this->getRuleFull($keyword);

        return $prompt;
    }



}
