<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Repositories\AttemptRepository;
use App\Repositories\QuizRepository;

/**
 * Controller for student results viewing in the admin panel.
 */
class ResultController extends AdminController
{
    private AttemptRepository $repo;

    public function __construct()
    {
        $this->repo = new AttemptRepository();
    }

    /**
     * Render a view with common admin layout.
     *
     * @param string $view     Path to the view file (relative to project root)
     * @param array  $data     Variables to extract into the view scope
     * @param string $pageTitle The page title
     */
    private function render(string $view, array $data = [], string $pageTitle = 'Dashboard'): void
    {
        // Extract flash from session if present
        $flash = $_SESSION['flash'] ?? null;
        unset($_SESSION['flash']);

        // Extract data into local scope
        extract(['pageTitle' => $pageTitle, 'flash' => $flash] + $data, EXTR_SKIP);

        // Start output buffering for the content
        ob_start();
        require __DIR__ . '/../../' . $view;
        $content = ob_get_clean();

        // Render within admin layout
        require __DIR__ . '/../../views/layouts/admin.php';
    }

    /**
     * Show results summary table with stats and quiz filter.
     * GET /admin/results
     */
    public function indexAction(): void
    {
        $this->requireAuth();

        $quizId = isset($_GET['quiz_id']) && $_GET['quiz_id'] !== '' ? (int) $_GET['quiz_id'] : null;
        $attempts = $this->repo->getAllByQuiz($quizId);
        $quizRepo = new QuizRepository();
        $quizzes = $quizRepo->getAll();
        $stats = $this->repo->getStats();

        $this->render('views/results/index.php', [
            'attempts'       => $attempts,
            'quizzes'        => $quizzes,
            'selectedQuizId' => $quizId,
            'stats'          => $stats,
        ], 'Results');
    }

    /**
     * Show attempt detail with per-question breakdown.
     * GET /admin/results/detail/{id}
     */
    public function detailAction(int $id): void
    {
        $this->requireAuth();
        $attempt = $this->repo->getById($id);

        if ($attempt === null) {
            http_response_code(404);
            echo '404 Not Found';
            return;
        }

        $this->render('views/results/detail.php', [
            'attempt' => $attempt,
        ], 'Attempt Detail - ' . htmlspecialchars($attempt['student_name'] ?? ''));
    }
}
