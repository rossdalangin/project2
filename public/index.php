<?php

// PSR-4 Autoloader
spl_autoload_register(function ($class) {
    $prefix = 'App\\';
    $baseDir = __DIR__ . '/../App/';

    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }

    $relativeClass = substr($class, $len);
    $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';

    if (file_exists($file)) {
        require $file;
    }
});

// Initialize session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Router
$requestUri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$requestMethod = $_SERVER['REQUEST_METHOD'] ?? 'GET';

$controller = new \App\Controllers\LeadController();

if ($requestMethod === 'GET' && ($requestUri === '/' || $requestUri === '/index.php')) {
    $controller->index();
} elseif ($requestMethod === 'POST' && ($requestUri === '/submit' || $requestUri === '/index.php/submit')) {
    $controller->handleSubmission();
} else {
    http_response_code(404);
    header('Content-Type: application/json');
    echo json_encode([
        'status' => 'error',
        'message' => '404 Not Found'
    ]);
}
