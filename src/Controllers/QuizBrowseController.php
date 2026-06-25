<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Repositories\StudentQuizRepository;

/**
 * Controller for student quiz browsing page.
 *
 * Handles displaying available quizzes with topic filtering,
 * resume detection, and card-grid rendering.
 *
 * Threat mitigations:
 *   - T-02F-01: requireStudentAuth() on every action
 *   - T-02F-02: Topic filter uses whitelist (intval from DB) not arbitrary values
 *   - T-02F-03: Only is_active=1 quizzes shown — unpublished hidden from students
 *   - T-02F-04: htmlspecialchars() on all dynamic output
 */
class QuizBrowseController extends StudentController
{
    private StudentQuizRepository $quizRepo;

    public function __construct()
    {
        $this->quizRepo = new StudentQuizRepository();
    }

    /**
     * Display the quiz browse page with card grid layout.
     *
     * GET /quiz
     * GET /quiz/browse
     * GET /quiz/browse/{topicId}
     *
     * @param  string|null $topicId URL parameter
     */
    public function indexAction(?string $topicId = null): void
    {
        $this->requireStudentAuth();

        // T-02F-02: Convert topicId to int (whitelist-safe — only valid topic IDs from DB)
        $topicIdInt = $topicId !== null && $topicId !== '' ? (int) $topicId : null;

        // Check for incomplete attempt (resume detection)
        $userId = (int) ($_SESSION['user_id'] ?? 0);
        $incompleteAttempt = $this->quizRepo->findAnyIncomplete($userId);

        // Load active quizzes (T-02F-03: only is_active=1)
        $quizzes = $this->quizRepo->getActiveQuizzes($topicIdInt);

        // Load topics for filter dropdown
        $topics = $this->quizRepo->getTopics();

        $this->render('views/quiz/browse.php', [
            'quizzes'            => $quizzes,
            'topics'             => $topics,
            'selectedTopicId'    => $topicIdInt,
            'incompleteAttempt'  => $incompleteAttempt,
        ], 'Browse Quizzes');
    }
}
