<?php
declare(strict_types=1);

namespace App\Repositories;

use PDO;

class GamificationRepository
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = require __DIR__ . '/../../config/database.php';
    }

    public function getUserStats(int $userId): array
    {
        $sql = "SELECT xp, streak_count, last_activity_date FROM users WHERE id = :id";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':id' => $userId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: ['xp' => 0, 'streak_count' => 0, 'last_activity_date' => null];
    }

    public function updateXp(int $userId, int $xp): void
    {
        $sql = "UPDATE users SET xp = :xp WHERE id = :id";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':xp' => $xp, ':id' => $userId]);
    }

    public function updateStreak(int $userId, int $count, string $lastDate): void
    {
        $sql = "UPDATE users SET streak_count = :count, last_activity_date = :last_date WHERE id = :id";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':count' => $count, ':last_date' => $lastDate, ':id' => $userId]);
    }

    public function getAllBadges(): array
    {
        $sql = "SELECT * FROM badges ORDER BY id ASC";
        $stmt = $this->pdo->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getUserBadges(int $userId): array
    {
        $sql = "SELECT b.id, b.badge_key, b.name, b.description, b.icon, ub.awarded_at
                FROM user_badges ub
                JOIN badges b ON b.id = ub.badge_id
                WHERE ub.user_id = :user_id
                ORDER BY ub.awarded_at ASC";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':user_id' => $userId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function awardBadge(int $userId, int $badgeId): void
    {
        $sql = "INSERT IGNORE INTO user_badges (user_id, badge_id) VALUES (:user_id, :badge_id)";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':user_id' => $userId, ':badge_id' => $badgeId]);
    }

    public function getBadgeByKey(string $key): ?array
    {
        $sql = "SELECT * FROM badges WHERE badge_key = :key";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':key' => $key]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function getCompletedQuizCount(int $userId): int
    {
        $sql = "SELECT COUNT(*) FROM attempts WHERE user_id = :user_id AND status = 'completed'";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':user_id' => $userId]);
        return (int) $stmt->fetchColumn();
    }

    public function getPerfectScoreCount(int $userId): int
    {
        $sql = "SELECT COUNT(*) FROM attempts WHERE user_id = :user_id AND status = 'completed' AND percentage >= 100";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':user_id' => $userId]);
        return (int) $stmt->fetchColumn();
    }

    public function getTopicCount(int $userId): int
    {
        $sql = "SELECT COUNT(*) FROM topic_mastery WHERE user_id = :user_id AND mastery_level = 'expert'";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':user_id' => $userId]);
        return (int) $stmt->fetchColumn();
    }

    public function getTotalTopics(): int
    {
        $sql = "SELECT COUNT(*) FROM topics";
        $stmt = $this->pdo->query($sql);
        return (int) $stmt->fetchColumn();
    }
}
