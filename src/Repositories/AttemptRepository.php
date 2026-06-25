<?php
declare(strict_types=1);

namespace App\Repositories;

/**
 * Repository for student attempt data access via PDO prepared statements.
 *
 * Provides results summary views and per-attempt drill-down with
 * question-by-question response data. Used by the admin results
 * panel per D-05.
 *
 * Threat mitigations:
 *   - T-03-001: PDO prepared statements with named parameters on all SQL
 */
class AttemptRepository
{
    private \PDO $pdo;

    public function __construct()
    {
        $this->pdo = \getConnection();
    }

    /**
     * Get all completed attempts with student and quiz info.
     * Optionally filtered by quiz ID.
     *
     * @param  int|null $quizId Optional quiz filter
     * @return array<int, array<string, mixed>>
     */
    public function getAllByQuiz(?int $quizId = null): array
    {
        $sql = "SELECT a.*, u.full_name as student_name, u.username, qz.title as quiz_title,
                       qz.pass_percentage
                FROM attempts a
                JOIN users u ON a.user_id = u.id
                JOIN quizzes qz ON a.quiz_id = qz.id
                WHERE a.status IN ('completed', 'timed_out')
                  AND (:quiz_id IS NULL OR a.quiz_id = :quiz_id)
                ORDER BY a.completed_at DESC, a.started_at DESC";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':quiz_id' => $quizId]);
        return $stmt->fetchAll();
    }

    /**
     * Get a single attempt by ID including all responses.
     *
     * @param  int $id
     * @return array<string, mixed>|null
     */
    public function getById(int $id): ?array
    {
        $sql = "SELECT a.*, u.full_name as student_name, u.username, qz.title as quiz_title,
                       qz.pass_percentage, qz.time_limit_min
                FROM attempts a
                JOIN users u ON a.user_id = u.id
                JOIN quizzes qz ON a.quiz_id = qz.id
                WHERE a.id = :id";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':id' => $id]);
        $attempt = $stmt->fetch();

        if ($attempt === false) {
            return null;
        }

        // Load responses with question data
        $sqlResponses = "SELECT r.*, q.question_text, q.question_type, q.points as max_points,
                                q.explanation
                         FROM responses r
                         JOIN questions q ON r.question_id = q.id
                         WHERE r.attempt_id = :attempt_id
                         ORDER BY r.id ASC";
        $stmtResponses = $this->pdo->prepare($sqlResponses);
        $stmtResponses->execute([':attempt_id' => $id]);
        $attempt['responses'] = $stmtResponses->fetchAll();

        // For each response, load the correct answer details based on question type
        foreach ($attempt['responses'] as &$response) {
            $response['correct_answer_data'] = $this->getCorrectAnswerData(
                (int) $response['question_id'],
                $response['question_type']
            );
        }
        unset($response);

        return $attempt;
    }

    /**
     * Get all quizzes for the filter dropdown.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getQuizzes(): array
    {
        $sql = "SELECT id, title FROM quizzes ORDER BY title ASC";
        $stmt = $this->pdo->query($sql);
        return $stmt->fetchAll();
    }

    /**
     * Get summary stats for the results dashboard.
     *
     * @return array<string, mixed>
     */
    public function getStats(): array
    {
        $sql = "SELECT
                    COUNT(DISTINCT a.id) as total_attempts,
                    COUNT(DISTINCT a.user_id) as total_students,
                    ROUND(AVG(a.percentage), 1) as avg_percentage,
                    SUM(CASE WHEN a.passed = 1 THEN 1 ELSE 0 END) as passed_count
                FROM attempts a
                WHERE a.status IN ('completed', 'timed_out')";
        $stmt = $this->pdo->query($sql);
        $stats = $stmt->fetch();

        // Ensure all keys exist with defaults
        return [
            'total_attempts'  => (int) ($stats['total_attempts'] ?? 0),
            'total_students'  => (int) ($stats['total_students'] ?? 0),
            'avg_percentage'  => (float) ($stats['avg_percentage'] ?? 0.0),
            'passed_count'    => (int) ($stats['passed_count'] ?? 0),
        ];
    }

