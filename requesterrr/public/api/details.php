<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/src/bootstrap.php';

use Requesterrr\App;

try {
    $projectRoot = dirname(__DIR__, 2);
    $body = requesterrr_read_json();
    if (count($body) === 0) {
        $body = $_POST;
    }

    $service = App::metadataService($projectRoot);
    $result = $service->resolveSelection([
        'media_type' => $body['media_type'] ?? $body['type'] ?? '',
        'tmdb_id' => $body['tmdb_id'] ?? null,
        'imdb_id' => $body['imdb_id'] ?? null,
        'title' => $body['title'] ?? '',
        'year' => $body['year'] ?? null,
    ]);

    $status = $result['success'] ? 200 : 400;
    requesterrr_json_response($result, $status);
} catch (Throwable $throwable) {
    requesterrr_json_response([
        'success' => false,
        'error' => 'Details lookup failed: ' . $throwable->getMessage(),
        'item' => null,
    ], 500);
}

