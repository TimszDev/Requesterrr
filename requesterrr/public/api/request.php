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

    $service = App::requestService($projectRoot);
    $result = $service->submit($body);
    $status = $result['success'] ? 200 : 400;
    requesterrr_json_response($result, $status);
} catch (Throwable $throwable) {
    requesterrr_json_response([
        'success' => false,
        'error' => 'Request submission failed: ' . $throwable->getMessage(),
        'data' => null,
    ], 500);
}

