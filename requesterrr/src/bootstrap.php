<?php
declare(strict_types=1);

$requesterrrProjectRoot = dirname(__DIR__);

spl_autoload_register(
    static function (string $class) use ($requesterrrProjectRoot): void {
        $prefix = 'Requesterrr\\';
        if (!str_starts_with($class, $prefix)) {
            return;
        }

        $relativeClass = substr($class, strlen($prefix));
        $relativePath = str_replace('\\', DIRECTORY_SEPARATOR, $relativeClass) . '.php';
        $fullPath = $requesterrrProjectRoot . '/src/' . $relativePath;

        if (is_file($fullPath)) {
            require_once $fullPath;
        }
    }
);

\Requesterrr\App::boot($requesterrrProjectRoot);

if (!function_exists('requesterrr_json_response')) {
    /**
     * @param array<mixed> $payload
     */
    function requesterrr_json_response(array $payload, int $statusCode = 200): void
    {
        http_response_code($statusCode);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($payload, JSON_UNESCAPED_SLASHES);
    }
}

if (!function_exists('requesterrr_read_json')) {
    /**
     * @return array<string,mixed>
     */
    function requesterrr_read_json(): array
    {
        $rawBody = file_get_contents('php://input');
        if (!is_string($rawBody) || $rawBody === '') {
            return [];
        }

        $decoded = json_decode($rawBody, true);
        return is_array($decoded) ? $decoded : [];
    }
}

