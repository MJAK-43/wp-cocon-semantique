<?php
if (!defined('ABSPATH')) exit;

class PromptContext
{
    private array $context = [];
    private static $introDuctionOfContext="Contexte :";

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
        if (empty($this->context)) return "";

        $lines = [self::$introDuctionOfContext];
        foreach ($this->context as $key => $value) {
            $keyLabel = ucfirst(str_replace('_', ' ', $key));
            $lines[] = "- {$keyLabel} : « {$value} »";
        }
        return implode("\n", $lines) . "\n\n";
    }
}
