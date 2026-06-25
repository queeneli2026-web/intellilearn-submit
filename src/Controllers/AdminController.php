<?php
declare(strict_types=1);

namespace App\Controllers;

class AdminController
{
    protected function requireAuth(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'lecturer') {
            header('Location: /admin/login');
            exit;
        }
    }

    protected function render(string $view, array $data = [], string $pageTitle = 'Dashboard'): void
    {
        $flash = $_SESSION['flash'] ?? null;
        unset($_SESSION['flash']);

        $csrfToken = $_SESSION['csrf_token'] ?? '';

        extract(['pageTitle' => $pageTitle, 'flash' => $flash, 'csrfToken' => $csrfToken] + $data, EXTR_SKIP);

        ob_start();
        require __DIR__ . '/../../' . $view;
        $content = ob_get_clean();

        require __DIR__ . '/../../views/layouts/admin.php';
    }

    protected function generateCsrfToken(): string
    {
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }

    protected function validateCsrfToken(): bool
    {
        $token = $_POST['csrf_token'] ?? '';
        if ($token === '' || !isset($_SESSION['csrf_token'])) {
            return false;
        }
        return hash_equals($_SESSION['csrf_token'], $token);
    }

    protected function requireCsrf(): void
    {
        if (!$this->validateCsrfToken()) {
            $_SESSION['flash'] = 'Invalid or expired form token. Please try again.';
            header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? '/admin'));
            exit;
        }
    }
}
