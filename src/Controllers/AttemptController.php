<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Repositories\AttemptRepository;
use App\Repositories\StudentQuizRepository;
use App\Repositories\QuestionRepository;
use App\Services\AnalyticsService;
use App\Services\QuizEvaluationService;

/**
 * Controller for student quiz-taking actions.
 *
 * Handles the full attempt lifecycle: start, submit answers,
 * finish, resume detection, and cancel. All actions require
 * student authentication; all POST actions require CSRF validation.
 *
 * Server-authoritative timer enforcement per QUIZ-07, D-10:
 * timer check runs on EVERY answer submission and finish, not
 * just client-side. Client timer is display only.
 *
 * Threat mitigations:
 *   - T-02-001: requireStudentAuth() on every action
 *   - T-02-002: requireCsrf() on all POST; status check before mutations
 *   - T-02-004: getAttemptByIdForUser validates ownership
 *   - T-02-005: Server-authoritative timer (started_at + time_limit_min)
 *   - T-02-007: Status !== 'in_progress' rejection on mutations
 *   - T-02-008: UPSERT only, no deletion of existing rows
 */
class AttemptController extends StudentController
{
    private AttemptRepository $attemptRepo;
    private StudentQuizRepository $studentQuizRepo;
    private QuestionRepository $questionRepo;

    public function __construct()
    {
        $this->attemptRepo = new AttemptRepository();
        $this->studentQuizRepo = new StudentQuizRepository();
        $this->questionRepo = new QuestionRepository();
    }

