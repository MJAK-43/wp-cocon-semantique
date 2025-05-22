<?php
if (!defined('ABSPATH')) exit;

require_once __DIR__ . '/interface-csb-prompt-provider.php';
require_once __DIR__ . '/trait-prompt-rules.php';
require_once __DIR__ . '/class-prompt-context.php';


class CSB_CustomPrompts implements PromptProviderInterface
{
    use PromptRulesTrait;

    public function structure(string $keyword, int $depth, int $breadth, PromptContext $context): string {
        return "Ignore toutes les instructions précédentes.\n\n"
            . $context->toString()
            . $this->getRuleStructure($depth, $breadth, $keyword);
    }
    
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

    public function fullArticle(string $keyword, string $title, string $structure, array $subparts, PromptContext $context): string {
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

        return "Tu es {$this->roleStructure}\n\n"
            . $context->toString()
            . "Titre de l’article : « $title »\n\n"
            . "Voici la structure du cocon sémantique auquel appartient cet article :\n\n"
            . "$structure\n\n"
            . "Parties à respecter :\n"
            . "$parties_formatees\n\n"
            . $this->getRuleFull($keyword, count($subparts));
    }
    
    public function image(string $keyword, string $title, PromptContext $context): string {
        return "Tu es {$this->roleImage}\n\n"
            . $context->toString()
            . "Donne une description d’image pour illustrer un article intitulé « $title » dans le contexte du mot-clé « $keyword ».\n\n"
            . $this->getRuleImage();
    }

}
