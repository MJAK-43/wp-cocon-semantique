<!-- <?php
// if (!defined('ABSPATH')) exit;
// require_once 'interface-csb-prompt-provider.php';
// require_once 'trait-prompt-rules.php';


// class CSB_Prompts implements PromptProviderInterface
// {
//     use PromptRulesTrait;

//     // === Prompts publics ===

//     public function structure(string $keyword, int $depth, int $breadth): string {
//         return "Ignore toutes les instructions précédentes.\n\n" . $this->getRuleStructure($depth, $breadth, $keyword);
//     }

//     public function intro(string $title, string $structure): string {
//         return "Tu es " . $this->roleStructure . "

//         Tu dois écrire une **INTRODUCTION HTML** pour un article intitulé « $title ».

//         Voici la structure complète du cocon sémantique auquel cet article appartient :

//         $structure

//         " . $this->getRuleIntro();
//     }

//     public function development(string $title, string $structure): string {
//         return "Tu es " . $this->roleStructure . "

//         Tu dois écrire un bloc de DÉVELOPPEMENT HTML pour un article intitulé « $title ».

//         Voici la structure du cocon sémantique global :

//         $structure

//         " . $this->getRuleDevelopment();
//     }

//     public function conclusion(string $title, string $structure): string {
//         return "Tu es " . $this->roleStructure . "
//         Tu dois écrire une CONCLUSION HTML pour l’article intitulé « $title ».
//         Structure du cocon sémantique complet :
//         $structure
//         " . $this->getRuleConclusion();
//     }

//     public function image(string $keyword, string $title): string {
//         return "Tu es " . $this->roleImage . "
//         Donne une description d’image pour illustrer un article intitulé « $title » dans le contexte du mot-clé « $keyword ».
//         " . $this->getRuleImage();
//     }

//     public function fullArticle(string $keyword, string $title, string $structure, array $subparts): string {
//         $parties_formatees = '';
//         $index = 1;

//         foreach ($subparts as $titre => $lien) {
//             $parties_formatees .= "- Partie $index : « $titre »";
//             if (!is_null($lien)) {
//                 $parties_formatees .= " (inclure le lien : $lien)";
//             }
//             $parties_formatees .= "\n";
//             $index++;
//         }

//         $prompt = "Tu es " . $this->roleStructure . ".
//         Titre de l’article : « $title »

//         Voici la structure du cocon sémantique auquel appartient cet article :
//         $structure

//         Parties à respecter :
//         $parties_formatees

//         " . $this->getRuleFull($keyword, count($subparts));
//         return $prompt;
//     }


// } 
