<?php
if (!defined('ABSPATH')) exit;

require_once 'interface-csb-prompt-provider.php';


class CSB_Prompts implements PromptProviderInterface {


    public function structure(string $keyword, int $depth, int $breadth): string {
        return "Ignore toutes les instructions précédentes.

        Tu es un expert SEO spécialisé en cocon sémantique.

        Génère une structure hiérarchique STRICTEMENT conforme à ces règles :

        - EXACTEMENT $depth niveaux (profondeur).
        - Chaque nœud NON-FEUILLE doit contenir EXACTEMENT $breadth sous-éléments.
        - Format brut en liste avec indentation : 4 espaces par niveau.
        - Chaque ligne commence par `- ` suivi du titre du nœud.
        - Titres uniquement en français, clairs, sans doublons, avec Majuscule À Chaque Mot.
        - AUCUN commentaire, balise, ou ligne vide.

        Mot-clé racine : « $keyword »

        ⚠️ Si la structure ne respecte pas les règles, la réponse est invalide.";
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

    public function leafParts(string $title, string $structure, int $number): string {
        return "Tu es un expert en structuration éditoriale SEO.

        Génère une liste de {$number} titres de sections pour un article intitulé « $title », qui est une feuille du cocon sémantique suivant :

        $structure

        Consignes :
        - La liste doit comporter exactement {$number} titres.
        - Chaque titre doit être court, clair, pertinent, informatif, et en français.
        - Retourne uniquement une liste en texte brut avec un tiret `-` devant chaque titre.
        - Pas de numérotation, pas de bloc Markdown, pas de HTML.";
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

}