    /**
     * Get the correct answer data for a question based on its type.
     *
     * @param  int    $questionId
     * @param  string $questionType
     * @return array  Contains 'correct_answer' string and optionally 'correct_option_id'
     */
    private function getCorrectAnswerData(int $questionId, string $questionType): array
    {
        switch ($questionType) {
            case 'mcq_single':
                $sql = "SELECT id, option_text FROM mcq_single_options
                        WHERE question_id = :question_id AND is_correct = 1 LIMIT 1";
                $stmt = $this->pdo->prepare($sql);
                $stmt->execute([':question_id' => $questionId]);
                $row = $stmt->fetch();
                return [
                    'correct_option_id' => $row ? (int) $row['id'] : null,
                    'correct_answer'    => $row ? $row['option_text'] : 'N/A',
                ];

            case 'mcq_multi':
                $sql = "SELECT id, option_text FROM mcq_multi_options
                        WHERE question_id = :question_id AND is_correct = 1
                        ORDER BY sort_order ASC";
                $stmt = $this->pdo->prepare($sql);
                $stmt->execute([':question_id' => $questionId]);
                $rows = $stmt->fetchAll();
                $correctIds = array_map(fn($r) => (int) $r['id'], $rows);
                $correctTexts = array_map(fn($r) => $r['option_text'], $rows);
                return [
                    'correct_option_ids' => $correctIds,
                    'correct_answer'     => !empty($correctTexts) ? implode(', ', $correctTexts) : 'N/A',
                ];

            case 'true_false':
                $sql = "SELECT is_true, is_correct FROM true_false_options
                        WHERE question_id = :question_id AND is_correct = 1 LIMIT 1";
                $stmt = $this->pdo->prepare($sql);
                $stmt->execute([':question_id' => $questionId]);
                $row = $stmt->fetch();
                $answerText = $row ? ($row['is_true'] ? 'True' : 'False') : 'N/A';
                return [
                    'correct_option_id' => $row ? (int) $row['id'] : null,
                    'correct_answer'    => $answerText,
                ];

            case 'fill_blank':
                $sql = "SELECT correct_answer, alternative_answers FROM fill_blank_answers
                        WHERE question_id = :question_id LIMIT 1";
                $stmt = $this->pdo->prepare($sql);
                $stmt->execute([':question_id' => $questionId]);
                $row = $stmt->fetch();
                $answer = $row ? $row['correct_answer'] : 'N/A';
                return ['correct_answer' => $answer];

            case 'short_answer':
                $sql = "SELECT keyword, synonyms FROM short_answer_keywords
                        WHERE question_id = :question_id AND is_required = 1";
                $stmt = $this->pdo->prepare($sql);
                $stmt->execute([':question_id' => $questionId]);
                $rows = $stmt->fetchAll();
                $keywords = array_map(fn($r) => $r['keyword'], $rows);
                return [
                    'correct_answer' => !empty($keywords) ? 'Keywords: ' . implode(', ', $keywords) : 'N/A',
                ];

            default:
                return ['correct_answer' => 'N/A'];
        }
    }

    // ─────────────────────────────────────────────────────────────
    // Phase 2: Student quiz-taking methods (appended)
    // ─────────────────────────────────────────────────────────────

