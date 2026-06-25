<?php
declare(strict_types=1);

namespace App\Services;

use App\Repositories\SpacedRepetitionRepository;
use App\Repositories\QuestionRepository;

class SpacedRepetitionService
{
    private SpacedRepetitionRepository $repo;
    private QuestionRepository $questionRepo;

    public function __construct()
    {
        $this->repo = new SpacedRepetitionRepository();
        $this->questionRepo = new QuestionRepository();
    }

    public function sm2Calculate(float $ef, int $repetitions, int $quality): array
    {
        $quality = max(0, min(6, $quality));

        $newEF = $ef + (0.1 - (5 - $quality) * (0.08 + (5 - $quality) * 0.02));
        if ($newEF < 1.3) {
            $newEF = 1.3;
        }

        if ($quality < 3) {
            $newRepetitions = 0;
            $newInterval = 1;
        } else {
            $newRepetitions = $repetitions + 1;
            if ($newRepetitions === 1) {
                $newInterval = 1;
            } elseif ($newRepetitions === 2) {
                $newInterval = 6;
            } else {
                $newInterval = (int) round($repetitions * $newEF);
            }
        }

        $nextDate = date('Y-m-d H:i:s', strtotime("+{$newInterval} days"));

        return [
            'ef' => round($newEF, 2),
            'interval_days' => $newInterval,
            'repetitions' => $newRepetitions,
            'next_review_at' => $nextDate,
        ];
    }

    public function scheduleAfterQuiz(int $userId, int $quizId): void
    {
        $topicIds = $this->questionRepo->getTopicIdsByQuiz($quizId);

        foreach ($topicIds as $topicId) {
            $record = $this->repo->getOrCreate($userId, $topicId);
            $result = $this->sm2Calculate(
                (float) ($record['ef'] ?? 2.5),
                (int) ($record['repetitions'] ?? 0),
                4
            );

            $this->repo->updateSchedule(
                (int) $record['id'],
                $result['ef'],
                $result['interval_days'],
                $result['repetitions'],
                $result['next_review_at']
            );
        }
    }

    public function getReviewData(int $userId): array
    {
        $due = $this->repo->getDueTopics($userId);
        $upcoming = $this->repo->getUpcomingReviews($userId);
        return ['due' => $due, 'upcoming' => $upcoming];
    }
}
