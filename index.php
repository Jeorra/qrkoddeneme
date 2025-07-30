<?php

require_once __DIR__ . '/api/ApiController.php';

// Simple router based on URL path
$requestUri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$controller = new App\ApiController();

header('Content-Type: application/json');

switch ($requestUri) {
    case '/api/session/new':
        $controller->getNewSession();
        break;

    case '/api/qr-image':
        header_remove('Content-Type');
        $controller->generateQrImage();
        break;

    case '/api/confirm-scan':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['error' => 'Method Not Allowed']);
            exit;
        }
        $controller->confirmScan();
        break;

    default:
        http_response_code(404);
        echo json_encode(['error' => 'Not Found']);
        break;
}
