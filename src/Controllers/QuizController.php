<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Repositories\QuizRepository;
use App\Repositories\TopicRepository;
use App\Repositories\QuestionRepository;

/**
 * Controller for quiz CRUD with question bank assignment.
 */
class QuizController extends AdminController
{
    private QuizRepository $repo;

    public function __construct()
    {
        $this->repo = new QuizRepository();
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
     * List all quizzes.
     * GET /admin/quizzes
     */
    public function indexAction(): void
    {
        $this->requireAuth();
        $quizzes = $this->repo->getAll();
        $this->render('views/quizzes/index.php', ['quizzes' => $quizzes], 'Quizzes');
    }

    /**
     * Show the create quiz form with question picker.
     * GET /admin/quizzes/create
     */
    public function createFormAction(): void
    {
        $this->requireAuth();
        $this->generateCsrfToken();
        $topicRepo = new TopicRepository();
        $questionRepo = new QuestionRepository();

        $topics = $topicRepo->getAll();
        $allQuestions = $questionRepo->getAll();

        $this->render('views/quizzes/create.php', [
            'topics'       => $topics,
            'allQuestions' => $allQuestions,
        ], 'Create Quiz');
    }

    /**
     * Store a newly created quiz.
     * POST /admin/quizzes/store
     */
    public function storeAction(): void
    {
        $this->requireAuth();
        $this->requireCsrf();

        $title = trim($_POST['title'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $topicId = (int) ($_POST['topic_id'] ?? 0);
        $timeLimitMin = $_POST['time_limit_min'] ?? '';
        $passPercentage = (int) ($_POST['pass_percentage'] ?? 50);
        $isActive = !empty($_POST['is_active']);
        $questionIds = $_POST['question_ids'] ?? [];

        $errors = [];

        if ($title === '') {
            $errors[] = 'Quiz title is required.';
        }

        if ($topicId <= 0) {
            $errors[] = 'Please select a topic.';
        }

        if ($timeLimitMin !== '' && (!ctype_digit(ltrim($timeLimitMin, '+-')) || !is_numeric($timeLimitMin))) {
            $errors[] = 'Time limit must be a valid number.';
        } elseif ($timeLimitMin !== '') {
            $timeLimitNum = (int) $timeLimitMin;
            if ($timeLimitNum < 0) {
                $errors[] = 'Time limit must be 0 or greater.';
            }
            $timeLimitMin = (string) $timeLimitNum;
        }

        if ($passPercentage < 0 || $passPercentage > 100) {
            $errors[] = 'Passing score must be between 0 and 100.';
        }

        if (!empty($errors)) {
            $topicRepo = new TopicRepository();
            $questionRepo = new QuestionRepository();
            $topics = $topicRepo->getAll();
            $allQuestions = $questionRepo->getAll();

            $this->render('views/quizzes/create.php', [
                'topics'       => $topics,
                'allQuestions' => $allQuestions,
                'errors'       => $errors,
                'oldInput'     => $_POST,
            ], 'Create Quiz');
            return;
        }

        $this->repo->create([
            'title'           => $title,
            'description'     => $description !== '' ? $description : null,
            'topic_id'        => $topicId,
            'time_limit_min'  => $timeLimitMin !== '' ? $timeLimitMin : null,
            'pass_percentage' => $passPercentage,
            'is_active'       => $isActive,
            'question_ids'    => $questionIds,
        ]);

        $_SESSION['flash'] = 'Quiz created successfully.';
        header('Location: /admin/quizzes');
        exit;
    }

    /**
     * Show the edit quiz form with pre-filled values.
     * GET /admin/quizzes/edit/{id}
     */
    public function editFormAction(int $id): void
    {
        $this->requireAuth();
        $this->generateCsrfToken();
        $quiz = $this->repo->getById($id);

        if ($quiz === null) {
            http_response_code(404);
            echo '404 Not Found';
            return;
        }

        $topicRepo = new TopicRepository();
        $questionRepo = new QuestionRepository();
        $topics = $topicRepo->getAll();
        $allQuestions = $questionRepo->getAll();

        $this->render('views/quizzes/edit.php', [
            'quiz'         => $quiz,
            'topics'       => $topics,
            'allQuestions' => $allQuestions,
        ], 'Edit Quiz');
    }

    /**
     * Update an existing quiz.
     * POST /admin/quizzes/update
     */
    public function updateAction(): void
    {
        $this->requireAuth();
        $this->requireCsrf();

        $id = (int) ($_POST['id'] ?? 0);
        $title = trim($_POST['title'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $topicId = (int) ($_POST['topic_id'] ?? 0);
        $timeLimitMin = $_POST['time_limit_min'] ?? '';
        $passPercentage = (int) ($_POST['pass_percentage'] ?? 50);
        $isActive = !empty($_POST['is_active']);
        $questionIds = $_POST['question_ids'] ?? [];

        $errors = [];

        if ($id <= 0) {
            $errors[] = 'Invalid quiz ID.';
        }

        if ($title === '') {
            $errors[] = 'Quiz title is required.';
        }

        if ($topicId <= 0) {
            $errors[] = 'Please select a topic.';
        }

        if ($timeLimitMin !== '' && (!ctype_digit(ltrim($timeLimitMin, '+-')) || !is_numeric($timeLimitMin))) {
            $errors[] = 'Time limit must be a valid number.';
        } elseif ($timeLimitMin !== '') {
            $timeLimitNum = (int) $timeLimitMin;
            if ($timeLimitNum < 0) {
                $errors[] = 'Time limit must be 0 or greater.';
            }
            $timeLimitMin = (string) $timeLimitNum;
        }

        if ($passPercentage < 0 || $passPercentage > 100) {
            $errors[] = 'Passing score must be between 0 and 100.';
        }

        if (!empty($errors)) {
            $topicRepo = new TopicRepository();
            $questionRepo = new QuestionRepository();
            $quiz = $this->repo->getById($id) ?: [];
            $topics = $topicRepo->getAll();
            $allQuestions = $questionRepo->getAll();

            $this->render('views/quizzes/edit.php', [
                'quiz'         => $quiz,
                'topics'       => $topics,
                'allQuestions' => $allQuestions,
                'errors'       => $errors,
                'oldInput'     => $_POST,
            ], 'Edit Quiz');
            return;
        }

        $this->repo->update($id, [
            'title'           => $title,
            'description'     => $description !== '' ? $description : null,
            'topic_id'        => $topicId,
            'time_limit_min'  => $timeLimitMin !== '' ? $timeLimitMin : null,
            'pass_percentage' => $passPercentage,
            'is_active'       => $isActive,
            'question_ids'    => $questionIds,
        ]);

        $_SESSION['flash'] = 'Quiz updated successfully.';
        header('Location: /admin/quizzes');
        exit;
    }

    /**
     * Delete a quiz.
     * POST /admin/quizzes/delete/{id}
     */
    public function deleteAction(int $id): void
    {
        $this->requireAuth();
        $this->requireCsrf();
        $this->repo->delete($id);
        $_SESSION['flash'] = 'Quiz deleted successfully.';
        header('Location: /admin/quizzes');
        exit;
    }

    /**
     * Show quiz detail with assigned questions.
     * GET /admin/quizzes/detail/{id}
     */
    public function detailAction(int $id): void
    {
        $this->requireAuth();
        $quiz = $this->repo->getById($id);

        if ($quiz === null) {
            http_response_code(404);
            echo '404 Not Found';
            return;
        }

        $this->render('views/quizzes/detail.php', ['quiz' => $quiz], htmlspecialchars($quiz['title']));
    }
}
