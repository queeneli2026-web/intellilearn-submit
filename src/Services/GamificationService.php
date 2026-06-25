<?php
declare(strict_types=1);

namespace App\Services;

use App\Repositories\GamificationRepository;

class GamificationService
{
    private GamificationRepository $repo;

    public function __construct()
    {
        $this->repo = new GamificationRepository();
    }

    public function addXp(int $userId, int $amount): void
    {
        $stats = $this->repo->getUserStats($userId);
        $multiplier = ((int) ($stats['streak_count'] ?? 0) >= 7) ? 1.5 : 1.0;
        $award = (int) round($amount * $multiplier);
        $newXp = (int) ($stats['xp'] ?? 0) + $award;
        $this->repo->updateXp($userId, $newXp);
    }

    public function updateStreak(int $userId): void
    {
        $stats = $this->repo->getUserStats($userId);
        $today = date('Y-m-d');
        $lastDate = $stats['last_activity_date'] ?? null;

        if ($lastDate === null) {
            $this->repo->updateStreak($userId, 1, $today);
        } elseif ($lastDate === $today) {
            return;
        } else {
            $yesterday = date('Y-m-d', strtotime('-1 day'));
            if ($lastDate === $yesterday) {
                $newCount = (int) ($stats['streak_count'] ?? 0) + 1;
                $this->repo->updateStreak($userId, $newCount, $today);
            } else {
                $this->repo->updateStreak($userId, 1, $today);
            }
        }
    }

    public function checkAndAwardBadges(int $userId, int $quizId): array
    {
        $awarded = [];
        $allBadges = $this->repo->getAllBadges();
        $userBadges = $this->repo->getUserBadges($userId);
        $earnedKeys = array_map(fn($b) => $b['badge_key'], $userBadges);

        foreach ($allBadges as $badge) {
            if (in_array($badge['badge_key'], $earnedKeys, true)) {
                continue;
            }

            if ($this->shouldAward($userId, $badge['badge_key'], $quizId)) {
                $this->repo->awardBadge($userId, (int) $badge['id']);
                $awarded[] = $badge;
            }
        }

        return $awarded;
    }

    private function shouldAward(int $userId, string $badgeKey, int $quizId): bool
    {
        return match ($badgeKey) {
            'first_quiz' => $this->repo->getCompletedQuizCount($userId) >= 1,
            'perfect_score' => $this->repo->getPerfectScoreCount($userId) >= 1,
            'streak_3' => ($this->repo->getUserStats($userId)['streak_count'] ?? 0) >= 3,
            'streak_7' => ($this->repo->getUserStats($userId)['streak_count'] ?? 0) >= 7,
            'ten_quizzes' => $this->repo->getCompletedQuizCount($userId) >= 10,
            'all_expert' => $this->repo->getTopicCount($userId) >= $this->repo->getTotalTopics() && $this->repo->getTotalTopics() > 0,
            default => false,
        };
    }

    public function getGamificationData(int $userId): array
    {
        return [
            'stats' => $this->repo->getUserStats($userId),
            'allBadges' => $this->repo->getAllBadges(),
            'userBadges' => $this->repo->getUserBadges($userId),
        ];
    }
}
