<?php
if (!defined('ABSPATH')) exit;

require_once 'interface-csb-prompt-provider.php';


class CSB_Prompts implements PromptProviderInterface {

    public function structure(string $keyword, int $depth): string{
        return "Tu es un expert en SEO abtimiser pour le référencement. Génère une structure hiérarchique de cocon sémantique en texte brut.
        Consignes :
        - Utilise des tirets `-` pour chaque point.
        - Utilise **4 espaces** pour chaque niveau d’imbrication (indentation).
        - Le mot-clé principal est : \"$keyword\"
        - $depth sous-thèmes, chacun avec $depth sous-sous-thèmes.
        - Chaque titre doit commencer par une majuscule à chaque mot
        Pas de commentaires, pas de balises, juste le texte hiérarchique.";
    }

    public function intro(string $title, string $structure): string{
        return "Tu es un rédacteur SEO professionnel expert de WordPress.
    
        Tu dois écrire une **INTRODUCTION HTML** pour un article intitulé « $title ».
        
        Voici la structure complète du cocon sémantique auquel cet article appartient :
        
        $structure
    
        Consignes :
        - Structure en 2 à 3 paragraphes <p>, tous dans un <div class='csb-intro'>.
        - Utilise un ton engageant, accessible, fluide et vendeur.
        - **Met en gras avec <strong> les mots ou expressions clés SEO** (liés au sujet).
        - **Souligne les idées importantes** avec <u> si pertinent.
        - Pas de titres <h1> ou <h2>.
        - Ne commence jamais par « Cet article va parler de… ».
        - Interdiction d'utiliser ```html ou blocs Markdown.
        - Favoriser un vocabulaire riche, précis et sémantique SEO.";
    }

    public function image(string $title): string {
        return "Imagine une photographie réaliste correspondant au sujet suivant : \"$title\". 
        Donne une description très concrète, contenant des éléments visuels simples et populaires, en moins de 10 mots. 
        Utilise des mots que l'on trouverait facilement sur une banque d'images.";
    }
    

    public function development(string $title, string $structure): string{
        return "Tu es un expert en rédaction SEO sur WordPress.

        Tu dois écrire un bloc de DÉVELOPPEMENT HTML pour un article intitulé « $title ».
        
        Voici la structure du cocon sémantique global :
        
        $structure
        
        
        Consignes :
        - Ouvre un <div class='csb-development'>.
        - Ajoute un titre unique en <h4> : le sujet exact traité.
        - Ajoute 1 ou 2 paragraphes <p> clairs et optimisés SEO.
        - Mets en <strong> les mots-clés importants.
        - Souligne <u> les éléments stratégiques si pertinent.
        - Ajoute une <ul><li> si cela améliore la lisibilité (ex: avantages, conseils).
        - Ferme proprement le <div>.
        
        Important :
        - Langage naturel, agréable, optimisé.
        - Pas de ```html ni Markdown.
        - Vocabulaire riche, sémantiquement varié.";
    }

    public function leafDevelopment(string $title, string $structure, int $number): string{
        return "Tu es un expert en rédaction SEO sur WordPress.
    
        Tu dois écrire un DÉVELOPPEMENT HTML pour l'article intitulé « $title », qui est une feuille du cocon sémantique (pas d'enfants).
    
        Voici la structure globale du cocon sémantique :
        $structure
    
        Consignes strictes :
        - Crée exactement {$number} parties distinctes.
        - Pour chaque partie :
            - Ouvre un <div class='csb-development'>.
            - Commence avec un titre unique dans une balise <h4> (pas d'autres titres).
            - Ajoute 1 ou 2 paragraphes <p> descriptifs, naturels et engageants.
            - Mets en gras <strong> les mots-clés importants.
            - Si pertinent, souligne <u> les points stratégiques.
            - Si nécessaire, ajoute une liste <ul><li> pour structurer les informations.
            - Ferme proprement le <div>.

        Règles :
        - Il doit y avoir **exactement {$number} blocs** au final.
        - Ne dépasse jamais ni ne réduis ce nombre.
        - N'ajoute ni introduction globale, ni conclusion globale.
        - Aucun lien interne ou externe.

        Interdictions :
        - N'utilise pas ```html ni de blocs Markdown.
        - N'utilise que HTML pur dans les balises spécifiées.

        Style :
        - Langage fluide, naturel, riche sémantiquement.
        - Optimisé pour le SEO sans être artificiel.";
    
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
        - Structure uniquement avec <div class='csb-conclusion'>, <p> et <strong>.
        - Pas de liens ni d’ouverture vers d’autres articles.
        - Style naturel, positif, dynamique.
        - Aucune balise ```html ni Markdown.";
    }

}