    /**
     * Create a new quiz attempt for a student.
     *
     * @param  int    $userId
     * @param  int    $quizId
     * @param  int    $attemptNumber
     * @param  string $questionOrderJson JSON-encoded shuffled question ID order
     * @return int    The new attempt's ID
     */
    public function createAttempt(int $userId, int $quizId, int $attemptNumber, string $questionOrderJson): int
    {
        $sql = "INSERT INTO attempts (user_id, quiz_id, status, attempt_number, question_order, started_at)
                VALUES (:user_id, :quiz_id, 'in_progress', :attempt_number, :question_order, NOW())";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':user_id'         => $userId,
            ':quiz_id'         => $quizId,
            ':attempt_number'  => $attemptNumber,
            ':question_order'  => $questionOrderJson,
        ]);
        return (int) $this->pdo->lastInsertId();
    }

    /**
     * Get the highest attempt number for a student on a quiz.
     *
     * @param  int $userId
     * @param  int $quizId
     * @return int
     */
    public function getMaxAttemptNumber(int $userId, int $quizId): int
    {
        $sql = "SELECT COALESCE(MAX(attempt_number), 0) FROM attempts
                WHERE user_id = :user_id AND quiz_id = :quiz_id";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':user_id' => $userId, ':quiz_id' => $quizId]);
        return (int) $stmt->fetchColumn();
    }

    /**
     * Save (or update) a student's answer to a question.
     *
     * Uses UPSERT for idempotent re-save — submitting the same question
     * updates the existing row rather than inserting a duplicate.
     *
     * @param  int      $attemptId
     * @param  int      $questionId
     * @param  int|null $selectedOptionId For mcq_single, mcq_multi, true_false
     * @param  string|null $answerText   For fill_blank, short_answer
     * @param  int      $timeTakenSec    Seconds spent on this question
     */
    public function saveAnswer(int $attemptId, int $questionId, ?int $selectedOptionId, ?string $answerText, int $timeTakenSec): void
    {
        $sql = "INSERT INTO responses (attempt_id, question_id, selected_option_id, answer_text, answered_at)
                VALUES (:attempt_id, :question_id, :selected_option_id, :answer_text, NOW())
                ON DUPLICATE KEY UPDATE
                    selected_option_id = VALUES(selected_option_id),
                    answer_text = VALUES(answer_text),
                    answered_at = NOW()";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':attempt_id'         => $attemptId,
            ':question_id'        => $questionId,
            ':selected_option_id' => $selectedOptionId,
            ':answer_text'        => $answerText,
        ]);
    }

    /**
     * Get a single response for an attempt+question pair.
     *
     * @param  int $attemptId
     * @param  int $questionId
     * @return array<string, mixed>|null
     */
    public function getResponse(int $attemptId, int $questionId): ?array
    {
        $sql = "SELECT * FROM responses WHERE attempt_id = :attempt_id AND question_id = :question_id LIMIT 1";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':attempt_id' => $attemptId, ':question_id' => $questionId]);
        $row = $stmt->fetch();
        return $row !== false ? $row : null;
    }

    /**
     * Get all responses for an attempt, ordered by id ASC.
     *
     * @param  int $attemptId
     * @return array<int, array<string, mixed>>
     */
    public function getResponsesForAttempt(int $attemptId): array
    {
        $sql = "SELECT r.*, q.question_text, q.question_type, q.points AS max_points,
                       q.explanation
                FROM responses r
                JOIN questions q ON r.question_id = q.id
                WHERE r.attempt_id = :attempt_id
                ORDER BY r.id ASC";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':attempt_id' => $attemptId]);
        return $stmt->fetchAll();
    }

    /**
     * Get a single attempt by ID, validating student ownership.
     *
     * Returns null if the attempt doesn't exist or doesn't belong to the user.
     * Mitigates T-02-001 (spoofing) and T-02-004 (information disclosure).
     *
     * @param  int $attemptId
     * @param  int $userId
     * @return array<string, mixed>|null
     */
    public function getAttemptByIdForUser(int $attemptId, int $userId): ?array
    {
        $sql = "SELECT a.*, qz.title AS quiz_title, qz.time_limit_min, qz.pass_percentage,
                       qz.max_attempts
                FROM attempts a
                JOIN quizzes qz ON a.quiz_id = qz.id
                WHERE a.id = :id AND a.user_id = :user_id";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':id' => $attemptId, ':user_id' => $userId]);
        $row = $stmt->fetch();
        return $row !== false ? $row : null;
    }

    /**
     * Update a response with evaluation results.
     *
     * @param  int   $attemptId
     * @param  int   $questionId
     * @param  bool  $isCorrect
     * @param  float $pointsEarned
     * @param  bool  $needsReview Short answer requires manual grading (SANS-03)
     */
    public function evaluateAndUpdateResponse(int $attemptId, int $questionId, bool $isCorrect, float $pointsEarned, bool $needsReview = false): void
    {
        $sql = "UPDATE responses SET is_correct = :is_correct, points_earned = :points_earned,
                    needs_review = :needs_review
                WHERE attempt_id = :attempt_id AND question_id = :question_id";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':is_correct'     => $isCorrect ? 1 : 0,
            ':points_earned'  => $pointsEarned,
            ':needs_review'   => $needsReview ? 1 : 0,
            ':attempt_id'     => $attemptId,
            ':question_id'    => $questionId,
        ]);
    }

    /**
     * Get all responses for an attempt with correct answer data loaded.
     * Used by the results page for per-question review display.
     *
     * @param  int $attemptId
     * @return array<int, array<string, mixed>>
     */
    public function getResponsesWithCorrectData(int $attemptId): array
    {
        $responses = $this->getResponsesForAttempt($attemptId);
        foreach ($responses as &$response) {
            $response['correct_answer_data'] = $this->getCorrectAnswerData(
                (int) $response['question_id'],
                $response['question_type']
            );
        }
        unset($response);
        return $responses;
    }

    /**
     * Mark an attempt as completed with final score.
     *
     * @param  int    $attemptId
     * @param  float  $score
     * @param  float  $maxScore
     * @param  float  $percentage
     * @param  bool   $passed
     */
    public function updateAttemptScore(int $attemptId, float $score, float $maxScore, float $percentage, bool $passed): void
    {
        $sql = "UPDATE attempts
                SET status = 'completed', score = :score, max_score = :max_score,
                    percentage = :percentage, passed = :passed, completed_at = NOW()
                WHERE id = :id";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':score'      => $score,
            ':max_score'  => $maxScore,
            ':percentage' => $percentage,
            ':passed'     => $passed ? 1 : 0,
            ':id'         => $attemptId,
        ]);
    }

    /**
     * Mark an attempt as timed out (server-authoritative timer enforcement).
     *
     * @param  int $attemptId
     */
    public function markAsTimedOut(int $attemptId): void
    {
        $sql = "UPDATE attempts SET status = 'timed_out', completed_at = NOW() WHERE id = :id";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':id' => $attemptId]);
    }

    /**
     * Update the current question index for resume tracking.
     *
     * @param  int $attemptId
     * @param  int $index
     */
    public function updateCurrentQuestionIndex(int $attemptId, int $index): void
    {
        $sql = "UPDATE attempts SET current_question_index = :index WHERE id = :id";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':index' => $index, ':id' => $attemptId]);
    }

    /**
     * Get the number of answered questions for an attempt.
     *
     * @param  int  $attemptId
     * @return int
     */
    public function getAnsweredCount(int $attemptId): int
    {
        $sql = "SELECT COUNT(*) FROM responses WHERE attempt_id = :id AND score IS NOT NULL";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':id' => $attemptId]);
        return (int) $stmt->fetchColumn();
    }

    /**
     * Get the question_order JSON for an attempt.
     *
     * @param  int $attemptId
     * @return string|null  JSON string or null
     */
    public function getQuestionOrder(int $attemptId): ?string
    {
        $sql = "SELECT question_order FROM attempts WHERE id = :id";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':id' => $attemptId]);
        $val = $stmt->fetchColumn();
        return $val !== false && $val !== null ? (string) $val : null;
    }
}
