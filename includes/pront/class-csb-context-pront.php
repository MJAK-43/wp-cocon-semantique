<?php
if (!defined('ABSPATH')) exit;

require_once __DIR__ . '/interface-csb-prompt-provider.php';
require_once __DIR__ . '/trait-prompt-rules.php';
require_once __DIR__ . '/class-prompt-context.php';


class CSB_CustomPrompts implements PromptProviderInterface
{
    use PromptRulesTrait;

   public function structure(string $keyword, int $depth, int $breadth, PromptContext $context): string {
        $context_string = $context->toString();

        $template = <<<TXT
        Tu es un professionnel du SEO qui rédige des cocons sémantiques.

        Génère une structure de cocon, c'est-à-dire la liste des articles,
        sous forme d'une liste à puces autour de l'expression-clé « %s ».
        %s
        Cette liste à puces contiendra 12 éléments. 3 éléments en niveau 1 et pour chacun 3 éléments en niveau 2. 
        Les articles de niveau 2 seront associés à leur article de niveau 1.
        Les sous-articles doivent être correctement hiérarchisés (indentation de 4 espaces).
        Ne retourne que la liste à puces. Aucun commentaire, aucune balise HTML, aucune ligne vide.
        TXT;

            return sprintf($template, $keyword, $context_string);
        }



    /*
Génère une structure de cocon, c'est à dire la liste des articles, sous forme d'une liste 
à puces autour de l'expression-clé « CRM pour les courtiers en assurance » pour un professionnel 
qui propose des services d'intégration de CRM -  destiné à la cible suivante :  courtiers en assurance.
Cette liste à puces contiendra 12 éléments. 3 éléments en niveau 1 et pour chacun 3 éléments en 
niveau 2. Les articles de niveau 2 seront associés à leur article de niveau 1.

    */
    
    public function intro(string $title, string $structure, PromptContext $context): string {
        return "Tu es {$this->roleStructure}\n\n"
            . $context->toString()
            . "Tu dois écrire une **INTRODUCTION HTML** pour un article intitulé « $title ».\n\n"
            . "Voici la structure complète du cocon sémantique auquel cet article appartient :\n\n"
            . "$structure\n\n"
            . $this->getRuleIntro();
    }

    public function development(string $title, string $structure, PromptContext $context): string {
        return "Tu es {$this->roleStructure}\n\n"
            . $context->toString()
            . "Tu dois écrire un bloc de DÉVELOPPEMENT HTML pour un article intitulé « $title ».\n\n"
            . "Voici la structure du cocon sémantique global :\n\n"
            . "$structure\n\n"
            . $this->getRuleDevelopment();
    }

    public function conclusion(string $title, string $structure, PromptContext $context): string {
        return "Tu es {$this->roleStructure}\n\n"
            . $context->toString()
            . "Tu dois écrire une CONCLUSION HTML pour l’article intitulé « $title ».\n\n"
            . "Structure du cocon sémantique complet :\n\n"
            . "$structure\n\n"
            . $this->getRuleConclusion();
    }

    public function fullArticle(string $keyword, string $title, array $subparts, PromptContext $context): string {
        $context_string = $context->toString();
        $index = count($subparts);

        $parties_formatees = '';
        foreach ($subparts as $titre => $lien) {
            $parties_formatees .= sprintf("%s\nContenant le lien suivant intégré de façon naturelle dans le texte : %s\n", $titre, $lien);
        }

        $template = <<<TXT
        Rédige-moi un article sans titre avec une introduction, %d parties intitulées avec des balises H3 que voici :
        %s
        Suivi d'une conclusion sans titre.
        L'article doit être soigneusement optimisé pour le SEO (balises <strong> etc), 
        rédigé sous forme de blocs HTML directement intégrables dans WordPress (pas de balises ```html ou de guillemets « ),
        et sans commentaires à la fin.

        %s
        TXT;

        return sprintf($template, $index, $parties_formatees, $context_string);
    }

    public function fullArticleLeaf(string $keyword, string $title, PromptContext $context): string {
        $context_string = $context->toString();

        $template = <<<TXT
        Rédige-moi un article sans titre avec une introduction, 3 parties intitulées avec des balises <h3>,
        et une conclusion sans titre. L'article doit être soigneusement optimisé pour le SEO (balises <strong> etc),
        et prêt à être intégré directement dans WordPress (pas de balises ```html ou de guillemets « ), sans commentaires à la fin, avec le titre suivant :
        « %s »
        %s
        TXT;

        return sprintf($template, $title, $context_string);
    }

    public function image(string $keyword, string $title, PromptContext $context): string {
        return "Tu es {$this->roleImage}\n\n"
            . $context->toString()
            . "Donne une description d’image pour illustrer un article intitulé « $title » dans le contexte du mot-clé « $keyword ».\n\n"
            . $this->getRuleImage();
    }

}
