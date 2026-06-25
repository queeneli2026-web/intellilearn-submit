<?php
declare(strict_types=1);

namespace App\Repositories;

use PDO;

class AnalyticsRepository
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = require __DIR__ . '/../../config/database.php';
    }

    public function getAttemptHistory(int $userId, int $page = 1, int $perPage = 10): array
    {
        $offset = max(0, ($page - 1) * $perPage);

        $sql = "SELECT a.id, a.attempt_number, a.score, a.max_score, a.percentage, a.passed,
                       a.completed_at, a.time_taken_sec, a.status,
                       q.title AS quiz_title, q.pass_percentage,
                       t.name AS topic_name
                FROM attempts a
                JOIN quizzes q ON q.id = a.quiz_id
                LEFT JOIN topics t ON t.id = q.topic_id
                WHERE a.user_id = :user_id AND a.status = 'completed'
                ORDER BY a.completed_at DESC
                LIMIT :limit OFFSET :offset";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':user_id' => $userId,
            ':limit' => $perPage,
            ':offset' => $offset,
        ]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getAttemptHistoryCount(int $userId): int
    {
        $sql = "SELECT COUNT(*) FROM attempts WHERE user_id = :user_id AND status = 'completed'";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':user_id' => $userId]);
        return (int) $stmt->fetchColumn();
    }

    public function getTopicAccuracy(int $userId): array
    {
        $sql = "SELECT t.id AS topic_id, t.name AS topic_name,
                       COUNT(r.id) AS total_questions,
                       SUM(r.is_correct) AS correct_answers,
                       ROUND(AVG(r.is_correct) * 100, 1) AS accuracy
                FROM responses r
                JOIN attempts a ON a.id = r.attempt_id
                JOIN questions q ON q.id = r.question_id
                JOIN topics t ON t.id = q.topic_id
                WHERE a.user_id = :user_id AND a.status = 'completed' AND r.is_correct IS NOT NULL
                GROUP BY t.id, t.name
                ORDER BY accuracy DESC";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':user_id' => $userId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getAccuracyTrend(int $userId, int $limit = 15): array
    {
        $sql = "SELECT a.id, a.percentage, a.completed_at, q.title AS quiz_title
                FROM attempts a
                JOIN quizzes q ON q.id = a.quiz_id
                WHERE a.user_id = :user_id AND a.status = 'completed' AND a.percentage IS NOT NULL
                ORDER BY a.completed_at ASC
                LIMIT :limit";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':user_id' => $userId, ':limit' => $limit]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getOrCreateMastery(int $userId, int $topicId): array
    {
        $sql = "SELECT * FROM topic_mastery WHERE user_id = :user_id AND topic_id = :topic_id";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':user_id' => $userId, ':topic_id' => $topicId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($row) {
            return $row;
        }

        $sql = "INSERT INTO topic_mastery (user_id, topic_id) VALUES (:user_id, :topic_id)";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':user_id' => $userId, ':topic_id' => $topicId]);

        return [
            'user_id' => $userId,
            'topic_id' => $topicId,
            'mastery_level' => 'novice',
            'total_questions' => 0,
            'correct_answers' => 0,
            'accuracy' => 0.0,
            'last_practiced' => null,
        ];
    }

    public function updateMastery(int $userId, int $topicId, int $totalQuestions, int $correctAnswers, float $accuracy, string $masteryLevel): void
    {
        $sql = "UPDATE topic_mastery
                SET total_questions = :total_questions,
                    correct_answers = :correct_answers,
                    accuracy = :accuracy,
                    mastery_level = :mastery_level,
                    last_practiced = NOW(),
                    updated_at = NOW()
                WHERE user_id = :user_id AND topic_id = :topic_id";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':total_questions' => $totalQuestions,
            ':correct_answers' => $correctAnswers,
            ':accuracy' => $accuracy,
            ':mastery_level' => $masteryLevel,
            ':user_id' => $userId,
            ':topic_id' => $topicId,
        ]);
    }

    public function getAllMastery(int $userId): array
    {
        $sql = "SELECT tm.*, t.name AS topic_name
                FROM topic_mastery tm
                JOIN topics t ON t.id = tm.topic_id
                WHERE tm.user_id = :user_id
                ORDER BY tm.accuracy DESC";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':user_id' => $userId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
