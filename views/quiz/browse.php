<!-- Quiz Browse Page — Card Grid Layout -->
<?php
// Expected variables:
// $quizzes          — array of quiz rows with topic_name, question_count, time_limit_min, title, etc.
// $topics           — array of topics for filter dropdown
// $selectedTopicId  — int|null currently selected topic
// $incompleteAttempt — array|null incomplete attempt data for resume banner
// $csrfToken        — CSRF token for forms
?>

<?php if ($incompleteAttempt !== null): ?>
    <!-- Resume Banner -->
    <div class="alert alert-info alert-dismissible fade show resume-banner" role="alert">
        <div class="d-flex flex-column flex-sm-row align-items-start align-items-sm-center gap-2">
            <div class="flex-grow-1">
                <strong><i class="bi bi-arrow-return-right"></i> Resume Quiz?</strong>
                You have an incomplete attempt for
                <strong><?= htmlspecialchars($incompleteAttempt['quiz_title'] ?? '', ENT_QUOTES, 'UTF-8') ?></strong>.
                Question <?= ((int) ($incompleteAttempt['current_question_index'] ?? 0)) + 1 ?>
                of <?= (int) ($incompleteAttempt['total_questions'] ?? 0) ?>.
            </div>
            <div class="d-flex gap-2 flex-shrink-0">
                <a href="/quiz/attempt/<?= (int) ($incompleteAttempt['id'] ?? 0) ?>/next"
                   class="btn btn-primary btn-sm">
                    Resume Quiz
                </a>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Not now"></button>
            </div>
        </div>
    </div>
<?php endif; ?>

<!-- Page Header -->
<div class="d-flex flex-column flex-sm-row justify-content-between align-items-start align-items-sm-center mb-4 gap-3">
    <h1 class="h3 mb-0">Available Quizzes</h1>

    <!-- Topic Filter Dropdown -->
    <div class="d-flex align-items-center gap-2">
        <label for="topicFilter" class="form-label mb-0 text-nowrap small">Filter by Topic:</label>
        <select id="topicFilter" class="form-select form-select-sm"
                onchange="if(this.value) window.location.href='/quiz/browse/' + this.value; else window.location.href='/quiz';">
            <option value="">All Topics</option>
            <?php foreach ($topics as $topic): ?>
                <option value="<?= (int) ($topic['id'] ?? 0) ?>"
                    <?= $selectedTopicId !== null && (int) ($topic['id'] ?? 0) === $selectedTopicId ? 'selected' : '' ?>>
                    <?= htmlspecialchars($topic['name'] ?? '', ENT_QUOTES, 'UTF-8') ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>
</div>

<!-- Quiz Card Grid -->
<?php if (empty($quizzes)): ?>
    <!-- Empty State -->
    <div class="empty-state text-center py-5">
        <div class="mb-3">
            <svg xmlns="http://www.w3.org/2000/svg" width="64" height="64" fill="currentColor"
                 class="bi bi-journal-text text-muted" viewBox="0 0 16 16">
                <path d="M5 10.5a.5.5 0 0 1 .5-.5h2a.5.5 0 0 1 0 1h-2a.5.5 0 0 1-.5-.5zm0-2a.5.5 0 0 1 .5-.5h5a.5.5 0 0 1 0 1h-5a.5.5 0 0 1-.5-.5zm0-2a.5.5 0 0 1 .5-.5h5a.5.5 0 0 1 0 1h-5a.5.5 0 0 1-.5-.5zm0-2a.5.5 0 0 1 .5-.5h5a.5.5 0 0 1 0 1h-5a.5.5 0 0 1-.5-.5z"/>
                <path d="M3 0h10a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V2a2 2 0 0 1 2-2zm0 1a1 1 0 0 0-1 1v12a1 1 0 0 0 1 1h10a1 1 0 0 0 1-1V2a1 1 0 0 0-1-1H3z"/>
            </svg>
        </div>
        <h5 class="text-muted">No quizzes available right now.</h5>
        <p class="text-muted small">Check back later for new quizzes from your lecturers.</p>
    </div>
<?php else: ?>
    <div class="row row-cols-1 row-cols-md-2 row-cols-lg-3 g-4">
        <?php foreach ($quizzes as $quiz): ?>
            <div class="col">
                <div class="card quiz-card h-100 shadow-sm">
                    <div class="card-body d-flex flex-column">
                        <!-- Topic Badge -->
                        <div class="d-flex justify-content-between align-items-start mb-2">
                            <span class="badge bg-primary">
                                <?= htmlspecialchars($quiz['topic_name'] ?? '', ENT_QUOTES, 'UTF-8') ?>
                            </span>
                        </div>

                        <!-- Quiz Title -->
                        <h5 class="card-title mb-2">
                            <?= htmlspecialchars($quiz['title'] ?? '', ENT_QUOTES, 'UTF-8') ?>
                        </h5>

                        <!-- Quiz Description -->
                        <?php if (!empty($quiz['description'])): ?>
                            <p class="card-text small text-muted mb-3">
                                <?= htmlspecialchars($quiz['description'], ENT_QUOTES, 'UTF-8') ?>
                            </p>
                        <?php endif; ?>

                        <!-- Quiz Meta -->
                        <div class="mt-auto">
                            <div class="d-flex justify-content-between align-items-center text-muted small mb-3">
                                <span>
                                    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="currentColor"
                                         class="me-1" viewBox="0 0 16 16">
                                        <path d="M2 1a1 1 0 0 0-1 1v4a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1V2a1 1 0 0 0-1-1H2z"/>
                                        <path d="M12 9a1 1 0 0 1 1 1v4a1 1 0 0 1-1 1h-2a1 1 0 0 1-1-1v-4a1 1 0 0 1 1-1h2z"/>
                                        <path d="M4 9a1 1 0 0 1 1 1v4a1 1 0 0 1-1 1H2a1 1 0 0 1-1-1v-4a1 1 0 0 1 1-1h2z"/>
                                    </svg>
                                    <?= (int) ($quiz['question_count'] ?? 0) ?> questions
                                </span>
                                <span>
                                    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="currentColor"
                                         class="me-1" viewBox="0 0 16 16">
                                        <path d="M8 3.5a.5.5 0 0 0-1 0V9a.5.5 0 0 0 .252.434l3.5 2a.5.5 0 0 0 .496-.868L8 8.71V3.5z"/>
                                        <path d="M8 16A8 8 0 1 0 8 0a8 8 0 0 0 0 16zm7-8A7 7 0 1 1 1 8a7 7 0 0 1 14 0z"/>
                                    </svg>
                                    <?= $quiz['time_limit_min'] !== null
                                        ? (int) $quiz['time_limit_min'] . ' min'
                                        : 'No time limit' ?>
                                </span>
                            </div>

                            <!-- Start Button -->
                            <form method="post" action="/quiz/attempt/start/<?= (int) ($quiz['id'] ?? 0) ?>"
                                  class="start-quiz-form">
                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken ?? '', ENT_QUOTES, 'UTF-8') ?>">
                                <button type="submit" class="btn btn-primary w-100 start-quiz-btn">
                                    Start Quiz
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
