<?php
declare(strict_types=1);

namespace App\Repositories;

/**
 * Repository for question data access with polymorphic type support.
 *
 * Questions are stored in a base `questions` table with type-specific child
 * tables for options/answers:
 *   - mcq_single        → mcq_single_options
 *   - mcq_multi         → mcq_multi_options
 *   - true_false        → true_false_options
 *   - fill_blank        → fill_blank_answers
 *   - short_answer      → short_answer_keywords
 *
 * Threat mitigations:
 *   - T-02-001: PDO prepared statements with named parameters on all SQL
 *   - T-02-004: Server-side validation of question_type against ENUM values
 */
class QuestionRepository
{
    private \PDO $pdo;

    /** @var array<string, string> Mapping of question types to their child table names */
    private const CHILD_TABLES = [
        'mcq_single'   => 'mcq_single_options',
        'mcq_multi'    => 'mcq_multi_options',
        'true_false'   => 'true_false_options',
        'fill_blank'   => 'fill_blank_answers',
        'short_answer' => 'short_answer_keywords',
    ];

    /** @var array<string> Valid question types for server-side validation (T-02-004) */
    private const VALID_TYPES = ['mcq_single', 'mcq_multi', 'true_false', 'fill_blank', 'short_answer'];

    public function __construct()
    {
        $this->pdo = \getConnection();
    }

    /**
     * Get the child table name for a given question type.
     *
     * @param  string $type One of mcq_single|mcq_multi|true_false|fill_blank|short_answer
     * @return string       The child table name
     */
    private function getChildTable(string $type): string
    {
        return self::CHILD_TABLES[$type];
    }

    /**
     * Validate question type against allowed values.
     *
     * @param  string $type
     * @throws \InvalidArgumentException if type is not valid
     */
    private function validateType(string $type): void
    {
        if (!in_array($type, self::VALID_TYPES, true)) {
            throw new \InvalidArgumentException("Invalid question type: {$type}");
        }
    }

    /**
     * Get all active questions with optional type and topic filters.
     *
     * @param  string|null $type    Filter by question type
     * @param  int|null    $topicId Filter by topic ID
     * @return array<int, array<string, mixed>>
     */
    public function getAll(?string $type = null, ?int $topicId = null): array
    {
        $sql = "SELECT q.*, t.name as topic_name
                FROM questions q
                JOIN topics t ON q.topic_id = t.id
                WHERE q.is_active = 1";

        $params = [];

        if ($type !== null && $type !== '') {
            $sql .= " AND q.question_type = :type";
            $params[':type'] = $type;
        }

        if ($topicId !== null && $topicId > 0) {
            $sql .= " AND q.topic_id = :topic_id";
            $params[':topic_id'] = $topicId;
        }

        $sql .= " ORDER BY q.created_at DESC";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /**
     * Get a single question by ID including type-specific options.
     *
     * @param  int $id
     * @return array<string, mixed>|null
     */
    public function getById(int $id): ?array
    {
        $sql = "SELECT q.*, t.name as topic_name
                FROM questions q
                JOIN topics t ON q.topic_id = t.id
                WHERE q.id = :id";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();

        if ($row === false) {
            return null;
        }

        // Load type-specific options
        $row['options'] = $this->loadChildOptions((int) $row['id'], $row['question_type']);
        return $row;
    }

    /**
     * Load child options for a given question ID and type.
     *
     * @param  int    $questionId
     * @param  string $type
     * @return array<int, array<string, mixed>>
     */
    private function loadChildOptions(int $questionId, string $type): array
    {
        $this->validateType($type);
        $childTable = $this->getChildTable($type);

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
     * Create a new question with type-specific options.
     *
     * @param  array<string, mixed> $data
     * @return int  The new question's ID
     */
    public function create(array $data): int
    {
        $this->validateType($data['question_type']);

        // Insert base question
        $sql = "INSERT INTO questions (question_text, question_type, topic_id, points, explanation)
                VALUES (:question_text, :question_type, :topic_id, :points, :explanation)";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':question_text' => $data['question_text'],
            ':question_type' => $data['question_type'],
            ':topic_id'      => (int) ($data['topic_id'] ?? 0),
            ':points'        => (float) ($data['points'] ?? 1.0),
            ':explanation'   => $data['explanation'] ?? null,
        ]);

        $questionId = (int) $this->pdo->lastInsertId();

        // Store child options
        $this->storeChildOptions($questionId, $data['question_type'], $data['options'] ?? []);

        return $questionId;
    }

