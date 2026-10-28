<?php

namespace App\Controllers;

use App\Models\LeadModel;

class LeadController
{
    private ?LeadModel $leadModel;
    private string $logFilePath;

    public function __construct(?LeadModel $leadModel = null, ?string $logFilePath = null)
    {
        $this->leadModel = $leadModel;
        $this->logFilePath = $logFilePath ?? __DIR__ . '/../../storage/logs/submissions.json';
    }

    private function getLeadModel(): LeadModel
    {
        if ($this->leadModel === null) {
            $this->leadModel = new LeadModel();
        }
        return $this->leadModel;
    }

    /**
     * Generate or return existing CSRF token for the current session.
     */
    public function generateCsrfToken(): string
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }

    /**
     * Validate CSRF token against session token.
     */
    public function validateCsrfToken(?string $token): bool
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        if (empty($_SESSION['csrf_token']) || empty($token)) {
            return false;
        }
        return hash_equals($_SESSION['csrf_token'], $token);
    }

    /**
     * Render lead submission form HTML.
     */
    public function index(): void
    {
        $csrfToken = $this->generateCsrfToken();
        $message = $_GET['message'] ?? '';
        $status = $_GET['status'] ?? '';

        if (!headers_sent()) {
            header('Content-Type: text/html; charset=utf-8');
        }
        echo <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lead Intake Form</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif; background: #f4f6f9; margin: 0; padding: 40px 20px; display: flex; justify-content: center; }
        .card { background: #ffffff; border-radius: 8px; box-shadow: 0 4px 12px rgba(0,0,0,0.08); width: 100%; max-width: 500px; padding: 30px; }
        h2 { margin-top: 0; color: #1a202c; font-size: 1.5rem; }
        .form-group { margin-bottom: 18px; }
        label { display: block; margin-bottom: 6px; font-weight: 600; font-size: 0.9rem; color: #4a5568; }
        input[type="text"], input[type="email"], textarea { width: 100%; padding: 10px 12px; border: 1px solid #cbd5e0; border-radius: 6px; font-size: 1rem; box-sizing: border-box; }
        textarea { resize: vertical; height: 100px; }
        button { background-color: #3182ce; color: #ffffff; border: none; padding: 12px 20px; font-size: 1rem; font-weight: 600; border-radius: 6px; cursor: pointer; width: 100%; }
        button:hover { background-color: #2b6cb0; }
        .alert { padding: 12px 16px; border-radius: 6px; margin-bottom: 20px; font-size: 0.95rem; }
        .alert-success { background-color: #c6f6d5; color: #22543d; border: 1px solid #9ae6b4; }
        .alert-error { background-color: #fed7d7; color: #742a2a; border: 1px solid #feb2b2; }
        .hp-field { display: none !important; opacity: 0; position: absolute; left: -9999px; }
    </style>
</head>
<body>
    <div class="card">
        <h2>Submit Lead Information</h2>
HTML;

        if ($message !== '') {
            $alertClass = $status === 'success' ? 'alert-success' : 'alert-error';
            echo '<div class="' . htmlspecialchars($alertClass, ENT_QUOTES, 'UTF-8') . '">' . htmlspecialchars($message, ENT_QUOTES, 'UTF-8') . '</div>';
        }

        echo <<<HTML
        <form action="/submit" method="POST">
            <input type="hidden" name="csrf_token" value="{$csrfToken}">

            <!-- Honeypot Field -->
            <div class="hp-field">
                <label for="website_hp">Website</label>
                <input type="text" id="website_hp" name="website_hp" tabindex="-1" autocomplete="off">
            </div>

            <div class="form-group">
                <label for="name">Full Name *</label>
                <input type="text" id="name" name="name" required>
            </div>

            <div class="form-group">
                <label for="email">Email Address *</label>
                <input type="email" id="email" name="email" required>
            </div>

            <div class="form-group">
                <label for="phone">Phone Number</label>
                <input type="text" id="phone" name="phone">
            </div>

            <div class="form-group">
                <label for="company">Company</label>
                <input type="text" id="company" name="company">
            </div>

            <div class="form-group">
                <label for="message">Message</label>
                <textarea id="message" name="message"></textarea>
            </div>

            <button type="submit">Submit Lead</button>
        </form>
    </div>
</body>
</html>
HTML;
    }

    /**
     * Process lead submission.
     */
    public function handleSubmission(): void
    {
        // 1. Spam Defense: Honeypot check
        $honeypot = $_POST['website_hp'] ?? null;
        if (!empty($honeypot)) {
            // Silently reject bot submissions
            $this->logActivity('rejected_bot_honeypot', ['ip' => $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1']);
            $this->respond(200, ['status' => 'success', 'message' => 'Lead submitted successfully!']);
            return;
        }

        // 2. CSRF Verification
        $csrfToken = $_POST['csrf_token'] ?? null;
        if (!$this->validateCsrfToken($csrfToken)) {
            $this->logActivity('csrf_failure', ['ip' => $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1']);
            $this->respond(403, ['status' => 'error', 'message' => 'CSRF verification failed. Invalid token.']);
            return;
        }

        // 3. Input Sanitization & Validation
        $name = filter_input(INPUT_POST, 'name', FILTER_SANITIZE_SPECIAL_CHARS);
        $emailRaw = filter_input(INPUT_POST, 'email', FILTER_DEFAULT);
        $phone = filter_input(INPUT_POST, 'phone', FILTER_SANITIZE_SPECIAL_CHARS);
        $company = filter_input(INPUT_POST, 'company', FILTER_SANITIZE_SPECIAL_CHARS);
        $message = filter_input(INPUT_POST, 'message', FILTER_SANITIZE_SPECIAL_CHARS);

        // Fallbacks if filter_input returns null (e.g. CLI environment)
        if ($name === null && isset($_POST['name'])) {
            $name = htmlspecialchars($_POST['name'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        }
        if ($emailRaw === null && isset($_POST['email'])) {
            $emailRaw = $_POST['email'];
        }
        if ($phone === null && isset($_POST['phone'])) {
            $phone = htmlspecialchars($_POST['phone'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        }
        if ($company === null && isset($_POST['company'])) {
            $company = htmlspecialchars($_POST['company'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        }
        if ($message === null && isset($_POST['message'])) {
            $message = htmlspecialchars($_POST['message'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        }

        $name = is_string($name) ? trim($name) : '';
        $email = filter_var(trim((string)$emailRaw), FILTER_VALIDATE_EMAIL);
        $phone = is_string($phone) ? trim($phone) : '';
        $company = is_string($company) ? trim($company) : '';
        $message = is_string($message) ? trim($message) : '';

        if (empty($name)) {
            $this->respond(400, ['status' => 'error', 'message' => 'Name is required.']);
            return;
        }

        if (!$email) {
            $this->respond(400, ['status' => 'error', 'message' => 'A valid email address is required.']);
            return;
        }

        $leadData = [
            'name'    => $name,
            'email'   => $email,
            'phone'   => $phone,
            'company' => $company,
            'message' => $message,
        ];

        // 4. Persistence
        try {
            $leadId = $this->getLeadModel()->create($leadData);
            $leadData['id'] = $leadId;
        } catch (\Exception $e) {
            $this->logActivity('db_error', ['error' => $e->getMessage()]);
            $this->respond(500, ['status' => 'error', 'message' => 'Database error encountered while saving lead.']);
            return;
        }

        // 5. Send Email Notification
        $this->dispatchEmailNotification($leadData);

        // 6. Log Activity to JSON File
        $this->logActivity('lead_submitted', $leadData);

        // 7. Response
        $this->respond(200, ['status' => 'success', 'message' => 'Lead submitted successfully!', 'lead_id' => $leadId]);
    }

    /**
     * Dispatch email notification for new lead.
     */
    private function dispatchEmailNotification(array $leadData): bool
    {
        $to = "admin@example.com";
        $subject = "New Lead Submission: " . $leadData['name'];
        $body = "New lead details:\n\n"
              . "Name: {$leadData['name']}\n"
              . "Email: {$leadData['email']}\n"
              . "Phone: {$leadData['phone']}\n"
              . "Company: {$leadData['company']}\n"
              . "Message: {$leadData['message']}\n";
        $headers = "From: no-reply@example.com\r\n"
                 . "Reply-To: {$leadData['email']}\r\n"
                 . "X-Mailer: PHP/" . phpversion();

        return @mail($to, $subject, $body, $headers);
    }

    /**
     * Log activity to local JSON file.
     */
    private function logActivity(string $event, array $data): void
    {
        $dir = dirname($this->logFilePath);
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }

        $logEntry = [
            'timestamp' => date('c'),
            'event'     => $event,
            'data'      => $data,
        ];

        $existingLogs = [];
        if (file_exists($this->logFilePath)) {
            $contents = file_get_contents($this->logFilePath);
            if ($contents) {
                $decoded = json_decode($contents, true);
                if (is_array($decoded)) {
                    $existingLogs = $decoded;
                }
            }
        }

        $existingLogs[] = $logEntry;

        file_put_contents($this->logFilePath, json_encode($existingLogs, JSON_PRETTY_PRINT), LOCK_EX);
    }

    /**
     * Respond with JSON or Redirect depending on request content-type / headers.
     */
    private function respond(int $statusCode, array $payload): void
    {
        if (!headers_sent()) {
            http_response_code($statusCode);
        }

        $acceptHeader = $_SERVER['HTTP_ACCEPT'] ?? '';
        $isJsonRequest = str_contains($acceptHeader, 'application/json') ||
                         (isset($_SERVER['CONTENT_TYPE']) && str_contains($_SERVER['CONTENT_TYPE'], 'application/json'));

        if ($isJsonRequest) {
            if (!headers_sent()) {
                header('Content-Type: application/json');
            }
            echo json_encode($payload);
            return;
        }

        // HTML Response / Redirect
        $queryParams = http_build_query([
            'status'  => $payload['status'],
            'message' => $payload['message'],
        ]);

        if (!headers_sent()) {
            header("Location: /?" . $queryParams);
        }
        echo json_encode($payload);
    }
}
