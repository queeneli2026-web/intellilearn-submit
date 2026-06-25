<?php
declare(strict_types=1);

namespace App\Repositories;

use PDO;

class SpacedRepetitionRepository
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = require __DIR__ . '/../../config/database.php';
    }

    public function getOrCreate(int $userId, int $topicId): array
    {
        $sql = "SELECT * FROM spaced_repetition WHERE user_id = :user_id AND topic_id = :topic_id";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':user_id' => $userId, ':topic_id' => $topicId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($row) {
            return $row;
        }

        $sql = "INSERT INTO spaced_repetition (user_id, topic_id) VALUES (:user_id, :topic_id)";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':user_id' => $userId, ':topic_id' => $topicId]);
        return $this->getOrCreate($userId, $topicId);
    }

    public function updateSchedule(int $id, float $ef, int $intervalDays, int $repetitions, string $nextReviewAt): void
    {
        $sql = "UPDATE spaced_repetition
                SET ef = :ef, interval_days = :interval_days, repetitions = :repetitions,
                    next_review_at = :next_review_at, last_reviewed_at = NOW(), updated_at = NOW()
                WHERE id = :id";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':ef' => $ef,
            ':interval_days' => $intervalDays,
            ':repetitions' => $repetitions,
            ':next_review_at' => $nextReviewAt,
            ':id' => $id,
        ]);
    }

    public function getDueTopics(int $userId): array
    {
        $sql = "SELECT sr.*, t.name AS topic_name
                FROM spaced_repetition sr
                JOIN topics t ON t.id = sr.topic_id
                WHERE sr.user_id = :user_id
                  AND (sr.next_review_at IS NULL OR sr.next_review_at <= NOW())
                ORDER BY sr.next_review_at ASC";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':user_id' => $userId]);
        $topics = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($topics as &$t) {
            $t['question_count'] = $this->getTopicDueQuestionCount($userId, (int) $t['topic_id']);
        }
        return $topics;
    }

    public function getUpcomingReviews(int $userId, int $days = 30): array
    {
        $sql = "SELECT sr.*, t.name AS topic_name
                FROM spaced_repetition sr
                JOIN topics t ON t.id = sr.topic_id
                WHERE sr.user_id = :user_id
                  AND sr.next_review_at IS NOT NULL
                  AND sr.next_review_at > NOW()
                  AND sr.next_review_at <= DATE_ADD(NOW(), INTERVAL :days DAY)
                ORDER BY sr.next_review_at ASC";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':user_id' => $userId, ':days' => $days]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getTopicDueQuestionCount(int $userId, int $topicId): int
    {
        $sql = "SELECT COUNT(*) FROM questions q
                WHERE q.topic_id = :topic_id AND q.is_active = 1
                  AND q.id NOT IN (
                      SELECT r.question_id FROM responses r
                      JOIN attempts a ON a.id = r.attempt_id
                      WHERE a.user_id = :user_id AND r.is_correct = 1
                  )";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':user_id' => $userId, ':topic_id' => $topicId]);
        return (int) $stmt->fetchColumn();
    }

    public function getReviewQuestions(int $userId, int $topicId, int $limit = 20): array
    {
        $sql = "SELECT q.* FROM questions q
                WHERE q.topic_id = :topic_id AND q.is_active = 1
                  AND q.id NOT IN (
                      SELECT r.question_id FROM responses r
                      JOIN attempts a ON a.id = r.attempt_id
                      WHERE a.user_id = :user_id AND r.is_correct = 1
                  )
                ORDER BY RAND()
                LIMIT :limit";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':user_id' => $userId, ':topic_id' => $topicId, ':limit' => $limit]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
