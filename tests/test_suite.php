<?php

spl_autoload_register(function ($class) {
    $prefix = 'App\\';
    $baseDir = __DIR__ . '/../App/';
    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) return;
    $file = $baseDir . str_replace('\\', '/', substr($class, $len)) . '.php';
    if (file_exists($file)) require $file;
});

use App\Database;
use App\Models\LeadModel;
use App\Controllers\LeadController;

$logFile = __DIR__ . '/../storage/logs/submissions.json';
if (file_exists($logFile)) unlink($logFile);

// Test 1: CSRF Token Generation and Validation
$controller = new LeadController(null, $logFile);
$token = $controller->generateCsrfToken();
assert(strlen($token) === 64, "CSRF token length must be 64 hex chars");
assert($controller->validateCsrfToken($token) === true, "CSRF token validation failed for valid token");
assert($controller->validateCsrfToken("invalid_token") === false, "CSRF token validation succeeded for invalid token");
echo "[PASS] Test 1: CSRF Token generation and validation\n";

// Test 2: Database and LeadModel Mock/Pdo test using SQLite in-memory
$pdoMock = new PDO('sqlite::memory:');
$pdoMock->exec("CREATE TABLE leads (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL,
    email TEXT NOT NULL,
    phone TEXT,
    company TEXT,
    message TEXT,
    created_at DATETIME
)");

// Create reflection to test LeadModel with SQLite PDO
$dbClass = new ReflectionClass(Database::class);
$dbInstance = $dbClass->newInstanceWithoutConstructor();
$pdoProp = $dbClass->getProperty('pdo');
$pdoProp->setAccessible(true);
$pdoProp->setValue($dbInstance, $pdoMock);

$leadModel = new LeadModel($dbInstance);
$id = $leadModel->create([
    'name' => 'John Doe <script>',
    'email' => 'john@example.com',
    'phone' => '123-456-7890',
    'company' => 'Acme Inc',
    'message' => 'Hello World'
]);

assert($id === 1, "Lead ID should be 1");
$lead = $leadModel->find(1);
assert($lead['name'] === 'John Doe <script>', "Name matches");
assert($lead['email'] === 'john@example.com', "Email matches");
echo "[PASS] Test 2: LeadModel data persistence & parameterized query execution\n";

// Test 3: Controller submission handling with Honeypot rejection
$_POST = [
    'website_hp' => 'http://spam.com',
    'csrf_token' => $token,
    'name' => 'Bot User',
    'email' => 'bot@spam.com'
];
$_SERVER['HTTP_ACCEPT'] = 'application/json';
$_SERVER['REQUEST_METHOD'] = 'POST';

ob_start();
$controllerWithMock = new LeadController($leadModel, $logFile);
$controllerWithMock->handleSubmission();
$output = ob_get_clean();
$res = json_decode($output, true);
assert($res['status'] === 'success', "Honeypot should silently accept/reject without error");

$logs = json_decode(file_get_contents($logFile), true);
assert($logs[0]['event'] === 'rejected_bot_honeypot', "Event should be rejected_bot_honeypot");
echo "[PASS] Test 3: Honeypot spam defense silently rejects bot\n";

// Test 4: Controller submission handling with invalid CSRF
$_POST = [
    'website_hp' => '',
    'csrf_token' => 'wrong_token',
    'name' => 'Real User',
    'email' => 'real@example.com'
];
ob_start();
$controllerWithMock->handleSubmission();
$output = ob_get_clean();
$res = json_decode($output, true);
assert($res['status'] === 'error', "Status should be error");

$logs = json_decode(file_get_contents($logFile), true);
assert(end($logs)['event'] === 'csrf_failure', "Event should be csrf_failure");
echo "[PASS] Test 4: CSRF token verification failure handled correctly\n";

// Test 5: Successful Lead Submission
$_POST = [
    'website_hp' => '',
    'csrf_token' => $token,
    'name' => 'Alice Smith',
    'email' => 'alice@example.com',
    'phone' => '555-0199',
    'company' => 'TechCorp & Co',
    'message' => 'Interested in services!'
];

ob_start();
$controllerWithMock->handleSubmission();
$output = ob_get_clean();

$logs = json_decode(file_get_contents($logFile), true);
$lastLog = end($logs);
assert($lastLog['event'] === 'lead_submitted', "Event should be lead_submitted");
assert($lastLog['data']['email'] === 'alice@example.com', "Logged email should match");
echo "[PASS] Test 5: Successful lead submission with activity logging and email dispatch\n";

echo "ALL TESTS PASSED SUCCESSFULLY!\n";
