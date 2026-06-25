<?php
declare(strict_types=1);

namespace App\Controllers;

/**
 * Base class for all student-facing controllers.
 *
 * Mirrors AdminController's auth and CSRF patterns but for the
 * 'student' role. Provides student-specific rendering through
 * views/layouts/student.php (no sidebar, minimal chrome per
 * Phase 2 context decisions).
 *
 * Threat mitigations:
 *   - T-02-001: requireStudentAuth() on every student action
 *   - T-02-002: requireCsrf() on all POST requests
 *   - T-02-006: Separate role check prevents student/admin cross-access
 */
class StudentController
{
    /**
     * Verify the user is authenticated as a student.
     * Redirects to /student/login if not.
     */
    protected function requireStudentAuth(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'student') {
            header('Location: /student/login');
            exit;
        }
    }

    /**
     * Render a view within the student layout.
     *
     * @param string $view      Path to the view file (relative to project root)
     * @param array  $data      Variables to extract into the view scope
     * @param string $pageTitle The page title
     */
    protected function render(string $view, array $data = [], string $pageTitle = 'Quiz'): void
    {
        $flash = $_SESSION['flash'] ?? null;
        unset($_SESSION['flash']);

        $csrfToken = $_SESSION['csrf_token'] ?? '';

        extract(['pageTitle' => $pageTitle, 'flash' => $flash, 'csrfToken' => $csrfToken] + $data, EXTR_SKIP);

        ob_start();
        require __DIR__ . '/../../' . $view;
        $content = ob_get_clean();

        require __DIR__ . '/../../views/layouts/student.php';
    }

    /**
     * Parse request body from JSON or form-encoded POST.
     * Handles JS fetch() sending Content-Type: application/json.
     *
     * @return array Parsed body data
     */
    protected function getRequestBody(): array
    {
        $contentType = $_SERVER['CONTENT_TYPE'] ?? $_SERVER['HTTP_CONTENT_TYPE'] ?? '';

        if (str_contains($contentType, 'application/json')) {
            $raw = file_get_contents('php://input');
            if ($raw === false || $raw === '') {
                return [];
            }
            $data = json_decode($raw, true);
            return is_array($data) ? $data : [];
        }

        return $_POST;
    }

    /**
     * Generate a CSRF token, storing in session if not already present.
     *
     * @return string
     */
    protected function generateCsrfToken(): string
    {
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }

    /**
     * Validate the submitted CSRF token against the session token.
     *
     * @return bool
     */
    protected function validateCsrfToken(): bool
    {
        $body = $this->getRequestBody();
        $token = $body['csrf_token'] ?? '';
        if ($token === '' || !isset($_SESSION['csrf_token'])) {
            return false;
        }
        return hash_equals($_SESSION['csrf_token'], $token);
    }

    /**
     * Require a valid CSRF token. Redirects on failure.
     */
    protected function requireCsrf(): void
    {
        if (!$this->validateCsrfToken()) {
            $_SESSION['flash'] = 'Invalid or expired form token. Please try again.';
            header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? '/quiz'));
            exit;
        }
    }
}
