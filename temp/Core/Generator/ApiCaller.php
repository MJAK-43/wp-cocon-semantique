<?php

namespace CSB\Core\Generator;

if (!defined('ABSPATH')) exit;

/**
 * Classe de base commune pour les API Callers.
 */
abstract class ApiCaller
{
    protected int $tokensUsed = 0;

    /**
     * Retourne le nombre total de tokens utilisés.
     */
    public function getTokensUsed(): int
    {
        return $this->tokensUsed;
    }

    /**
     * Réinitialise le compteur de tokens.
     */
    public function resetTokens(): void
    {
        $this->tokensUsed = 0;
    }

    /**
     * Fonction commune pour journaliser les erreurs.
     */
    protected function logError(string $context, string $message): void
    {
        error_log("❌ [$context] $message");
    }
}