    /**
     * Update an existing question and its type-specific options.
     *
     * @param int              $id
     * @param array<string, mixed> $data
     */
    public function update(int $id, array $data): void
    {
        $this->validateType($data['question_type']);

        // Update base question (type cannot change after creation)
        $sql = "UPDATE questions SET
                    question_text = :question_text,
                    topic_id = :topic_id,
                    points = :points,
                    explanation = :explanation
                WHERE id = :id";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':id'             => $id,
            ':question_text'  => $data['question_text'],
            ':topic_id'       => (int) ($data['topic_id'] ?? 0),
            ':points'         => (float) ($data['points'] ?? 1.0),
            ':explanation'    => $data['explanation'] ?? null,
        ]);

        // Delete existing child options, then re-insert
        $childTable = $this->getChildTable($data['question_type']);
        $deleteSql = "DELETE FROM {$childTable} WHERE question_id = :id";
        $deleteStmt = $this->pdo->prepare($deleteSql);
        $deleteStmt->execute([':id' => $id]);

        $this->storeChildOptions($id, $data['question_type'], $data['options'] ?? []);
    }

    /**
     * Soft-delete a question (set is_active = 0).
     * Preserves historical references in responses.
     *
     * @param int $id
     */
    public function delete(int $id): void
    {
        $sql = "UPDATE questions SET is_active = 0 WHERE id = :id";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':id' => $id]);
    }

    /**
     * Store type-specific child options for a question.
     *
     * @param int    $questionId
     * @param string $type
     * @param array  $options   Type-specific option data
     */
    private function storeChildOptions(int $questionId, string $type, array $options): void
    {
        $this->validateType($type);

        switch ($type) {
            case 'mcq_single':
            case 'mcq_multi':
                $this->storeMcqOptions($questionId, $type, $options);
                break;

            case 'true_false':
                $this->storeTrueFalseOptions($questionId, $options);
                break;

            case 'fill_blank':
                $this->storeFillBlankAnswer($questionId, $options);
                break;

            case 'short_answer':
                $this->storeShortAnswerKeywords($questionId, $options);
                break;
        }
    }

    /**
     * Store MCQ options (single or multi).
     *
     * @param int    $questionId
     * @param string $type       mcq_single or mcq_multi
     * @param array  $options    Array of option rows with option_text, is_correct, feedback_text, sort_order
     */
    private function storeMcqOptions(int $questionId, string $type, array $options): void
    {
        $childTable = $this->getChildTable($type);
        $sql = "INSERT INTO {$childTable} (question_id, option_text, is_correct, feedback_text, sort_order)
                VALUES (:question_id, :option_text, :is_correct, :feedback_text, :sort_order)";
        $stmt = $this->pdo->prepare($sql);

        foreach ($options as $i => $option) {
            $stmt->execute([
                ':question_id'   => $questionId,
                ':option_text'   => $option['option_text'] ?? '',
                ':is_correct'    => !empty($option['is_correct']) ? 1 : 0,
                ':feedback_text' => $option['feedback_text'] ?? null,
                ':sort_order'    => (int) ($option['sort_order'] ?? $i),
            ]);
        }
    }

    /**
     * Store True/False options (always 2 rows).
     *
     * @param int   $questionId
     * @param array $options     Must contain: is_true (1 or 0), feedback_text
     *                           is_correct determines which row is marked correct
     */
    private function storeTrueFalseOptions(int $questionId, array $options): void
    {
        $sql = "INSERT INTO true_false_options (question_id, is_true, is_correct, feedback_text)
                VALUES (:question_id, :is_true, :is_correct, :feedback_text)";
        $stmt = $this->pdo->prepare($sql);

        $correctValue = (int) ($options['is_correct'] ?? 1);

        // Row for True (is_true = 1)
        $stmt->execute([
            ':question_id'   => $questionId,
            ':is_true'       => 1,
            ':is_correct'    => $correctValue === 1 ? 1 : 0,
            ':feedback_text' => $options['feedback_text'] ?? null,
        ]);

        // Row for False (is_true = 0)
        $stmt->execute([
            ':question_id'   => $questionId,
            ':is_true'       => 0,
            ':is_correct'    => $correctValue === 0 ? 1 : 0,
            ':feedback_text' => $options['feedback_text'] ?? null,
        ]);
    }

    /**
     * Store a fill-in-the-blank answer.
     *
     * @param int   $questionId
     * @param array $options     Must contain: correct_answer, optional alternative_answers, feedback_text
     */
    private function storeFillBlankAnswer(int $questionId, array $options): void
    {
        $sql = "INSERT INTO fill_blank_answers (question_id, correct_answer, alternative_answers, feedback_text)
                VALUES (:question_id, :correct_answer, :alternative_answers, :feedback_text)";
        $stmt = $this->pdo->prepare($sql);

        $alternativeAnswers = null;
        if (!empty($options['alternative_answers'])) {
            $altArray = array_map('trim', explode(',', $options['alternative_answers']));
            $altArray = array_filter($altArray, fn(string $v) => $v !== '');
            if (!empty($altArray)) {
                $alternativeAnswers = json_encode(array_values($altArray));
            }
        }

        $stmt->execute([
            ':question_id'         => $questionId,
            ':correct_answer'      => $options['correct_answer'] ?? '',
            ':alternative_answers' => $alternativeAnswers,
            ':feedback_text'       => $options['feedback_text'] ?? null,
        ]);
    }

    /**
     * Store short answer keywords.
     *
     * @param int   $questionId
     * @param array $options     Array of keyword rows: keyword, synonyms (comma-separated), is_required
     */
    private function storeShortAnswerKeywords(int $questionId, array $options): void
    {
        $sql = "INSERT INTO short_answer_keywords (question_id, keyword, synonyms, is_required)
                VALUES (:question_id, :keyword, :synonyms, :is_required)";
        $stmt = $this->pdo->prepare($sql);

        foreach ($options as $option) {
            $synonyms = null;
            if (!empty($option['synonyms'])) {
                $synArray = array_map('trim', explode(',', $option['synonyms']));
                $synArray = array_filter($synArray, fn(string $v) => $v !== '');
                if (!empty($synArray)) {
                    $synonyms = json_encode(array_values($synArray));
                }
            }

            $stmt->execute([
                ':question_id' => $questionId,
                ':keyword'     => $option['keyword'] ?? '',
                ':synonyms'    => $synonyms,
                ':is_required' => !empty($option['is_required']) ? 1 : 0,
            ]);
        }
    }

    /**
     * Get all topics for filter dropdowns.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getTopics(): array
    {
        $sql = "SELECT id, name FROM topics ORDER BY name ASC";
        $stmt = $this->pdo->query($sql);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Get unique topic IDs for all questions assigned to a quiz.
     *
     * @param  int   $quizId
     * @return int[]
     */
    public function getTopicIdsByQuiz(int $quizId): array
    {
        $sql = "SELECT DISTINCT q.topic_id
                FROM quiz_question qq
                JOIN questions q ON q.id = qq.question_id
                WHERE qq.quiz_id = :quiz_id";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':quiz_id' => $quizId]);
        return array_map('intval', $stmt->fetchAll(\PDO::FETCH_COLUMN));
    }
}
}
