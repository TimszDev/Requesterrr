<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/src/bootstrap.php';

use Requesterrr\App;

try {
    $projectRoot = dirname(__DIR__, 2);
    $query = trim((string) ($_GET['q'] ?? ''));
    $limit = isset($_GET['limit']) ? (int) $_GET['limit'] : 18;
    $limit = max(1, min($limit, 40));

    if (strlen($query) < 2) {
        requesterrr_json_response([
            'success' => true,
            'error' => '',
            'results' => [],
        ]);
        exit;
    }

    $service = App::metadataService($projectRoot);
    $result = $service->search($query, $limit);
    $status = $result['success'] ? 200 : 400;
    requesterrr_json_response($result, $status);
} catch (Throwable $throwable) {
    requesterrr_json_response([
        'success' => false,
        'error' => 'Search failed: ' . $throwable->getMessage(),
        'results' => [],
    ], 500);
}

