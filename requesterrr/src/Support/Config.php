<?php
declare(strict_types=1);

namespace Requesterrr\Support;

final class Config
{
    private array $values;

    public function __construct(array $values)
    {
        $this->values = $values;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->values[$key] ?? $default;
    }

    public function getString(string $key, string $default = ''): string
    {
        $value = $this->get($key, $default);
        return is_string($value) ? $value : (string) $value;
    }

    public function getInt(string $key, int $default = 0): int
    {
        $value = $this->get($key, $default);
        return (int) $value;
    }

    public function getBool(string $key, bool $default = false): bool
    {
        $value = $this->get($key, $default);
        if (is_bool($value)) {
            return $value;
        }

        $normalized = strtolower(trim((string) $value));
        return in_array($normalized, ['1', 'true', 'yes', 'on'], true);
    }

    public function getList(string $key, string $separator = ','): array
    {
        $value = $this->getString($key);
        if ($value === '') {
            return [];
        }

        $parts = array_map('trim', explode($separator, $value));
        return array_values(array_filter($parts, static fn (string $part): bool => $part !== ''));
    }
}

