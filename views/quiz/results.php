<!--
======================================================================
Quiz Results Page (RSLT-01, SANS-03)
Server-rendered view reached after finish action redirect.

Threat mitigations (T-02R-01 through T-02R-04):
  - Ownership validated by AttemptController::resultsAction before rendering
  - Score is server-calculated, never from client (T-02R-02)
  - All dynamic output uses htmlspecialchars() (T-02R-04)
  - No other student data exposed (T-02R-03)
======================================================================
-->
<?php
// Expected: $attempt = array with:
//   id, quiz_id, status, score, max_score, percentage, passed,
//   started_at, completed_at, time_taken_sec,
//   quiz_title, pass_percentage,
//   responses: [{
//     question_id, question_text, question_type, points_earned, max_points,
//     is_correct, selected_option_id, answer_text,
//     correct_answer_data: { correct_answer, ... },
//     explanation, needs_review
//   }]

$passed = !empty($attempt['passed']);
$percentage = (float) ($attempt['percentage'] ?? 0);
$score = (float) ($attempt['score'] ?? 0);
$maxScore = (float) ($attempt['max_score'] ?? 0);
$passPct = (float) ($attempt['pass_percentage'] ?? 50);
$timeTakenSec = (int) ($attempt['time_taken_sec'] ?? 0);
$timeMinutes = intdiv($timeTakenSec, 60);
$timeSeconds = $timeTakenSec % 60;
$isTimedOut = ($attempt['status'] ?? '') === 'timed_out';
$responses = $attempt['responses'] ?? [];

// Type labels for badge display
$typeLabels = [
    'mcq_single'   => 'MCQ Single',
    'mcq_multi'    => 'MCQ Multi',
    'true_false'   => 'T/F',
    'fill_blank'   => 'Fill Blank',
    'short_answer' => 'Short Answer',
];
$typeBadgeClasses = [
    'mcq_single'   => 'bg-primary',
    'mcq_multi'    => 'bg-info',
    'true_false'   => 'bg-warning text-dark',
    'fill_blank'   => 'bg-secondary',
    'short_answer' => 'bg-dark',
];

// Resolve option text for a response by finding the matching option
function resolveSelectedOptionText(array $response): string {
    $correctData = $response['correct_answer_data'] ?? [];
    if (!empty($response['selected_option_id'])) {
        // For T/F, try to infer from is_correct context
        if ($response['question_type'] === 'true_false') {
            return !empty($response['is_correct']) ? 'True' : 'False';
        }
        // For MCQ single and multi, we can show the option ID as fallback
        return 'Option #' . (int) $response['selected_option_id'];
    }
    return '';
}
?>

<!-- Header -->
<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h3 mb-0">Quiz Complete: <?= htmlspecialchars($attempt['quiz_title'] ?? '', ENT_QUOTES, 'UTF-8') ?></h1>
    <a href="/quiz" class="btn btn-outline-primary">&larr; Back to Quizzes</a>
</div>

<!-- Score Card (RSLT-01) -->
<div class="card results-score-card mb-4">
    <div class="card-body py-4">
        <!-- Percentage -->
        <div class="display-1 mb-1"><?= htmlspecialchars(number_format($percentage, 1), ENT_QUOTES, 'UTF-8') ?>%</div>

        <!-- Score -->
        <p class="text-muted mb-3">
            <?= htmlspecialchars((string) $score, ENT_QUOTES, 'UTF-8') ?> /
            <?= htmlspecialchars((string) $maxScore, ENT_QUOTES, 'UTF-8') ?> points
        </p>

        <!-- Pass/Fail Badge -->
        <?php if ($passed): ?>
            <span class="badge results-pass-badge badge-success">Pass</span>
        <?php else: ?>
            <span class="badge results-pass-badge badge-danger">Fail</span>
        <?php endif; ?>

        <!-- Details row -->
        <div class="row mt-4 pt-3 border-top text-center">
            <div class="col-4">
                <div class="results-detail-label">Status</div>
                <div class="results-detail-value">
                    <?= $isTimedOut ? 'Timed Out' : 'Completed' ?>
                </div>
            </div>
            <div class="col-4">
                <div class="results-detail-label">Time</div>
                <div class="results-detail-value">
                    <?php if ($timeTakenSec > 0): ?>
                        <?php if ($timeMinutes > 0): ?>
                            <?= $timeMinutes ?>m <?= $timeSeconds ?>s
                        <?php else: ?>
                            <?= $timeSeconds ?>s
                        <?php endif; ?>
                    <?php else: ?>
                        <span class="text-muted fst-italic">N/A</span>
                    <?php endif; ?>
                </div>
            </div>
            <div class="col-4">
                <div class="results-detail-label">Passing</div>
                <div class="results-detail-value"><?= htmlspecialchars((string) $passPct, ENT_QUOTES, 'UTF-8') ?>%</div>
            </div>
        </div>
    </div>
</div>

<!-- Per-Question Review Accordion -->
<h5 class="mb-3">Question Review</h5>

<?php if (empty($responses)): ?>
    <div class="alert alert-info">No question data available.</div>
