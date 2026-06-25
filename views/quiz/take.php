<!-- Quiz Take Page — Single Question per Page -->
<?php
// Expected variables:
// $attempt   — array with id, quiz, current_question_index, question_order, started_at, ends_at
// $question  — array with id, text, type, options, points
// $quiz      — array with title, time_limit_min
// $progress  — array with answered, total
// $csrfToken — CSRF token for AJAX submissions

$totalQuestions = (int) ($progress['total'] ?? 0);
$currentIndex   = (int) ($progress['answered'] ?? 0);
$currentDisplay = $currentIndex + 1; // 1-based for display
$progressPct    = $totalQuestions > 0 ? round(($currentIndex / $totalQuestions) * 100) : 0;
$isLastQuestion = $currentDisplay >= $totalQuestions;
$isFirstQuestion = $currentDisplay <= 1;
?>

<!-- Timer Bar (fixed top) -->
<div id="timer-bar" class="timer-bar timer-green" style="width: 100%;"></div>

<!-- Top Info Bar -->
<div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-4 py-2 border-bottom gap-2">
    <!-- Quiz Title -->
    <h5 class="mb-0 text-truncate me-3">
        <?= htmlspecialchars($quiz['title'] ?? '', ENT_QUOTES, 'UTF-8') ?>
    </h5>

    <!-- Progress Bar + Counter -->
    <div class="d-flex align-items-center gap-3 flex-shrink-0">
        <div class="d-flex align-items-center gap-2">
            <div class="progress" style="width: 120px; height: 10px;">
                <div class="progress-bar progress-bar-striped progress-bar-animated"
                     role="progressbar"
                     style="width: <?= $progressPct ?>%;"
                     aria-valuenow="<?= $progressPct ?>"
                     aria-valuemin="0"
                     aria-valuemax="100"
                     id="progress-bar">
                </div>
            </div>
            <small class="text-muted text-nowrap" id="question-counter">
                Question <?= $currentDisplay ?> of <?= $totalQuestions ?>
            </small>
        </div>

        <!-- Timer Display -->
        <div id="timer-display" class="fw-bold text-nowrap" style="min-width: 60px; text-align: right;">
            <?php if ($quiz['time_limit_min'] !== null): ?>
                --:--
            <?php else: ?>
                <span class="text-muted small">No time limit</span>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Question Area -->
