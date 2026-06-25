<?php
$questions = $questions ?? [];
$total = count($questions);
$currentIndex = (int) ($_SESSION['review_index'] ?? 0);
$feedback = $_SESSION['review_feedback'] ?? null;
$currentQuestion = $feedback === null && $currentIndex < $total ? $questions[$currentIndex] : null;
?>

<div class="review-session">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h4 class="mb-0">Review: <?= htmlspecialchars($topicName ?? '', ENT_QUOTES, 'UTF-8') ?></h4>
        <a href="/review" class="btn btn-outline-secondary btn-sm">&larr; Dashboard</a>
    </div>

    <?php if ($total === 0): ?>
        <div class="card shadow-sm">
            <div class="card-body text-center py-5">
                <p class="text-muted mb-2">All questions reviewed in this topic!</p>
                <a href="/review" class="btn btn-primary">Back to Dashboard</a>
            </div>
        </div>

    <?php elseif ($feedback !== null): ?>
        <!-- Show feedback after answer -->
        <div class="card shadow-sm mb-4">
            <div class="card-body">
                <div class="d-flex justify-content-between mb-3">
                    <span class="badge bg-secondary">Question <?= $currentIndex + 1 ?> of <?= $total ?></span>
                </div>

                <h5 class="mb-3"><?= nl2br(htmlspecialchars($feedback['question_text'] ?? '', ENT_QUOTES, 'UTF-8')) ?></h5>

                <div class="alert <?= $feedback['is_correct'] ? 'alert-success' : 'alert-danger' ?>">
                    <?php if ($feedback['is_correct']): ?>
                        &#10004; Correct!
                    <?php else: ?>
                        &#10008; Incorrect
                    <?php endif; ?>
                    <?php if (!empty($feedback['explanation'])): ?>
                        <div class="mt-1 small"><?= nl2br(htmlspecialchars($feedback['explanation'], ENT_QUOTES, 'UTF-8')) ?></div>
                    <?php endif; ?>
                </div>

                <p class="fw-medium mb-3">How well did you know this?</p>
                <div class="rating-grid">
                    <form method="POST" action="/review/<?= (int) ($topicId ?? 0) ?>/rate" class="d-inline">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken ?? '', ENT_QUOTES, 'UTF-8') ?>">
                        <input type="hidden" name="quality" value="0">
                        <input type="hidden" name="question_id" value="<?= (int) ($feedback['question_id'] ?? 0) ?>">
                        <button type="submit" class="btn btn-outline-danger btn-lg rating-btn">Again</button>
                    </form>
                    <form method="POST" action="/review/<?= (int) ($topicId ?? 0) ?>/rate" class="d-inline">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken ?? '', ENT_QUOTES, 'UTF-8') ?>">
                        <input type="hidden" name="quality" value="2">
                        <input type="hidden" name="question_id" value="<?= (int) ($feedback['question_id'] ?? 0) ?>">
                        <button type="submit" class="btn btn-outline-warning btn-lg rating-btn">Hard</button>
                    </form>
                    <form method="POST" action="/review/<?= (int) ($topicId ?? 0) ?>/rate" class="d-inline">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken ?? '', ENT_QUOTES, 'UTF-8') ?>">
                        <input type="hidden" name="quality" value="4">
                        <input type="hidden" name="question_id" value="<?= (int) ($feedback['question_id'] ?? 0) ?>">
                        <button type="submit" class="btn btn-outline-success btn-lg rating-btn">Good</button>
                    </form>
                    <form method="POST" action="/review/<?= (int) ($topicId ?? 0) ?>/rate" class="d-inline">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken ?? '', ENT_QUOTES, 'UTF-8') ?>">
                        <input type="hidden" name="quality" value="6">
                        <input type="hidden" name="question_id" value="<?= (int) ($feedback['question_id'] ?? 0) ?>">
                        <button type="submit" class="btn btn-outline-info btn-lg rating-btn">Easy</button>
                    </form>
                </div>
            </div>
        </div>

    <?php elseif ($currentQuestion): ?>
        <!-- Show question form -->
        <div class="card shadow-sm mb-4">
            <div class="card-body">
                <div class="d-flex justify-content-between mb-3">
                    <span class="badge bg-secondary">Question <?= $currentIndex + 1 ?> of <?= $total ?></span>
                    <span class="badge bg-info"><?= (float) ($currentQuestion['points'] ?? 1.0) ?> pts</span>
                </div>

                <h5 class="mb-4"><?= nl2br(htmlspecialchars($currentQuestion['question_text'] ?? '', ENT_QUOTES, 'UTF-8')) ?></h5>

                <form method="POST" action="/review/<?= (int) ($topicId ?? 0) ?>/answer">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken ?? '', ENT_QUOTES, 'UTF-8') ?>">
                    <input type="hidden" name="question_id" value="<?= (int) ($currentQuestion['id'] ?? 0) ?>">

                    <?php $type = $currentQuestion['question_type'] ?? ''; ?>
                    <?php if ($type === 'fill_blank' || $type === 'short_answer'): ?>
                        <div class="mb-3">
                            <textarea class="form-control form-control-lg" name="answer_text" rows="3"
                                      placeholder="Type your answer..." required></textarea>
                        </div>
                    <?php else: ?>
                        <p class="text-muted">Question type not supported in review mode. Please take a quiz to practice this question type.</p>
                    <?php endif; ?>

                    <button type="submit" class="btn btn-primary btn-lg w-100">Submit Answer</button>
                </form>
            </div>
        </div>

    <?php else: ?>
        <div class="card shadow-sm">
            <div class="card-body text-center py-5">
                <p class="text-muted mb-2">Review complete for this topic!</p>
                <a href="/review" class="btn btn-primary">Back to Dashboard</a>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php
unset($_SESSION['review_index'], $_SESSION['review_feedback']);
?>

<link rel="stylesheet" href="/assets/css/review.css">
