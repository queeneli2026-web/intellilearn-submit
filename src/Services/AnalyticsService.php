<?php
declare(strict_types=1);

namespace App\Services;

use App\Repositories\AnalyticsRepository;

class AnalyticsService
{
    private AnalyticsRepository $repo;

    public function __construct()
    {
        $this->repo = new AnalyticsRepository();
    }

    public function calculateMasteryLevel(float $accuracy, int $totalQuestions): string
    {
        if ($totalQuestions >= 10 && $accuracy >= 81) {
            return 'expert';
        }
        if ($totalQuestions >= 3 && $accuracy >= 61) {
            return 'proficient';
        }
        if ($accuracy >= 41) {
            return 'apprentice';
        }
        return 'novice';
    }

    public function recalculateMastery(int $userId, int $topicId): void
    {
        $topicAccuracy = $this->repo->getTopicAccuracy($userId);

        $accuracy = 0.0;
        $totalQuestions = 0;
        $correctAnswers = 0;

        foreach ($topicAccuracy as $ta) {
            if ((int) $ta['topic_id'] === $topicId) {
                $accuracy = (float) ($ta['accuracy'] ?? 0);
                $totalQuestions = (int) ($ta['total_questions'] ?? 0);
                $correctAnswers = (int) ($ta['correct_answers'] ?? 0);
                break;
            }
        }

        $level = $this->calculateMasteryLevel($accuracy, $totalQuestions);
        $this->repo->updateMastery($userId, $topicId, $totalQuestions, $correctAnswers, $accuracy, $level);
    }

    public function getDashboardData(int $userId, int $page = 1): array
    {
        $attempts = $this->repo->getAttemptHistory($userId, $page);
        $totalAttempts = $this->repo->getAttemptHistoryCount($userId);
        $topicAccuracy = $this->repo->getTopicAccuracy($userId);
        $trend = $this->repo->getAccuracyTrend($userId);
        $mastery = $this->repo->getAllMastery($userId);

        return [
            'attempts' => $attempts,
            'totalAttempts' => $totalAttempts,
            'topicAccuracy' => $topicAccuracy,
            'trend' => $trend,
            'mastery' => $mastery,
        ];
    }
}
