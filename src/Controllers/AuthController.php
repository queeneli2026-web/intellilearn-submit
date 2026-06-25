<?php
declare(strict_types=1);

namespace App\Controllers;

/**
 * Authentication controller for admin login/logout.
 *
 * Handles login form display, credential verification with
 * password_verify(), session creation with fixation prevention,
 * and logout with session destruction.
 *
 * Threat mitigations:
 *   - T-01-001: password_verify() with bcrypt hashes
 *   - T-01-003: session_regenerate_id(true) after login
 *   - T-01-005: Session role check on admin pages
 */
class AuthController
{
    /**
     * Display the login form.
     * Redirects to /admin/topics if already logged in as lecturer.
     */
    public function loginFormAction(): void
    {
        // If already authenticated as lecturer, redirect to admin dashboard
        if (isset($_SESSION['role']) && $_SESSION['role'] === 'lecturer') {
            header('Location: /admin/topics');
            exit;
        }

        $pageTitle = 'Lecturer Login';
        $this->renderLoginView($pageTitle);
    }

    /**
     * Process login form submission.
     * Validates credentials against the database using password_verify().
     */
    public function loginAction(): void
    {
        $username = $_POST['username'] ?? '';
        $password = $_POST['password'] ?? '';

        // Basic validation
        if ($username === '' || $password === '') {
            $pageTitle = 'Lecturer Login';
            $error = 'Please enter both username and password';
            $this->renderLoginView($pageTitle, $error, $username);
            return;
        }

        try {
            $pdo = \getConnection();
            $stmt = $pdo->prepare(
                'SELECT * FROM users WHERE username = :username AND role = \'lecturer\' LIMIT 1'
            );
            $stmt->execute([':username' => $username]);
            $row = $stmt->fetch();

            if ($row && \password_verify($password, $row['password_hash'])) {
                // T-01-003: Session fixation prevention
                \session_regenerate_id(true);

                $_SESSION['user_id'] = (int) $row['id'];
                $_SESSION['username'] = $row['username'];
                $_SESSION['full_name'] = $row['full_name'];
                $_SESSION['role'] = 'lecturer';
                $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

                header('Location: /admin/topics');
                exit;
            }

            // T-01-001: Invalid credentials — no hint about whether user exists
            $pageTitle = 'Lecturer Login';
            $error = 'Invalid credentials';
            $this->renderLoginView($pageTitle, $error, $username);

        } catch (\PDOException $e) {
            $pageTitle = 'Lecturer Login';
            $error = 'A system error occurred. Please try again later.';
            $this->renderLoginView($pageTitle, $error, $username);
        }
    }

    /**
     * Log out the current user.
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

        header('Location: /admin/login');
        exit;
    }

    /**
     * Render the login view with optional error message.
     */
    private function renderLoginView(
        string $pageTitle,
        ?string $error = null,
        ?string $username = null
    ): void {
        // Extract only known variables to avoid injection
        extract([
            'pageTitle' => $pageTitle,
            'error' => $error,
            'username' => $username,
        ], EXTR_SKIP);

        require __DIR__ . '/../../views/auth/login.php';
    }
}
