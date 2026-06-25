<?php
declare(strict_types=1);

namespace App\Repositories;

/**
 * Student-facing repository for quiz browsing and resume detection.
 *
 * READ-only operations — no mutations. Returns quiz data with nested
 * questions and options for the student quiz-taking flow.
 *
 * Threat mitigations:
 *   - T-02-001: PDO prepared statements on all SQL queries
 *   - Uses only active quizzes (is_active = 1) in browse queries
 */
class StudentQuizRepository
{
    private \PDO $pdo;

    public function __construct()
    {
        $this->pdo = \getConnection();
    }

    /**
     * Get all active quizzes with topic name and question count.
     * Optionally filtered by topic ID.
     *
     * @param  int|null $topicId Optional topic filter
     * @return array<int, array<string, mixed>>
     */
    public function getActiveQuizzes(?int $topicId = null): array
    {
        $sql = "SELECT qz.*, t.name AS topic_name,
                       (SELECT COUNT(*) FROM quiz_question qq WHERE qq.quiz_id = qz.id) AS question_count
                FROM quizzes qz
                JOIN topics t ON qz.topic_id = t.id
                WHERE qz.is_active = 1
                  AND (:topic_id IS NULL OR qz.topic_id = :topic_id)
                ORDER BY qz.title ASC";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':topic_id' => $topicId]);
        return $stmt->fetchAll();
    }

    /**
     * Get a quiz with all its questions and type-specific options.
     *
     * Loads quiz metadata + all assigned questions with child options
     * (mirrors QuestionRepository::loadChildOptions logic).
     *
     * @param  int $quizId
     * @return array<string, mixed>|null  Quiz with nested `questions` array
     */
    public function getQuizWithQuestions(int $quizId): ?array
    {
        // Load quiz metadata
        $sql = "SELECT qz.*, t.name AS topic_name
                FROM quizzes qz
                JOIN topics t ON qz.topic_id = t.id
                WHERE qz.id = :id";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':id' => $quizId]);
        $quiz = $stmt->fetch();

        if ($quiz === false) {
            return null;
        }

        // Load assigned questions with sort order and points_override
        $sqlQuestions = "SELECT q.*, qq.sort_order, qq.points_override
                         FROM quiz_question qq
                         JOIN questions q ON qq.question_id = q.id
                         WHERE qq.quiz_id = :id
                         ORDER BY qq.sort_order ASC";
        $stmtQuestions = $this->pdo->prepare($sqlQuestions);
        $stmtQuestions->execute([':id' => $quizId]);
        $questions = $stmtQuestions->fetchAll();

        // Load type-specific options for each question
        foreach ($questions as &$question) {
            $question['options'] = $this->loadChildOptions(
                (int) $question['id'],
                $question['question_type']
            );
        }
        unset($question);

        $quiz['questions'] = $questions;
        return $quiz;
    }

    /**
     * Load child options for a given question ID and type.
     *
     * Mirrors QuestionRepository::loadChildOptions to avoid coupling.
     *
     * @param  int    $questionId
     * @param  string $type
     * @return array<int, array<string, mixed>>
     */
    private function loadChildOptions(int $questionId, string $type): array
    {
        $childTable = match ($type) {
            'mcq_single'   => 'mcq_single_options',
            'mcq_multi'    => 'mcq_multi_options',
            'true_false'   => 'true_false_options',
            'fill_blank'   => 'fill_blank_answers',
            'short_answer' => 'short_answer_keywords',
            default        => null,
        };

        if ($childTable === null) {
            return [];
        }

        $sql = "SELECT * FROM {$childTable} WHERE question_id = :id";

        switch ($type) {
            case 'mcq_single':
            case 'mcq_multi':
                $sql .= " ORDER BY sort_order ASC";
                break;
            case 'short_answer':
                $sql .= " ORDER BY is_required DESC";
                break;
            default:
                break;
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':id' => $questionId]);
        return $stmt->fetchAll();
    }

    /**
     * Get all topics for the filter dropdown.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getTopics(): array
    {
        $sql = "SELECT id, name FROM topics ORDER BY name ASC";
        $stmt = $this->pdo->query($sql);
        return $stmt->fetchAll();
    }

    /**
     * Check if a student has an incomplete attempt for a specific quiz
     * within the 24-hour expiry window (D-07).
     *
     * @param  int $userId
     * @param  int $quizId
     * @return array<string, mixed>|null  Attempt data or null if none found
     */
    public function checkStudentHasIncomplete(int $userId, int $quizId): ?array
    {
        $sql = "SELECT id, current_question_index, started_at
                FROM attempts
                WHERE user_id = :user_id
                  AND quiz_id = :quiz_id
                  AND status = 'in_progress'
                  AND TIMESTAMPDIFF(HOUR, started_at, NOW()) < 24
                LIMIT 1";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':user_id'  => $userId,
            ':quiz_id'  => $quizId,
        ]);
        $row = $stmt->fetch();
        return $row !== false ? $row : null;
    }

    /**
     * Find any incomplete attempt for a student across all quizzes.
     * Used for resume detection on the browse page.
     *
     * @param  int $userId
     * @return array<string, mixed>|null  Attempt data with quiz title or null
     */
    public function findAnyIncomplete(int $userId): ?array
    {
        $sql = "SELECT a.id, a.current_question_index, a.started_at, a.quiz_id,
                       qz.title AS quiz_title,
                       (SELECT COUNT(*) FROM quiz_question qq WHERE qq.quiz_id = a.quiz_id) AS total_questions
                FROM attempts a
                JOIN quizzes qz ON a.quiz_id = qz.id
                WHERE a.user_id = :user_id
                  AND a.status = 'in_progress'
                  AND TIMESTAMPDIFF(HOUR, a.started_at, NOW()) < 24
                LIMIT 1";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':user_id' => $userId]);
        $row = $stmt->fetch();
        return $row !== false ? $row : null;
    }
}
