<?php
declare(strict_types=1);

namespace App\Repositories;

/**
 * Repository for quiz data access via PDO prepared statements.
 *
 * Handles quiz metadata CRUD and question assignment through the
 * quiz_question pivot table. Follows the question bank pattern
 * (D-03): questions are created independently, then assigned to
 * quizzes via this repository.
 *
 * Threat mitigations:
 *   - T-03-001: PDO prepared statements with named parameters on all SQL
 *   - T-03-006: Validates question_ids exist before inserting into quiz_question
 */
class QuizRepository
{
    private \PDO $pdo;

    public function __construct()
    {
        $this->pdo = \getConnection();
    }

    /**
     * Get all quizzes with topic name and question count.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getAll(): array
    {
        $sql = "SELECT qz.*, t.name as topic_name,
                       (SELECT COUNT(*) FROM quiz_question qq WHERE qq.quiz_id = qz.id) as question_count
                FROM quizzes qz
                JOIN topics t ON qz.topic_id = t.id
                ORDER BY qz.created_at DESC";
        $stmt = $this->pdo->query($sql);
        return $stmt->fetchAll();
    }

    /**
     * Get a single quiz by ID including assigned questions.
     *
     * @param  int $id
     * @return array<string, mixed>|null
     */
    public function getById(int $id): ?array
    {
        $sql = "SELECT qz.*, t.name as topic_name
                FROM quizzes qz
                JOIN topics t ON qz.topic_id = t.id
                WHERE qz.id = :id";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':id' => $id]);
        $quiz = $stmt->fetch();

        if ($quiz === false) {
            return null;
        }

        // Load assigned questions with sort order
        $sqlQuestions = "SELECT q.*, qq.sort_order, qq.points_override, t.name as topic_name
                         FROM quiz_question qq
                         JOIN questions q ON qq.question_id = q.id
                         JOIN topics t ON q.topic_id = t.id
                         WHERE qq.quiz_id = :id
                         ORDER BY qq.sort_order ASC";
        $stmtQuestions = $this->pdo->prepare($sqlQuestions);
        $stmtQuestions->execute([':id' => $id]);
        $quiz['questions'] = $stmtQuestions->fetchAll();

        return $quiz;
    }

    /**
     * Create a new quiz with optional question assignments.
     *
     * @param  array<string, mixed> $data Keys: topic_id, title, description, time_limit_min,
     *                                    pass_percentage, is_active, question_ids (optional array)
     * @return int The new quiz's ID
     */
    public function create(array $data): int
    {
        $sql = "INSERT INTO quizzes (topic_id, title, description, time_limit_min, pass_percentage, is_active)
                VALUES (:topic_id, :title, :description, :time_limit_min, :pass_percentage, :is_active)";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':topic_id'        => (int) ($data['topic_id'] ?? 0),
            ':title'           => $data['title'] ?? '',
            ':description'     => $data['description'] ?? null,
            ':time_limit_min'  => $data['time_limit_min'] !== '' && $data['time_limit_min'] !== null
                                   ? (int) $data['time_limit_min'] : null,
            ':pass_percentage' => (int) ($data['pass_percentage'] ?? 50),
            ':is_active'       => !empty($data['is_active']) ? 1 : 0,
        ]);

        $quizId = (int) $this->pdo->lastInsertId();

        // Assign questions if provided
        if (!empty($data['question_ids']) && is_array($data['question_ids'])) {
            $validIds = $this->validateQuestionIds($data['question_ids']);
            $this->assignQuestions($quizId, $validIds);
        }

        return $quizId;
    }

    /**
     * Update an existing quiz and its question assignments.
     *
     * Deletes existing question assignments and re-inserts from
     * the provided question_ids array, effectively performing a
     * full re-sync of the quiz_question pivot.
     *
     * @param int              $id
     * @param array<string, mixed> $data
     */
    public function update(int $id, array $data): void
    {
        $sql = "UPDATE quizzes SET
                    topic_id = :topic_id,
                    title = :title,
                    description = :description,
                    time_limit_min = :time_limit_min,
                    pass_percentage = :pass_percentage,
                    is_active = :is_active
                WHERE id = :id";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':id'              => $id,
            ':topic_id'        => (int) ($data['topic_id'] ?? 0),
            ':title'           => $data['title'] ?? '',
            ':description'     => $data['description'] ?? null,
            ':time_limit_min'  => $data['time_limit_min'] !== '' && $data['time_limit_min'] !== null
                                   ? (int) $data['time_limit_min'] : null,
            ':pass_percentage' => (int) ($data['pass_percentage'] ?? 50),
            ':is_active'       => !empty($data['is_active']) ? 1 : 0,
        ]);

        // Re-sync question assignments: delete existing, re-insert
        $deleteSql = "DELETE FROM quiz_question WHERE quiz_id = :id";
        $deleteStmt = $this->pdo->prepare($deleteSql);
        $deleteStmt->execute([':id' => $id]);

        if (!empty($data['question_ids']) && is_array($data['question_ids'])) {
            $validIds = $this->validateQuestionIds($data['question_ids']);
            $this->assignQuestions($id, $validIds);
        }
    }

    /**
     * Delete a quiz by ID.
     * CASCADE deletes quiz_question and attempts per FK constraints.
     *
     * @param int $id
     */
    public function delete(int $id): void
    {
        $sql = "DELETE FROM quizzes WHERE id = :id";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':id' => $id]);
    }

    /**
     * Get all topics for dropdown filters.
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
     * Get available (active) questions filtered by topic.
     *
     * @param  int $topicId
     * @return array<int, array<string, mixed>>
     */
    public function getAvailableQuestionsByTopic(int $topicId): array
    {
        $sql = "SELECT q.id, q.question_text, q.question_type, q.points, t.name as topic_name
                FROM questions q
                JOIN topics t ON q.topic_id = t.id
                WHERE q.topic_id = :topic_id AND q.is_active = 1
                ORDER BY q.created_at DESC";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':topic_id' => $topicId]);
        return $stmt->fetchAll();
    }

    /**
     * Validate that question IDs exist in the database (T-03-006).
     *
     * @param  array<int> $ids
     * @return array<int> Only IDs that exist in the questions table
     */
    private function validateQuestionIds(array $ids): array
    {
        $ids = array_map('intval', $ids);
        $ids = array_filter($ids, fn(int $id) => $id > 0);
        $ids = array_unique($ids);

        if (empty($ids)) {
            return [];
        }

        // Build placeholders for IN clause
        $placeholders = [];
        $params = [];
        foreach ($ids as $i => $id) {
            $key = ":id{$i}";
            $placeholders[] = $key;
            $params[$key] = $id;
        }

        $sql = "SELECT id FROM questions WHERE id IN (" . implode(',', $placeholders) . ") AND is_active = 1";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $existing = $stmt->fetchAll(\PDO::FETCH_COLUMN);

        return array_map('intval', $existing);
    }

    /**
     * Assign questions to a quiz with sequential sort_order.
     *
     * @param int     $quizId
     * @param array<int> $questionIds Validated question IDs
     */
    private function assignQuestions(int $quizId, array $questionIds): void
    {
        $sql = "INSERT INTO quiz_question (quiz_id, question_id, sort_order)
                VALUES (:quiz_id, :question_id, :sort_order)";
        $stmt = $this->pdo->prepare($sql);

        foreach ($questionIds as $sortOrder => $questionId) {
            $stmt->execute([
                ':quiz_id'      => $quizId,
                ':question_id'  => $questionId,
                ':sort_order'   => $sortOrder + 1,
            ]);
        }
    }
}
