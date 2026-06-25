<?php
declare(strict_types=1);

namespace App\Controllers;

/**
 * Authentication controller for student login/logout.
 *
 * Handles login form display, credential verification with
 * password_verify(), session creation with fixation prevention,
 * and logout with session destruction.
 *
 * Follows the same pattern as AuthController but for the 'student' role.
 *
 * Threat mitigations:
 *   - T-02F-01: Session-based role check on every student page
 *   - T-02F-04: htmlspecialchars() on all output
 *   - T-02F-06: CSRF validation on POST
 */
class StudentAuthController
{
    /**
     * Display the student login form.
     * Redirects to /quiz if already logged in as student.
     */
    public function loginFormAction(): void
    {
        // If already authenticated as student, redirect to quiz listing
        if (isset($_SESSION['role']) && $_SESSION['role'] === 'student') {
            header('Location: /quiz');
            exit;
        }

        // Generate CSRF token
        $csrfToken = $this->generateCsrfToken();

        $pageTitle = 'Student Login';
        require __DIR__ . '/../../views/student/login.php';
    }

    /**
     * Process login form submission.
     * Validates credentials against the users table WHERE role='student'.
     */
    public function loginAction(): void
    {
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';

        // Basic validation
        if ($username === '' || $password === '') {
            $csrfToken = $this->generateCsrfToken();
            $pageTitle = 'Student Login';
            $error = 'Please enter both username and password';
            require __DIR__ . '/../../views/student/login.php';
            return;
        }

        try {
            $pdo = \getConnection();
            $stmt = $pdo->prepare(
                'SELECT * FROM users WHERE username = :username AND role = \'student\' LIMIT 1'
            );
            $stmt->execute([':username' => $username]);
            $row = $stmt->fetch();

            if ($row && \password_verify($password, $row['password_hash'])) {
                // Session fixation prevention
                \session_regenerate_id(true);

                $_SESSION['user_id'] = (int) $row['id'];
                $_SESSION['username'] = $row['username'];
                $_SESSION['full_name'] = $row['full_name'];
                $_SESSION['role'] = 'student';
                $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

                header('Location: /quiz');
                exit;
            }

            // Invalid credentials — no hint about whether user exists
            $csrfToken = $this->generateCsrfToken();
            $pageTitle = 'Student Login';
            $error = 'Invalid credentials';
            require __DIR__ . '/../../views/student/login.php';

        } catch (\PDOException $e) {
            $csrfToken = $this->generateCsrfToken();
            $pageTitle = 'Student Login';
            $error = 'A system error occurred. Please try again later.';
            require __DIR__ . '/../../views/student/login.php';
        }
    }

    /**
     * Log out the current student.
     * Destroys session and redirects to login page.
     */
    public function logoutAction(): void
    {
        // Clear session data
        $_SESSION = [];

        // Delete session cookie
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params['path'],
                $params['domain'],
                $params['secure'],
                $params['httponly']
            );
        }

        // Destroy the session
        \session_destroy();

        header('Location: /student/login');
        exit;
    }

    /**
     * Generate a CSRF token, storing in session if not already present.
     */
    private function generateCsrfToken(): string
    {
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }
}
