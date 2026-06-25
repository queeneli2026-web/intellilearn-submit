<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Services\AnalyticsService;

class PerformanceController extends StudentController
{
    private AnalyticsService $analyticsService;

    public function __construct()
    {
        $this->analyticsService = new AnalyticsService();
    }

    public function dashboardAction(): void
    {
        $this->requireStudentAuth();

        $userId = (int) ($_SESSION['user_id'] ?? 0);
        $page = isset($_GET['page']) ? max(1, (int) $_GET['page']) : 1;

        $data = $this->analyticsService->getDashboardData($userId, $page);

        $totalPages = $data['totalAttempts'] > 0 ? (int) ceil($data['totalAttempts'] / 10) : 1;

        $this->render('views/performance/dashboard.php', [
            'attempts' => $data['attempts'],
            'totalAttempts' => $data['totalAttempts'],
            'currentPage' => $page,
            'totalPages' => $totalPages,
            'topicAccuracy' => $data['topicAccuracy'],
            'trend' => $data['trend'],
            'mastery' => $data['mastery'],
        ], 'Performance Dashboard');
    }
}