<div class="row justify-content-center">
    <div class="col-lg-8 col-md-10">
        <div class="take-question">
            <!-- Question Header -->
            <div class="d-flex justify-content-between align-items-start mb-3">
                <h4 class="mb-0 flex-grow-1">
                    <?= nl2br(htmlspecialchars($question['question_text'] ?? '', ENT_QUOTES, 'UTF-8')) ?>
                </h4>
                <span class="badge bg-secondary ms-2 flex-shrink-0">
                    <?= (float) ($question['points'] ?? 1.0) ?> pts
                </span>
            </div>

            <!-- Question Type Indicator -->
            <div class="mb-3">
                <?php
                $typeLabels = [
                    'mcq_single'   => 'Choose one answer',
                    'mcq_multi'    => 'Select all that apply',
                    'true_false'   => 'True or False?',
                    'fill_blank'   => 'Fill in the blank',
                    'short_answer' => 'Short answer',
                ];
                $typeLabel = $typeLabels[$question['question_type'] ?? ''] ?? 'Answer the question';
                ?>
                <small class="text-muted"><?= htmlspecialchars($typeLabel, ENT_QUOTES, 'UTF-8') ?></small>
            </div>

            <form id="answer-form" novalidate>
                <input type="hidden" name="question_id" value="<?= (int) ($question['question_id'] ?? $question['id'] ?? 0) ?>">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken ?? '', ENT_QUOTES, 'UTF-8') ?>">

                <?php
                $qType = $question['question_type'] ?? '';
                $options = $question['options'] ?? [];
                $qId = (int) ($question['question_id'] ?? $question['id'] ?? 0);
                ?>

                <?php if ($qType === 'mcq_single'): ?>
                    <!-- MCQ Single — Radio Buttons -->
                    <div class="list-group mb-3">
                        <?php foreach ($options as $opt): ?>
                            <label class="list-group-item list-group-item-action d-flex align-items-center gap-3 option-item">
                                <input type="radio"
                                       name="selected_option_id"
                                       value="<?= (int) ($opt['id'] ?? 0) ?>"
                                       class="form-check-input mt-0"
                                       required>
                                <span><?= htmlspecialchars($opt['option_text'] ?? '', ENT_QUOTES, 'UTF-8') ?></span>
                            </label>
                        <?php endforeach; ?>
                    </div>

                <?php elseif ($qType === 'mcq_multi'): ?>
                    <!-- MCQ Multi — Checkboxes -->
                    <div class="list-group mb-3">
                        <?php foreach ($options as $opt): ?>
                            <label class="list-group-item list-group-item-action d-flex align-items-center gap-3 option-item">
                                <input type="checkbox"
                                       name="answer_options[]"
                                       value="<?= (int) ($opt['id'] ?? 0) ?>"
                                       class="form-check-input mt-0">
                                <span><?= htmlspecialchars($opt['option_text'] ?? '', ENT_QUOTES, 'UTF-8') ?></span>
                            </label>
                        <?php endforeach; ?>
                    </div>

                <?php elseif ($qType === 'true_false'): ?>
                    <!-- True/False — Two Large Buttons -->
                    <div class="d-flex gap-3 mb-3">
                        <button type="button"
                                class="btn btn-outline-success btn-lg flex-fill option-btn py-4"
                                data-value="1"
                                onclick="selectTrueFalse(this, 1)">
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="currentColor"
                                 class="me-2" viewBox="0 0 16 16">
                                <path d="M13.854 3.646a.5.5 0 0 1 0 .708l-7 7a.5.5 0 0 1-.708 0l-3.5-3.5a.5.5 0 1 1 .708-.708L6.5 10.293l6.646-6.647a.5.5 0 0 1 .708 0z"/>
                            </svg>
                            True
                        </button>
                        <button type="button"
                                class="btn btn-outline-danger btn-lg flex-fill option-btn py-4"
                                data-value="0"
                                onclick="selectTrueFalse(this, 0)">
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="currentColor"
                                 class="me-2" viewBox="0 0 16 16">
                                <path d="M4.646 4.646a.5.5 0 0 1 .708 0L8 7.293l2.646-2.647a.5.5 0 0 1 .708.708L8.707 8l2.647 2.646a.5.5 0 0 1-.708.708L8 8.707l-2.646 2.647a.5.5 0 0 1-.708-.708L7.293 8 4.646 5.354a.5.5 0 0 1 0-.708z"/>
                            </svg>
                            False
                        </button>
                        <input type="hidden" name="selected_option_id" value="">
                    </div>

                <?php elseif ($qType === 'fill_blank'): ?>
                    <!-- Fill in the Blank — Text Input -->
                    <div class="mb-3">
                        <input type="text"
                               class="form-control form-control-lg"
                               name="answer_text"
                               placeholder="Type your answer..."
                               autocomplete="off"
                               required>
                    </div>

                <?php elseif ($qType === 'short_answer'): ?>
                    <!-- Short Answer — Textarea -->
                    <div class="mb-3">
                        <textarea class="form-control"
                                  name="answer_text"
                                  rows="4"
                                  placeholder="Type your answer..."
                                  required></textarea>
                    </div>

                <?php endif; ?>
            </form>

            <!-- Feedback Area (populated by JS) -->
            <div id="feedback-area" class="mt-3"></div>

            <!-- Navigation Buttons -->
            <div class="d-flex justify-content-between mt-4 pt-3 border-top">
                <div>
                    <?php if (!$isFirstQuestion): ?>
                        <button type="button" class="btn btn-outline-secondary" id="prev-btn" onclick="window.quizState?.previousQuestion()">
                            &larr; Previous
                        </button>
                    <?php else: ?>
                        <button type="button" class="btn btn-outline-secondary" disabled>&larr; Previous</button>
                    <?php endif; ?>
                </div>
                <div class="d-flex gap-2">
                    <?php if ($isLastQuestion): ?>
                        <button type="button" class="btn btn-success" id="submit-quiz-btn" data-bs-toggle="modal" data-bs-target="#submitModal">
                            Submit Quiz
                        </button>
                    <?php else: ?>
                        <button type="button" class="btn btn-primary" id="next-btn" onclick="window.quizState?.nextQuestion()">
                            Next &rarr;
                        </button>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Save Progress Toast (for auto-save indicator D-05) -->
<div class="toast-container position-fixed bottom-0 end-0 p-3">
    <div id="saved-toast" class="toast saved-toast" role="alert" aria-live="assertive" aria-atomic="true" data-bs-delay="2000">
        <div class="toast-body d-flex align-items-center gap-2">
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor"
                 class="text-success" viewBox="0 0 16 16">
                <path d="M13.854 3.646a.5.5 0 0 1 0 .708l-7 7a.5.5 0 0 1-.708 0l-3.5-3.5a.5.5 0 1 1 .708-.708L6.5 10.293l6.646-6.647a.5.5 0 0 1 .708 0z"/>
            </svg>
            <span>Saved</span>
        </div>
    </div>
</div>

<!-- Submit Confirmation Modal -->
<div class="modal fade" id="submitModal" tabindex="-1" aria-labelledby="submitModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="submitModalLabel">Submit Quiz</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to submit your quiz?</p>
                <p class="text-muted small mb-0">Unanswered questions will be marked as incorrect. You cannot change your answers after submission.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="confirm-submit-btn"
                        onclick="window.quizState?.submitQuiz()">
                    Submit Quiz
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Hidden Quiz Configuration for JS -->
<script>
const QUIZ_CONFIG = {
    attemptId: <?= json_encode((int) ($attempt['id'] ?? 0)) ?>,
    quizTitle: <?= json_encode($quiz['title'] ?? '') ?>,
    totalQuestions: <?= json_encode($totalQuestions) ?>,
    timeLimit: <?= json_encode($quiz['time_limit_min'] !== null ? (int) $quiz['time_limit_min'] : null) ?>,
    startedAt: <?= json_encode($attempt['started_at'] ?? '') ?>,
    currentIndex: <?= json_encode($currentIndex) ?>,
    csrfToken: <?= json_encode($csrfToken ?? '') ?>
};
</script>