<?php else: ?>
    <div class="accordion results-accordion" id="resultsAccordion">
        <?php foreach ($responses as $index => $response): ?>
            <?php
                $qNum = $index + 1;
                $isCorrect = !empty($response['is_correct']);
                $qType = $response['question_type'] ?? '';
                $typeLabel = $typeLabels[$qType] ?? $qType;
                $badgeClass = $typeBadgeClasses[$qType] ?? 'bg-secondary';
                $correctData = $response['correct_answer_data'] ?? [];
                $correctAnswer = $correctData['correct_answer'] ?? '';
                $needsReview = !empty($response['needs_review']);
                $explanation = $response['explanation'] ?? '';
                $pointsEarned = (float) ($response['points_earned'] ?? 0);
                $maxPts = (float) ($response['max_points'] ?? 1.0);
            ?>
            <div class="accordion-item">
                <h2 class="accordion-header" id="resultHeading<?= $qNum ?>">
                    <button class="accordion-button collapsed" type="button"
                            data-bs-toggle="collapse"
                            data-bs-target="#resultCollapse<?= $qNum ?>"
                            aria-expanded="false"
                            aria-controls="resultCollapse<?= $qNum ?>">
                        <span class="d-flex align-items-center gap-2 w-100">
                            <span class="fw-bold me-1">Q<?= $qNum ?>.</span>
                            <?php if ($needsReview): ?>
                                <span class="badge bg-warning text-dark" title="Pending Review">&#9888;</span>
                            <?php elseif ($isCorrect): ?>
                                <span class="badge bg-success" title="Correct">&check;</span>
                            <?php else: ?>
                                <span class="badge bg-danger" title="Incorrect">&times;</span>
                            <?php endif; ?>
                            <span class="badge <?= $badgeClass ?>"><?= htmlspecialchars($typeLabel, ENT_QUOTES, 'UTF-8') ?></span>
                            <span class="text-muted small ms-auto text-nowrap">
                                <?= htmlspecialchars((string) $pointsEarned, ENT_QUOTES, 'UTF-8') ?> /
                                <?= htmlspecialchars((string) $maxPts, ENT_QUOTES, 'UTF-8') ?> pts
                            </span>
                        </span>
                    </button>
                </h2>
                <div id="resultCollapse<?= $qNum ?>"
                     class="accordion-collapse collapse"
                     aria-labelledby="resultHeading<?= $qNum ?>"
                     data-bs-parent="#resultsAccordion">
                    <div class="accordion-body">
                        <!-- Question text -->
                        <div class="mb-3">
                            <strong>Question:</strong>
                            <p class="mb-0"><?= nl2br(htmlspecialchars($response['question_text'] ?? '', ENT_QUOTES, 'UTF-8')) ?></p>
                        </div>

                        <!-- Student's Answer vs Correct Answer -->
                        <div class="row mb-2">
                            <div class="col-md-6 mb-2 mb-md-0">
                                <strong class="<?= $isCorrect ? '' : 'text-danger' ?>">Your Answer:</strong>
                                <p class="mb-0">
                                    <?php if ($qType === 'fill_blank' || $qType === 'short_answer'): ?>
                                        <?php if (!empty($response['answer_text'])): ?>
                                            <?= nl2br(htmlspecialchars($response['answer_text'], ENT_QUOTES, 'UTF-8')) ?>
                                        <?php else: ?>
                                            <span class="text-muted fst-italic">No answer given</span>
                                        <?php endif; ?>
                                    <?php elseif ($qType === 'true_false'): ?>
                                        <?php if (!empty($response['selected_option_id'])): ?>
                                            <?php
                                                // Determine True/False from context (which option was selected)
                                                $tfAnswer = !empty($response['is_correct']) ? 'True' : 'False';
                                            ?>
                                            <?= htmlspecialchars($tfAnswer, ENT_QUOTES, 'UTF-8') ?>
                                        <?php else: ?>
                                            <span class="text-muted fst-italic">No answer given</span>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <?php if (!empty($response['selected_option_id'])): ?>
                                            <?= htmlspecialchars(resolveSelectedOptionText($response), ENT_QUOTES, 'UTF-8') ?>
                                        <?php else: ?>
                                            <span class="text-muted fst-italic">No answer given</span>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </p>
                            </div>
                            <div class="col-md-6">
                                <strong class="text-success">Correct Answer:</strong>
                                <p class="mb-0">
                                    <?php if (!empty($correctAnswer)): ?>
                                        <?= htmlspecialchars($correctAnswer, ENT_QUOTES, 'UTF-8') ?>
                                    <?php else: ?>
                                        <span class="text-muted fst-italic">N/A</span>
                                    <?php endif; ?>
                                </p>
                            </div>
                        </div>

                        <!-- Result label -->
                        <div class="mb-2">
                            <?php if ($needsReview): ?>
                                <span class="badge bg-warning text-dark">Pending Review</span>
                            <?php elseif ($isCorrect): ?>
                                <span class="badge bg-success">Correct</span>
                            <?php else: ?>
                                <span class="badge bg-danger">Incorrect</span>
                            <?php endif; ?>
                        </div>

                        <!-- Pending Review alert for short answer (SANS-03) -->
                        <?php if ($needsReview && $qType === 'short_answer'): ?>
                            <div class="alert alert-warning mt-2 py-2 mb-2">
                                <strong>Pending Review</strong> &mdash; This short answer requires manual grading
                                by your lecturer. Your score may change after review.
                            </div>
                        <?php endif; ?>

                        <!-- Explanation -->
                        <?php if (!empty($explanation)): ?>
                            <div class="mt-2 p-3 bg-light rounded">
                                <strong>Explanation:</strong>
                                <p class="mb-0"><?= nl2br(htmlspecialchars($explanation, ENT_QUOTES, 'UTF-8')) ?></p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<!-- Bottom navigation -->
<div class="mt-4 text-center">
    <a href="/quiz" class="btn btn-primary btn-lg">Back to Quizzes</a>
</div>
