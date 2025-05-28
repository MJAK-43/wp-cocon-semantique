<?php
if (!defined('ABSPATH')) exit;

class PromptContext
{
    private array $context = [];
    private static $introDuctionOfContext="Sachant que l'éditeur de blog propose ";

    public function __construct(array $context = []) {
        $this->context = $context;
    }

    public function get(string $key): ?string {
        return $this->context[$key] ?? null;
    }

    public function set(string $key, string $value): void {
        $this->context[$key] = $value;
    }

    public function has(string $key): bool {
        return array_key_exists($key, $this->context);
    }

    public function all(): array {
        return $this->context;
    }

    public function toString(): string {
        if (empty($this->context)){
            //error_log("Contexte vide");
            return "";
        }
        else{
                    $produit = $this->context['produit'] ?? null;
        $public = $this->context['public'] ?? null;
        // error_log("produit $produit");
        // error_log("public $public");

        if (!$produit || !$public){
            //error_log("Vide produit ou public");
            return "";
        }
        else{
            //error_log("produit ou public");
        }       
        return self::$introDuctionOfContext . "les services suivants : $produit  à la cible suivante : $public.\n\n";
        }


    }

}
