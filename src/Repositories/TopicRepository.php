<?php
declare(strict_types=1);

namespace App\Repositories;

/**
 * Repository for topic data access via PDO prepared statements.
 *
 * Follows the Repository pattern: every method uses PDO prepared statements
 * with named parameters. No service layer in Phase 1 — controllers call
 * repositories directly.
 *
 * Threat mitigations:
 *   - T-02-001: PDO prepared statements with named parameters on all SQL
 */
class TopicRepository
{
    private \PDO $pdo;

    public function __construct()
    {
        $this->pdo = \getConnection();
    }

    /**
     * Get all topics ordered by name, with quiz count.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getAll(): array
    {
        $sql = "SELECT t.*,
                       (SELECT COUNT(*) FROM quizzes q WHERE q.topic_id = t.id) as quiz_count
                FROM topics t
                ORDER BY t.name ASC";
        $stmt = $this->pdo->query($sql);
        return $stmt->fetchAll();
    }

    /**
     * Get a single topic by ID.
     *
     * @param  int $id
     * @return array<string, mixed>|null
     */
    public function getById(int $id): ?array
    {
        $sql = "SELECT * FROM topics WHERE id = :id";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        return $row !== false ? $row : null;
    }

    /**
     * Create a new topic.
     *
     * @param  string      $name
     * @param  string|null $description
     * @return int  The new topic's ID
     */
    public function create(string $name, ?string $description): int
    {
        $sql = "INSERT INTO topics (name, description) VALUES (:name, :description)";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':name'        => $name,
            ':description' => $description,
        ]);
        return (int) $this->pdo->lastInsertId();
    }

    /**
     * Update an existing topic.
     *
     * @param int         $id
     * @param string      $name
     * @param string|null $description
     */
    public function update(int $id, string $name, ?string $description): void
    {
        $sql = "UPDATE topics SET name = :name, description = :description WHERE id = :id";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':id'          => $id,
            ':name'        => $name,
            ':description' => $description,
        ]);
    }

    /**
     * Delete a topic by ID.
     * CASCADE will remove related quizzes and questions per FK constraints.
     *
     * @param int $id
     */
    public function delete(int $id): void
    {
        $sql = "DELETE FROM topics WHERE id = :id";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':id' => $id]);
    }
}
