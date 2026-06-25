<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Repositories\QuestionRepository;

/**
 * Controller for question CRUD operations with polymorphic type support.
 */
class QuestionController extends AdminController
{
    private QuestionRepository $repo;

    /** @var array<string> Valid question types */
    private const VALID_TYPES = ['mcq_single', 'mcq_multi', 'true_false', 'fill_blank', 'short_answer'];

    public function __construct()
    {
        $this->repo = new QuestionRepository();
    }

    /**
     * List all questions with type/topic filter.
     * GET /admin/questions
     */
    public function indexAction(): void
    {
        $this->requireAuth();

        $type = $_GET['type'] ?? null;
        $topicId = isset($_GET['topic_id']) && $_GET['topic_id'] !== '' ? (int) $_GET['topic_id'] : null;

        $questions = $this->repo->getAll($type, $topicId);
        $topics = $this->repo->getTopics();

        $this->render('views/questions/index.php', [
            'questions'       => $questions,
            'topics'          => $topics,
            'selectedType'    => $type,
            'selectedTopicId' => $topicId,
        ], 'Question Bank');
    }

    /**
     * Show the create question form with dynamic type selector.
     * GET /admin/questions/create
     */
    public function createFormAction(): void
    {
        $this->requireAuth();
        $this->generateCsrfToken();
        $topics = $this->repo->getTopics();
        $this->render('views/questions/create.php', ['topics' => $topics], 'Create Question');
    }

    /**
     * Store a newly created question.
     * POST /admin/questions/store
     */
    public function storeAction(): void
    {
        $this->requireAuth();
        $this->requireCsrf();

        $questionText = trim($_POST['question_text'] ?? '');
        $questionType = $_POST['question_type'] ?? '';
        $topicId = (int) ($_POST['topic_id'] ?? 0);
        $points = (float) ($_POST['points'] ?? 1.0);
        $explanation = trim($_POST['explanation'] ?? '');

        // Validation
        $errors = [];

        if ($questionText === '') {
            $errors[] = 'Question text is required.';
        }

        if (!in_array($questionType, self::VALID_TYPES, true)) {
            $errors[] = 'Invalid question type selected.';
        }

        if ($topicId <= 0) {
            $errors[] = 'Please select a topic.';
        }

        if ($points <= 0) {
            $errors[] = 'Points must be greater than 0.';
        }

        // Type-specific option validation
        $options = $this->extractOptionsFromPost($questionType, $errors);

        if (!empty($errors)) {
            $topics = $this->repo->getTopics();
            $this->render('views/questions/create.php', [
                'topics'   => $topics,
                'errors'   => $errors,
                'oldInput' => $_POST,
            ], 'Create Question');
            return;
        }

        $this->repo->create([
            'question_text' => $questionText,
            'question_type' => $questionType,
            'topic_id'      => $topicId,
            'points'        => $points,
            'explanation'   => $explanation !== '' ? $explanation : null,
            'options'       => $options,
        ]);

        $_SESSION['flash'] = 'Question created successfully.';
        header('Location: /admin/questions');
        exit;
    }

    /**
     * Show the edit question form with pre-populated values.
     * GET /admin/questions/edit/{id}
     */
    public function editFormAction(int $id): void
    {
        $this->requireAuth();
        $question = $this->repo->getById($id);

        if ($question === null) {
            http_response_code(404);
            echo '404 Not Found';
            return;
        }

        $this->generateCsrfToken();
        $topics = $this->repo->getTopics();
        $this->render('views/questions/edit.php', [
            'question' => $question,
            'topics'   => $topics,
        ], 'Edit Question');
    }

    /**
     * Update an existing question.
     * POST /admin/questions/update
     */
    public function updateAction(): void
    {
        $this->requireAuth();
        $this->requireCsrf();

        $id = (int) ($_POST['id'] ?? 0);
        $questionText = trim($_POST['question_text'] ?? '');
        $questionType = $_POST['question_type'] ?? '';
        $topicId = (int) ($_POST['topic_id'] ?? 0);
        $points = (float) ($_POST['points'] ?? 1.0);
        $explanation = trim($_POST['explanation'] ?? '');

        // Validation
        $errors = [];

        if ($id <= 0) {
            $errors[] = 'Invalid question ID.';
        }

        if ($questionText === '') {
            $errors[] = 'Question text is required.';
        }

        if (!in_array($questionType, self::VALID_TYPES, true)) {
            $errors[] = 'Invalid question type selected.';
        }

        if ($topicId <= 0) {
            $errors[] = 'Please select a topic.';
        }

        if ($points <= 0) {
            $errors[] = 'Points must be greater than 0.';
        }

        $options = $this->extractOptionsFromPost($questionType, $errors);

        if (!empty($errors)) {
            $question = $this->repo->getById($id) ?: [];
            $topics = $this->repo->getTopics();
            $this->render('views/questions/edit.php', [
                'question' => $question,
                'topics'   => $topics,
                'errors'   => $errors,
                'oldInput' => $_POST,
            ], 'Edit Question');
            return;
        }

        $this->repo->update($id, [
            'question_text' => $questionText,
            'question_type' => $questionType,
            'topic_id'      => $topicId,
            'points'        => $points,
            'explanation'   => $explanation !== '' ? $explanation : null,
            'options'       => $options,
        ]);

        $_SESSION['flash'] = 'Question updated successfully.';
        header('Location: /admin/questions');
        exit;
    }

