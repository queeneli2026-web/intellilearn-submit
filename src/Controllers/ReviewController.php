<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Services\SpacedRepetitionService;
use App\Services\GamificationService;
use App\Repositories\SpacedRepetitionRepository;
use App\Repositories\QuestionRepository;

class ReviewController extends StudentController
{
    private SpacedRepetitionService $spacedService;
    private GamificationService $gamificationService;
    private SpacedRepetitionRepository $spacedRepo;
    private QuestionRepository $questionRepo;

    public function __construct()
    {
        $this->spacedService = new SpacedRepetitionService();
        $this->gamificationService = new GamificationService();
        $this->spacedRepo = new SpacedRepetitionRepository();
        $this->questionRepo = new QuestionRepository();
    }

    public function dashboardAction(): void
    {
        $this->requireStudentAuth();
        $userId = (int) ($_SESSION['user_id'] ?? 0);

        $reviewData = $this->spacedService->getReviewData($userId);
        $gamificationData = $this->gamificationService->getGamificationData($userId);

        $this->render('views/review/dashboard.php', [
            'dueTopics' => $reviewData['due'],
            'upcomingReviews' => $reviewData['upcoming'],
            'allBadges' => $gamificationData['allBadges'],
            'userBadges' => $gamificationData['userBadges'],
            'xp' => $gamificationData['stats']['xp'] ?? 0,
            'streak' => $gamificationData['stats']['streak_count'] ?? 0,
        ], 'Spaced Review');
    }

    public function startAction(int $topicId): void
    {
        $this->requireStudentAuth();
        $userId = (int) ($_SESSION['user_id'] ?? 0);

        $questions = $this->spacedRepo->getReviewQuestions($userId, $topicId);

        $_SESSION['review_index'] = 0;
        unset($_SESSION['review_feedback']);

        $topicName = $this->getTopicName($topicId);

        $this->render('views/review/session.php', [
            'topicId' => $topicId,
            'topicName' => $topicName,
            'questions' => $questions,
        ], 'Review: ' . $topicName);
    }

    public function answerAction(int $topicId): void
    {
        $this->requireStudentAuth();
        $this->requireCsrf();
        $userId = (int) ($_SESSION['user_id'] ?? 0);

        $questionId = (int) ($_POST['question_id'] ?? 0);
        $answerText = trim($_POST['answer_text'] ?? '');

        $question = $this->questionRepo->getById($questionId);
        if ($question === null) {
            header('Location: /review/' . $topicId);
            exit;
        }

        $isCorrect = $this->checkAnswer($question, $answerText);

        $_SESSION['review_feedback'] = [
            'question_id' => $questionId,
            'question_text' => $question['question_text'] ?? '',
            'is_correct' => $isCorrect,
            'explanation' => $question['explanation'] ?? '',
        ];

        header('Location: /review/' . $topicId);
        exit;
    }

    public function rateAction(int $topicId): void
    {
        $this->requireStudentAuth();
        $this->requireCsrf();
        $userId = (int) ($_SESSION['user_id'] ?? 0);

        $quality = (int) ($_POST['quality'] ?? 4);

        $record = $this->spacedRepo->getOrCreate($userId, $topicId);
        $result = $this->spacedService->sm2Calculate(
            (float) ($record['ef'] ?? 2.5),
            (int) ($record['repetitions'] ?? 0),
            $quality
        );

        $this->spacedRepo->updateSchedule(
            (int) $record['id'],
            $result['ef'],
            $result['interval_days'],
            $result['repetitions'],
            $result['next_review_at']
        );

        $currentIndex = (int) ($_SESSION['review_index'] ?? 0);
        $_SESSION['review_index'] = $currentIndex + 1;
        unset($_SESSION['review_feedback']);

        header('Location: /review/' . $topicId);
        exit;
    }

    private function checkAnswer(array $question, string $answer): bool
    {
        $type = $question['question_type'] ?? '';
        $questionId = (int) ($question['id'] ?? 0);

        if ($type === 'fill_blank') {
            $pdo = $this->getPdo();
            $stmt = $pdo->prepare("SELECT correct_answer, alternative_answers FROM fill_blank_answers WHERE question_id = :qid LIMIT 1");
            $stmt->execute([':qid' => $questionId]);
            $row = $stmt->fetch(\PDO::FETCH_ASSOC);

            if ($row) {
                $normalized = trim(strtolower($answer));
                $correct = trim(strtolower($row['correct_answer'] ?? ''));
                if ($normalized === $correct) return true;

                $alts = json_decode($row['alternative_answers'] ?? '[]', true) ?? [];
                foreach ($alts as $alt) {
                    if ($normalized === trim(strtolower($alt))) return true;
                }
            }
            return false;
        }

        if ($type === 'short_answer') {
            $pdo = $this->getPdo();
            $stmt = $pdo->prepare("SELECT keyword, synonyms FROM short_answer_keywords WHERE question_id = :qid");
            $stmt->execute([':qid' => $questionId]);
            $keywords = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            $answerLower = trim(strtolower($answer));
            foreach ($keywords as $kw) {
                $keywordLower = trim(strtolower($kw['keyword'] ?? ''));
                if (str_contains($answerLower, $keywordLower)) return true;

                $synonyms = json_decode($kw['synonyms'] ?? '[]', true) ?? [];
                foreach ($synonyms as $syn) {
                    if (str_contains($answerLower, trim(strtolower($syn)))) return true;
                }
            }
            return false;
        }

        if ($type === 'true_false') {
            $pdo = $this->getPdo();
            $stmt = $pdo->prepare("SELECT is_true FROM true_false_options WHERE question_id = :qid");
            $stmt->execute([':qid' => $questionId]);
            $row = $stmt->fetch(\PDO::FETCH_ASSOC);
            $expected = $row ? (bool) $row['is_true'] : false;
            $userAnswer = strtolower(trim($answer)) === 'true';
            return $expected === $userAnswer;
        }

        return false;
    }

    private function getTopicName(int $topicId): string
    {
        $stmt = $this->getPdo()->prepare("SELECT name FROM topics WHERE id = :id");
        $stmt->execute([':id' => $topicId]);
        $topic = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $topic['name'] ?? 'Unknown Topic';
    }

    private function getPdo(): \PDO
    {
        return require __DIR__ . '/../../config/database.php';
    }
}