    /**
     * Helper: send a JSON response with proper content-type header.
     *
     * @param mixed $data
     * @param int   $statusCode HTTP status code
     */
    private function jsonResponse(mixed $data, int $statusCode = 200): void
    {
        http_response_code($statusCode);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE);
    }

    /**
     * Helper: check if the attempt timer has expired.
     *
     * If expired, marks attempt as timed_out and returns true.
     *
     * @param  array  $attempt    Attempt row with started_at and time_limit_min
     * @param  int    $attemptId
     * @return bool   True if expired
     */
    private function checkTimerExpired(array $attempt, int $attemptId): bool
    {
        if ($attempt['time_limit_min'] === null) {
            return false; // No time limit
        }

        $startedAt = strtotime($attempt['started_at']);
        $deadline = $startedAt + ((int) $attempt['time_limit_min'] * 60);

        if (time() > $deadline) {
            $this->attemptRepo->markAsTimedOut($attemptId);
            return true;
        }

        return false;
    }

    /**
     * Start a new quiz attempt.
     * POST /quiz/attempt/start/{quizId}
     *
     * @param  int $quizId
     */
    public function startAction(int $quizId): void
    {
        $this->requireStudentAuth();
        $this->requireCsrf();

        $userId = (int) ($_SESSION['user_id'] ?? 0);

        // Load quiz with all questions
        $quiz = $this->studentQuizRepo->getQuizWithQuestions($quizId);

        if ($quiz === null || empty($quiz['is_active'])) {
            $this->jsonResponse(['error' => 'Quiz not found or not active.'], 404);
            return;
        }

        // Check max attempts
        $maxAttempts = (int) ($quiz['max_attempts'] ?? 1);
        $currentAttempts = $this->attemptRepo->getMaxAttemptNumber($userId, $quizId);
        if ($currentAttempts >= $maxAttempts) {
            $this->jsonResponse(['error' => 'Maximum attempts reached for this quiz.'], 403);
            return;
        }

        $questions = $quiz['questions'] ?? [];

        if (empty($questions)) {
            $this->jsonResponse(['error' => 'Quiz has no questions.'], 400);
            return;
        }

        // Shuffle question IDs using Fisher-Yates (anti-cheating Tier 1)
        $questionIds = array_map(fn(array $q): int => (int) $q['id'], $questions);
        $count = count($questionIds);
        for ($i = $count - 1; $i > 0; $i--) {
            $j = random_int(0, $i);
            $temp = $questionIds[$i];
            $questionIds[$i] = $questionIds[$j];
            $questionIds[$j] = $temp;
        }
        $questionOrderJson = json_encode($questionIds);

        // Create attempt
        $attemptNumber = $currentAttempts + 1;
        $attemptId = $this->attemptRepo->createAttempt(
            $userId,
            $quizId,
            $attemptNumber,
            $questionOrderJson
        );

        // Get first question data
        $firstQuestionId = $questionIds[0];
        $firstQuestion = null;
        foreach ($questions as $q) {
            if ((int) $q['id'] === $firstQuestionId) {
                $firstQuestion = $q;
                break;
            }
        }

        // Compute ends_at for timer display
        $startedAt = time();
        $endsAt = $quiz['time_limit_min'] !== null
            ? $startedAt + ((int) $quiz['time_limit_min'] * 60)
            : null;

        $this->jsonResponse([
            'attempt_id'          => $attemptId,
            'quiz'                => [
                'title'           => $quiz['title'],
                'time_limit_min'  => $quiz['time_limit_min'],
                'total_questions' => count($questions),
            ],
            'current_question'    => [
                'index'          => 0,
                'question_id'    => $firstQuestionId,
                'question_text'  => $firstQuestion['question_text'] ?? '',
                'question_type'  => $firstQuestion['question_type'] ?? '',
                'options'        => $firstQuestion['options'] ?? [],
                'points'         => $firstQuestion['points_override'] ?? $firstQuestion['points'] ?? 1.0,
            ],
            'started_at'          => date('Y-m-d\TH:i:s', $startedAt),
            'ends_at'             => $endsAt !== null ? date('Y-m-d\TH:i:s', $endsAt) : null,
        ]);
    }

    /**
     * Submit an answer for a question within an attempt.
     * POST /quiz/attempt/{attemptId}/answer
     *
     * @param  int $attemptId
     */
    public function submitAnswerAction(int $attemptId): void
    {
        $this->requireStudentAuth();
        $this->requireCsrf();

        $userId = (int) ($_SESSION['user_id'] ?? 0);

        // Load attempt (validates ownership — T-02-001, T-02-004)
        $attempt = $this->attemptRepo->getAttemptByIdForUser($attemptId, $userId);

        if ($attempt === null) {
            $this->jsonResponse(['error' => 'Attempt not found.'], 404);
            return;
        }

        // Status check: only in_progress accepts answers (T-02-002, T-02-007)
        if ($attempt['status'] !== 'in_progress') {
            $this->jsonResponse(['error' => 'This attempt is no longer active.'], 400);
            return;
        }

        // Server-authoritative timer check (QUIZ-07, D-10)
        if ($this->checkTimerExpired($attempt, $attemptId)) {
            $this->jsonResponse(['error' => 'Time has expired.'], 400);
            return;
        }

        // Parse POST body (supports both JSON and form-encoded)
        $body = $this->getRequestBody();
        $questionId = (int) ($body['question_id'] ?? 0);
        $selectedOptionId = isset($body['selected_option_id']) && $body['selected_option_id'] !== ''
            ? (int) $body['selected_option_id'] : null;
        $answerText = isset($body['answer_text']) && $body['answer_text'] !== ''
            ? $body['answer_text'] : null;
        $timeTakenSec = isset($body['time_taken_sec']) ? (int) $body['time_taken_sec'] : 0;

        if ($questionId <= 0) {
            $this->jsonResponse(['error' => 'Invalid question ID.'], 400);
            return;
        }

        // Save answer (UPSERT for idempotent re-save — T-02-008)
        $this->attemptRepo->saveAnswer(
            $attemptId,
            $questionId,
            $selectedOptionId,
            $answerText,
            $timeTakenSec
        );

        // Load question data with options for evaluation
        $questionData = $this->questionRepo->getById($questionId);

        if ($questionData === null) {
            $this->jsonResponse(['error' => 'Question not found.'], 404);
            return;
        }

        // Build submittedAnswer structure for evaluation
        $submittedAnswer = [
            'selected_option_id' => $selectedOptionId,
            'answer_text'        => $answerText,
        ];

        // Evaluate answer
        $evaluation = QuizEvaluationService::evaluateAnswer(
            $questionData['question_type'],
            $questionData,
            $submittedAnswer,
            null // points_override handled via evaluateAnswer scaling
        );

        // Update response with evaluation results (including needs_review for SANS-03)
        $this->attemptRepo->evaluateAndUpdateResponse(
            $attemptId,
            $questionId,
            $evaluation['is_correct'],
            $evaluation['points_earned'],
            $evaluation['needs_review'] ?? false
        );

        // Compute progress
        $questionOrder = $this->attemptRepo->getQuestionOrder($attemptId);
        $totalQuestions = $questionOrder !== null ? count(json_decode($questionOrder, true)) : 0;

        // Get answered count from responses
        $responses = $this->attemptRepo->getResponsesForAttempt($attemptId);
        $answeredCount = 0;
        foreach ($responses as $r) {
            if ($r['selected_option_id'] !== null || ($r['answer_text'] !== null && $r['answer_text'] !== '')) {
                $answeredCount++;
            }
        }

        $progressPercentage = $totalQuestions > 0
            ? round(($answeredCount / $totalQuestions) * 100, 0)
            : 0;

        // Build feedback message
        $feedbackMessage = match ($evaluation['feedback_type']) {
            'correct'        => 'Correct!',
            'incorrect'      => 'Incorrect',
            'pending_review' => 'Pending Review',
            'partial'        => 'Partially Correct',
            default          => 'Evaluated',
        };

        $this->jsonResponse([
            'correct'      => $evaluation['is_correct'],
            'feedback'     => [
                'type'          => $evaluation['feedback_type'],
                'message'       => $feedbackMessage,
                'explanation'   => $questionData['explanation'] ?? '',
                'points_earned' => $evaluation['points_earned'],
                'max_points'    => $evaluation['max_points'],
            ],
            'needs_review' => $evaluation['needs_review'] ?? false,
            'progress'     => [
                'answered'   => $answeredCount,
                'total'      => $totalQuestions,
                'percentage' => $progressPercentage,
            ],
        ]);
    }

    /**
     * Finish an attempt and calculate final score.
     * POST /quiz/attempt/{attemptId}/finish
     *
     * @param  int $attemptId
     */
    public function finishAction(int $attemptId): void
    {
        $this->requireStudentAuth();
        $this->requireCsrf();

        $userId = (int) ($_SESSION['user_id'] ?? 0);

        // Load attempt (validates ownership)
        $attempt = $this->attemptRepo->getAttemptByIdForUser($attemptId, $userId);

        if ($attempt === null) {
            $this->jsonResponse(['error' => 'Attempt not found.'], 404);
            return;
        }

        if ($attempt['status'] !== 'in_progress') {
            $this->jsonResponse(['error' => 'This attempt is no longer active.'], 400);
            return;
        }

        // Server-authoritative timer check
        if ($this->checkTimerExpired($attempt, $attemptId)) {
            // Return the timed_out result instead of error
            $this->jsonResponse([
                'attempt_id' => $attemptId,
                'status'     => 'timed_out',
                'message'    => 'Time has expired.',
            ], 400);
            return;
        }

        // Load all responses
        $responses = $this->attemptRepo->getResponsesForAttempt($attemptId);

        // Get question order to find unanswered questions
        $questionOrderJson = $this->attemptRepo->getQuestionOrder($attemptId);
        $questionIds = $questionOrderJson !== null
            ? json_decode($questionOrderJson, true)
            : [];

        // Find answered question IDs
        $answeredIds = array_map(fn(array $r): int => (int) $r['question_id'], $responses);

        // Insert zero-score responses for unanswered questions
        $questionRepo = $this->questionRepo;
        foreach ($questionIds as $qId) {
            if (!in_array((int) $qId, $answeredIds, true)) {
                // No answer for this question — insert null response
                $this->attemptRepo->saveAnswer($attemptId, (int) $qId, null, null, 0);
                // Evaluate as incorrect with 0 points
                $this->attemptRepo->evaluateAndUpdateResponse($attemptId, (int) $qId, false, 0.0);
            }
        }

        // Reload responses after filling in blanks
        $responses = $this->attemptRepo->getResponsesForAttempt($attemptId);

        // Calculate total max points from quiz questions
        $maxPossible = 0.0;
        foreach ($responses as $r) {
            $maxPossible += (float) ($r['max_points'] ?? 1.0);
        }

        // Calculate score
        $passPercentage = (float) ($attempt['pass_percentage'] ?? 50);
        $scoreResult = QuizEvaluationService::calculateScore(
            $responses,
            $maxPossible,
            $passPercentage
        );

        // Save final score
        $this->attemptRepo->updateAttemptScore(
            $attemptId,
            $scoreResult['score'],
            $scoreResult['maxScore'],
            $scoreResult['percentage'],
            $scoreResult['passed']
        );

        // Compute time taken
        $startedAt = strtotime($attempt['started_at']);
        $timeTakenSec = time() - $startedAt;

        // Recalculate topic mastery for all topics covered by this quiz
        $quizId = (int) ($attempt['quiz_id'] ?? 0);
        if ($quizId > 0) {
            $topicIds = $this->questionRepo->getTopicIdsByQuiz($quizId);
            $analytics = new AnalyticsService();
            foreach ($topicIds as $tid) {
                $analytics->recalculateMastery($userId, (int) $tid);
            }
        }

        // Gamification hooks: spaced repetition, XP, streak, badges
        $correctCount = 0;
        foreach ($responses as $r) {
            if (!empty($r['is_correct'])) $correctCount++;
        }

        $gamification = new \App\Services\GamificationService();
        $spaced = new \App\Services\SpacedRepetitionService();

        // Schedule SM-2 review
        if ($quizId > 0) {
            $spaced->scheduleAfterQuiz($userId, $quizId);
        }

        // Award XP: 50 base + 10 per correct answer
        $xpAmount = 50 + ($correctCount * 10);
        $gamification->addXp($userId, $xpAmount);

        // Update streak
        $gamification->updateStreak($userId);

        // Check and award badges
        $gamification->checkAndAwardBadges($userId, $quizId);

        // Refresh session data for navbar display
        $freshStats = $gamification->getGamificationData($userId);
        $_SESSION['xp'] = $freshStats['stats']['xp'] ?? 0;
        $_SESSION['streak_count'] = $freshStats['stats']['streak_count'] ?? 0;

        $this->jsonResponse([
            'attempt_id'     => $attemptId,
            'status'         => 'completed',
            'score'          => $scoreResult['score'],
            'max_score'      => $scoreResult['maxScore'],
            'percentage'     => $scoreResult['percentage'],
            'passed'         => $scoreResult['passed'],
            'time_taken_sec' => max(0, $timeTakenSec),
        ]);
    }

    /**
     * Get the next (or current) question in the attempt.
     * GET /quiz/attempt/{attemptId}/next
     *
     * @param  int $attemptId
     */
    public function nextAction(int $attemptId): void
    {
        $this->requireStudentAuth();

        $userId = (int) ($_SESSION['user_id'] ?? 0);

        // Load attempt (validates ownership)
        $attempt = $this->attemptRepo->getAttemptByIdForUser($attemptId, $userId);

        if ($attempt === null) {
            $this->jsonResponse(['error' => 'Attempt not found.'], 404);
            return;
        }

        if ($attempt['status'] !== 'in_progress') {
            $this->jsonResponse(['error' => 'This attempt is no longer active.'], 400);
            return;
        }

        // Get question order and current index
        $questionOrderJson = $this->attemptRepo->getQuestionOrder($attemptId);
        $questionIds = $questionOrderJson !== null
            ? json_decode($questionOrderJson, true)
            : [];

        if (empty($questionIds)) {
            $this->jsonResponse(['error' => 'No questions in this attempt.'], 400);
            return;
        }

        $currentIndex = (int) ($attempt['current_question_index'] ?? 0);

        // Ensure index is within bounds
        if ($currentIndex >= count($questionIds)) {
            $currentIndex = count($questionIds) - 1;
        }

        $questionId = (int) $questionIds[$currentIndex];

        // Load question data
        $questionData = $this->questionRepo->getById($questionId);

        if ($questionData === null) {
            $this->jsonResponse(['error' => 'Question not found.'], 404);
            return;
        }

        // Check if already answered (for resume context)
        $existingResponse = $this->attemptRepo->getResponse($attemptId, $questionId);

        $this->jsonResponse([
            'attempt_id'   => $attemptId,
            'index'        => $currentIndex,
            'total'        => count($questionIds),
            'question'     => [
                'question_id'   => (int) $questionData['id'],
                'question_text' => $questionData['question_text'],
                'question_type' => $questionData['question_type'],
                'options'       => $questionData['options'] ?? [],
                'points'        => $questionData['points'] ?? 1.0,
            ],
            'previous_answer' => $existingResponse ? [
                'selected_option_id' => $existingResponse['selected_option_id'],
                'answer_text'        => $existingResponse['answer_text'],
                'is_correct'         => $existingResponse['is_correct'],
            ] : null,
        ]);
    }

    /**
     * Check if the student has an incomplete attempt for resume flow.
     * GET /quiz/check-resume
     */
    public function checkResumeAction(): void
    {
        $this->requireStudentAuth();

        $userId = (int) ($_SESSION['user_id'] ?? 0);

        $incomplete = $this->studentQuizRepo->findAnyIncomplete($userId);

        if ($incomplete === null) {
            $this->jsonResponse(['has_incomplete' => false]);
            return;
        }

        $this->jsonResponse([
            'has_incomplete'  => true,
            'attempt_id'      => (int) $incomplete['id'],
            'quiz_title'      => $incomplete['quiz_title'],
            'current_index'   => (int) ($incomplete['current_question_index'] ?? 0),
            'total_questions'  => (int) ($incomplete['total_questions'] ?? 0),
        ]);
    }

    /**
     * View attempt results (server-rendered HTML).
     * GET /quiz/attempt/{attemptId}/results
     *
     * @param  int $attemptId
     */
    public function resultsAction(int $attemptId): void
    {
        $this->requireStudentAuth();

        $userId = (int) ($_SESSION['user_id'] ?? 0);

        // Load completed attempt (validates ownership — T-02R-01)
        $attempt = $this->attemptRepo->getAttemptByIdForUser($attemptId, $userId);

        if ($attempt === null) {
            http_response_code(404);
            echo '404 Not Found';
            return;
        }

        // Only completed or timed_out attempts have results (T-02R-02)
        if (!in_array($attempt['status'], ['completed', 'timed_out'], true)) {
            header('Location: /quiz/attempt/' . $attemptId . '/next');
            exit;
        }

        // Load responses with correct answer data
        $responses = $this->attemptRepo->getResponsesWithCorrectData($attemptId);

        $attempt['responses'] = $responses;

        // Compute time_taken_sec from timestamps
        $startedAt = strtotime($attempt['started_at']);
        $completedAt = !empty($attempt['completed_at'])
            ? strtotime($attempt['completed_at'])
            : time();
        $attempt['time_taken_sec'] = max(0, $completedAt - $startedAt);

        // Ensure numeric types for template
        $attempt['score'] = (float) ($attempt['score'] ?? 0);
        $attempt['max_score'] = (float) ($attempt['max_score'] ?? 0);
        $attempt['percentage'] = (float) ($attempt['percentage'] ?? 0);
        $attempt['passed'] = (bool) ($attempt['passed'] ?? false);
        $attempt['pass_percentage'] = (float) ($attempt['pass_percentage'] ?? 50);

        $this->render('views/quiz/results.php', [
            'attempt' => $attempt,
        ], 'Quiz Results');
    }

    /**
     * Cancel/abandon an in-progress attempt.
     * POST /quiz/attempt/{attemptId}/cancel
     *
     * @param  int $attemptId
     */
    public function cancelAction(int $attemptId): void
    {
        $this->requireStudentAuth();
        $this->requireCsrf();

        $userId = (int) ($_SESSION['user_id'] ?? 0);

        $attempt = $this->attemptRepo->getAttemptByIdForUser($attemptId, $userId);

        if ($attempt === null) {
            $this->jsonResponse(['error' => 'Attempt not found.'], 404);
            return;
        }

        if ($attempt['status'] !== 'in_progress') {
            $this->jsonResponse(['error' => 'This attempt is no longer active.'], 400);
            return;
        }

        // Mark as abandoned (no score calculated)
        $sql = "UPDATE attempts SET status = 'abandoned', completed_at = NOW() WHERE id = :id";
        $pdo = \getConnection();
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':id' => $attemptId]);

        $this->jsonResponse([
            'status' => 'abandoned',
            'message' => 'Quiz attempt cancelled.',
        ]);
    }

    /**
     * Render the quiz taking page with attempt data.
     * GET /quiz/attempt/{attemptId}/take
     *
     * @param  int $attemptId
     */
    public function takeAction(int $attemptId): void
    {
        $this->requireStudentAuth();

        $userId = (int) ($_SESSION['user_id'] ?? 0);

        $attempt = $this->attemptRepo->getAttemptByIdForUser($attemptId, $userId);

        if ($attempt === null) {
            http_response_code(404);
            echo 'Attempt not found.';
            return;
        }

        if ($attempt['status'] !== 'in_progress') {
            header('Location: /quiz/attempt/' . $attemptId . '/results');
            exit;
        }

        $questionOrderJson = $this->attemptRepo->getQuestionOrder($attemptId);
        $questionIds = $questionOrderJson !== null
            ? json_decode($questionOrderJson, true)
            : [];

        $currentIndex = (int) ($attempt['current_question_index'] ?? 0);
        if ($currentIndex >= count($questionIds)) {
            $currentIndex = count($questionIds) - 1;
        }

        $questionId = (int) ($questionIds[$currentIndex] ?? 0);
        $questionData = $this->questionRepo->getById($questionId);

        if ($questionData === null) {
            http_response_code(404);
            echo 'Question not found.';
            return;
        }

        $quiz = $this->studentQuizRepo->getQuizWithQuestions($attempt['quiz_id']);
        $answeredCount = $this->attemptRepo->getAnsweredCount($attemptId);
        $endsAt = $quiz['time_limit_min'] !== null
            ? strtotime($attempt['started_at']) + ((int) $quiz['time_limit_min'] * 60)
            : null;

        $this->generateCsrfToken();

        $this->render('views/quiz/take.php', [
            'attempt'   => $attempt,
            'question'  => $questionData,
            'quiz'      => $quiz,
            'progress'  => [
                'answered' => $answeredCount,
                'total'    => count($questionIds),
            ],
            'endsAt'    => $endsAt,
            'questionOrder' => $questionIds,
        ], $quiz['title'] ?? 'Quiz');
    }
}