    /**
     * Soft-delete a question.
     * POST /admin/questions/delete/{id}
     */
    public function deleteAction(int $id): void
    {
        $this->requireAuth();
        $this->requireCsrf();
        $this->repo->delete($id);
        $_SESSION['flash'] = 'Question deleted successfully.';
        header('Location: /admin/questions');
        exit;
    }

    /**
     * Extract type-specific options from POST data.
     *
     * @param  string   $questionType
     * @param  array    $errors       Reference to errors array — appends validation errors
     * @return array    Extracted options suitable for QuestionRepository
     */
    private function extractOptionsFromPost(string $questionType, array &$errors): array
    {
        $options = [];

        switch ($questionType) {
            case 'mcq_single':
            case 'mcq_multi':
                $optionTexts = $_POST['option_text'] ?? [];
                $correctFlags = $_POST['option_correct'] ?? [];
                $feedbackTexts = $_POST['option_feedback'] ?? [];

                $optionCount = is_array($optionTexts) ? count($optionTexts) : 0;
                if ($optionCount < 2) {
                    $errors[] = 'At least 2 options are required.';
                }

                $hasCorrect = false;
                for ($i = 0; $i < $optionCount; $i++) {
                    $text = trim($optionTexts[$i] ?? '');
                    if ($text === '') {
                        continue;
                    }
                    $isCorrect = isset($correctFlags[$i]) && (int) $correctFlags[$i] === 1;
                    if ($isCorrect) {
                        $hasCorrect = true;
                    }
                    $options[] = [
                        'option_text'   => $text,
                        'is_correct'    => $isCorrect,
                        'feedback_text' => trim($feedbackTexts[$i] ?? '') ?: null,
                        'sort_order'    => $i,
                    ];
                }

                if (!$hasCorrect) {
                    $errors[] = 'Please mark at least one option as correct.';
                }
                break;

            case 'true_false':
                $isCorrect = isset($_POST['tf_correct']) ? (int) $_POST['tf_correct'] : 1;
                $feedback = trim($_POST['tf_feedback'] ?? '');

                $options = [
                    'is_correct'    => $isCorrect,
                    'feedback_text' => $feedback !== '' ? $feedback : null,
                ];
                break;

            case 'fill_blank':
                $correctAnswer = trim($_POST['fb_answer'] ?? '');
                if ($correctAnswer === '') {
                    $errors[] = 'Correct answer is required for fill-in-the-blank questions.';
                }
                $alternatives = trim($_POST['fb_alternatives'] ?? '');

                $options = [
                    'correct_answer'      => $correctAnswer,
                    'alternative_answers' => $alternatives,
                    'feedback_text'       => trim($_POST['fb_feedback'] ?? '') ?: null,
                ];
                break;

            case 'short_answer':
                $keywords = $_POST['sa_keyword'] ?? [];
                $synonyms = $_POST['sa_synonyms'] ?? [];
                $requiredFlags = $_POST['sa_required'] ?? [];

                $keywordCount = is_array($keywords) ? count($keywords) : 0;
                if ($keywordCount === 0) {
                    $errors[] = 'At least one keyword is required for short answer questions.';
                }

                for ($i = 0; $i < $keywordCount; $i++) {
                    $kw = trim($keywords[$i] ?? '');
                    if ($kw === '') {
                        continue;
                    }
                    $options[] = [
                        'keyword'     => $kw,
                        'synonyms'    => trim($synonyms[$i] ?? ''),
                        'is_required' => isset($requiredFlags[$i]) ? 1 : 0,
                    ];
                }

                if (empty($options)) {
                    $errors[] = 'At least one keyword is required for short answer questions.';
                }
                break;
        }

        return $options;
    }
}
