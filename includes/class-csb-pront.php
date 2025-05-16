<?php
if (!defined('ABSPATH')) exit;

require_once 'interface-csb-prompt-provider.php';


class CSB_Prompts implements PromptProviderInterface {

    public function structure(string $keyword, int $depth, int $breadth): string {
        return "return Ignore toutes les instructions précédentes.

        Tu es un expert SEO spécialisé en cocon sémantique.

        🎯 Ta mission : générer une structure STRICTEMENT conforme à ces règles :

        - EXACTEMENT $depth niveaux de profondeur hiérarchique.
        - Chaque nœud NON-FEUILLE doit contenir EXACTEMENT $breadth sous-éléments.
        - Chaque nœud FEUILLE se trouve uniquement au niveau $depth.
        - La structure doit former un arbre complet et équilibré : pas de niveaux manquants.
        - Format en texte brut avec indentation de 4 espaces par niveau.
        - Chaque ligne commence par « - » suivi du titre du nœud.
        - Les titres sont en français, clairs, uniques, avec Majuscule À Chaque Mot.
        - AUCUN commentaire, AUCUNE balise, AUCUNE ligne vide.

        Mot-clé racine : « $keyword »

        
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
                    - Point B2b";
    }


    public function intro(string $title, string $structure): string{
        return "Tu es un rédacteur SEO professionnel expert de WordPress.
    
        Tu dois écrire une **INTRODUCTION HTML** pour un article intitulé « $title ».
        
        Voici la structure complète du cocon sémantique auquel cet article appartient :
        
        $structure
    
        Consignes :
        - Structure en 2 à 3 paragraphes <p>, sans utiliser de <div>.
        - Utilise un ton engageant, accessible, fluide et vendeur.
        - **Met en gras avec <strong> les mots ou expressions clés SEO** (liés au sujet).
        - **Souligne les idées importantes** avec <u> si pertinent.
        - Pas de titres <h1> ou <h2>.
        - Ne commence jamais par « Cet article va parler de… ».
        - Interdiction d'utiliser ```html ou blocs Markdown.
        - Favoriser un vocabulaire riche, précis et sémantique SEO.";
    }


    public function image(string $keyword, string $title): string {
        return "Imagine une photographie réaliste liée au sujet \"$title\" dans le contexte de \"$keyword\". 
        Donne une description très concrète, visuelle, simple et populaire, en moins de 10 mots. 
        Utilise des termes qu’on trouverait dans une banque d’images.";
    }   

    
    public function development(string $title, string $structure): string{
        return "Tu es un expert en rédaction SEO sur WordPress.

        Tu dois écrire un bloc de DÉVELOPPEMENT HTML pour un article intitulé « $title ».
        
        Voici la structure du cocon sémantique global :
        
        $structure
        
        
        Consignes :
        - Ne pas utiliser de <div>.
        - Ajoute un titre unique en <h3> : le sujet exact traité.
        - Ajoute 1 ou 2 paragraphes <p> clairs et optimisés SEO.
        - Mets en <strong> les mots-clés importants.
        - Souligne <u> les éléments stratégiques si pertinent.
        - Ajoute une <ul><li> si cela améliore la lisibilité (ex: avantages, conseils).
        - Structure uniquement avec <h3>, <p> et éventuellement <ul><li>.
        
        Important :
        - Langage naturel, agréable, optimisé.
        - Pas de ```html ni Markdown.
        - Vocabulaire riche, sémantiquement varié.;

        Interdictions :
        - N'utilise pas ```html ni de blocs Markdown.
        - N'utilise que HTML pur dans les balises spécifiées";
    }


    public function conclusion(string $title, string $structure): string{
        return "Tu es un rédacteur SEO confirmé.
    
        Tu dois écrire une CONCLUSION HTML pour l’article intitulé « $title ».
        
        Structure du cocon sémantique complet :
        
        $structure
        
        Consignes :
        - Résume en 2 paragraphes maximum.
        - Rappelle les mots-clés importants en <strong> pour renforcer l'optimisation SEO.
        - Termine par une phrase inspirante ou engageante.
        - Structure uniquement avec <p> et <strong>, sans utiliser de <div>.
        - Pas de liens ni d’ouverture vers d’autres articles.
        - Style naturel, positif, dynamique.
        - Aucune balise ```html ni Markdown.";
    }


    public function fullArtical(string $title, string $structure, int $number, array $links): string {
        $formattedLinks = '';
        foreach ($links as $i => $link) {
            $formattedLinks .= "- Lien " . ($i + 1) . " : " . $link . "\n";
        }

        return "Tu es un rédacteur expert en SEO et WordPress.

        🎯 Ta mission : Rédiger un **article HTML complet** intitulé « $title », en suivant la structure du cocon sémantique ci-dessous :

        $structure

        L'article doit contenir :
        - Une **introduction** engageante (2 à 3 <p>), sans lien.
        - EXACTEMENT $number parties principales (Partie 1, 2, 3...), chacune avec :
            • Un <h3> clair et optimisé (titre de la partie)
            • 1 à 2 <p> + si pertinent une <ul><li>
            • 1 lien HTML que je te fournirai, intégré naturellement dans le texte avec une ancre descriptive.
        - Une **conclusion** synthétique (1 à 2 <p>), avec un ton positif.
        - À la fin : une section « Pour aller plus loin » contenant 2 liens HTML avec texte d'accroche.

        📎 Voici les liens à intégrer :
        $formattedLinks

        ⛔ Interdictions :
        - Pas de <div>, pas de <h1>, pas de balises inutiles.
        - Pas de syntaxe Markdown, ni de ```html.

        ✅ Obligations SEO :
        - Mots-clés importants en <strong>
        - Éléments importants soulignés avec <u> si utile
        - Style fluide, professionnel, adapté au web

        🔎 Rendu attendu :
        - Du HTML pur, prêt à être inséré dans un article WordPress
        - Un ton accessible, expert, naturel et structuré";
    }



}
