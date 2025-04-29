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
        - Ne commence pas par « Cet article va parler de… ».
        - Structure l’intro en 2 ou 3 paragraphes <p>, dans un <div class='csb-intro'>.
        - Utilise un ton engageant, accessible, et un vocabulaire fluide.
        - Pas de <h1>, <h2>, ni de résumé. Pas de liste.
        - N’utilise **jamais** de balise ```html ni aucun bloc de code Markdown
        
        Ta seule mission : captiver le lecteur pour qu’il ait envie de lire les développements.";
    }

    public function image(string $title): string{
        return "Donne une description très courte (moins de 10 mots) qui correspond à une image pour illustrer un article intitulé \"$title\". 
        La description doit être simple, réaliste et facile à comprendre.";
    }

    public function development(string $title, string $structure): string{
        return "Tu es un expert en rédaction SEO sur WordPress.

        Tu dois écrire un bloc de DÉVELOPPEMENT HTML pour un article intitulé « $title ».
        
        Voici la structure du cocon sémantique global :
        
        $structure
        
        Consignes :
        - doit avoir un <h4>$title</h4> suivi de 1 ou 2 paragraphes <p>. 
        - Si c’est pertinent, tu peux utiliser des <ul><li> pour lister des conseils, caractéristiques, etc.
        - N’utilise **jamais** de balise ```html ni aucun bloc de code Markdown
        Structure le tout dans un <div class='csb-development'>.";
    }

    public function leafDevelopment(string $title, string $structure, int $number): string{
        return "Tu es un expert en rédaction SEO sur WordPress.
    
        Tu dois écrire un DÉVELOPPEMENT HTML pour l'article intitulé « $title », qui est une feuille du cocon sémantique (pas d'enfants).
    
        Voici la structure globale du cocon sémantique :
        $structure
    
        Consignes STRICTES :
        - Crée exactement {$number} parties distinctes.
        - Pour chaque partie :
            - Ouvre un <div class='csb-development'>.
            - Commence avec un seul et unique titre dans une balise <h4> (pas d'autres titres).
            - Ajoute 1 ou 2 paragraphes <p> descriptifs et engageants.
            - Si pertinent, ajoute une liste <ul><li>...</li></ul> entre les paragraphes.
            - Ferme proprement le <div>.
    
        Règles :
        - Il doit y avoir exactement {$number} blocs de développement au final.
        - Ne dépasse jamais ce nombre.
        - N'ajoute pas d'introduction globale ni de conclusion globale.
        - Aucun lien externe ou interne.
    
        Interdictions :
        - Ne pas utiliser de balises ```html ni de format Markdown.
        - Ne pas générer plus ou moins de blocs que demandé.
    
        Style :
        - Langage fluide, naturel et SEO-friendly.
        - Chaque bloc doit être autonome et agréable à lire.";
    
    }


    public function conclusion(string $title, string $structure): string{
        return "Tu es un rédacteur SEO confirmé.
    
        Tu dois écrire une CONCLUSION HTML pour l’article intitulé « $title ».
        
        Structure du cocon sémantique complet :
        
        $structure
        
        Consignes :
        - Résume les points forts de l’article sans redites.
        - Termine sur un message encourageant ou une réflexion.
        - Utilise uniquement des balises HTML suivantes : <div class='csb-conclusion'>, <p>, <strong>.
        - Ne mets pas de liens ni d’ouverture vers d’autres sujets.
        - N’utilise **jamais** de balise ```html ni aucun bloc de code Markdown
        
        Écris de manière naturelle, engageante, et claire.";
    }

}
