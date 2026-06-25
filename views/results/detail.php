<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h3">Attempt Detail</h1>
    <a href="/admin/results" class="btn btn-outline-secondary">Back to Results</a>
</div>

<!-- Summary Card -->
<div class="card mb-3">
    <div class="card-header">
        <h5 class="card-title mb-0">Attempt Summary</h5>
    </div>
    <div class="card-body">
        <div class="row">
            <div class="col-md-4 mb-2">
                <strong>Student:</strong>
                <?= htmlspecialchars($attempt['student_name'] ?? $attempt['username'] ?? '', ENT_QUOTES, 'UTF-8') ?>
            </div>
            <div class="col-md-4 mb-2">
                <strong>Quiz:</strong>
                <?= htmlspecialchars($attempt['quiz_title'] ?? '', ENT_QUOTES, 'UTF-8') ?>
            </div>
            <div class="col-md-4 mb-2">
                <strong>Score:</strong>
                <?= htmlspecialchars((string) (float) ($attempt['score'] ?? 0), ENT_QUOTES, 'UTF-8') ?>
                /
                <?= htmlspecialchars((string) (float) ($attempt['max_score'] ?? 0), ENT_QUOTES, 'UTF-8') ?>
            </div>
        </div>
        <div class="row">
            <div class="col-md-4 mb-2">
                <strong>Percentage:</strong>
                <?= htmlspecialchars(number_format((float) ($attempt['percentage'] ?? 0), 1), ENT_QUOTES, 'UTF-8') ?>%
            </div>
            <div class="col-md-4 mb-2">
                <strong>Result:</strong>
                <?php if (!empty($attempt['passed'])): ?>
                    <span class="badge bg-success">Pass</span>
                <?php else: ?>
                    <span class="badge bg-danger">Fail</span>
                <?php endif; ?>
            </div>
            <div class="col-md-4 mb-2">
                <strong>Time Taken:</strong>
                <?php if (isset($attempt['time_taken_sec']) && $attempt['time_taken_sec'] !== null): ?>
                    <?php
                        $minutes = floor((int) $attempt['time_taken_sec'] / 60);
                        $seconds = (int) $attempt['time_taken_sec'] % 60;
                    ?>
                    <?= $minutes > 0 ? $minutes . 'm ' : '' ?><?= $seconds ?>s
                <?php else: ?>
                    <span class="text-muted fst-italic">N/A</span>
                <?php endif; ?>
            </div>
        </div>
        <div class="row">
            <div class="col-md-4">
                <strong>Completed:</strong>
                <?php if (!empty($attempt['completed_at'])): ?>
                    <?= htmlspecialchars(date('M d, Y H:i', strtotime($attempt['completed_at'])), ENT_QUOTES, 'UTF-8') ?>
                <?php else: ?>
                    <span class="text-muted fst-italic">In progress</span>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Per-question Breakdown -->
<div class="card">
    <div class="card-header">
        <h5 class="card-title mb-0">Question Breakdown</h5>
    </div>
    <div class="card-body p-0">
        <?php if (empty($attempt['responses'])): ?>
            <div class="empty-state">
                <p>No response data available.</p>
            </div>
        <?php else: ?>
            <div class="accordion" id="questionAccordion">
                <?php foreach ($attempt['responses'] as $index => $response): ?>
                    <?php
                        $qNum = $index + 1;
                        $isCorrect = (int) ($response['is_correct'] ?? 0);
                        $typeLabels = [
                            'mcq_single'   => 'MCQ Single',
                            'mcq_multi'    => 'MCQ Multi',
                            'true_false'   => 'T/F',
                            'fill_blank'   => 'Fill Blank',
                            'short_answer' => 'Short Answer',
                        ];
                        $typeLabel = $typeLabels[$response['question_type']] ?? $response['question_type'];
                        $typeBadgeClass = [
                            'mcq_single'   => 'bg-primary',
                            'mcq_multi'    => 'bg-info',
                            'true_false'   => 'bg-warning text-dark',
                            'fill_blank'   => 'bg-secondary',
                            'short_answer' => 'bg-dark',
                        ];
                        $badgeClass = $typeBadgeClass[$response['question_type']] ?? 'bg-secondary';
                        $correctAnswerData = $response['correct_answer_data'] ?? [];
                        $correctAnswer = $correctAnswerData['correct_answer'] ?? 'N/A';
                    ?>
                    <div class="accordion-item">
                        <h2 class="accordion-header" id="heading<?= $qNum ?>">
                            <button class="accordion-button <?= $index > 0 ? 'collapsed' : '' ?>"
                                    type="button"
                                    data-bs-toggle="collapse"
                                    data-bs-target="#collapse<?= $qNum ?>"
                                    aria-expanded="<?= $index === 0 ? 'true' : 'false' ?>"
                                    aria-controls="collapse<?= $qNum ?>">
                                <span class="d-flex align-items-center gap-2">
                                    <span class="fw-bold me-2">Q<?= $qNum ?>.</span>
                                    <?php if ($isCorrect): ?>
                                        <span class="badge bg-success" title="Correct">&check;</span>
                                    <?php else: ?>
                                        <span class="badge bg-danger" title="Incorrect">&times;</span>
                                    <?php endif; ?>
                                    <span class="badge <?= $badgeClass ?>"><?= htmlspecialchars($typeLabel, ENT_QUOTES, 'UTF-8') ?></span>
                                    <span class="text-muted small ms-2">
                                        <?= htmlspecialchars((string) (float) ($response['points_earned'] ?? 0), ENT_QUOTES, 'UTF-8') ?>
                                        /
                                        <?= htmlspecialchars((string) (float) ($response['max_points'] ?? 0), ENT_QUOTES, 'UTF-8') ?>
                                        pts
                                    </span>
                                </span>
                            </button>
                        </h2>
                        <div id="collapse<?= $qNum ?>"
                             class="accordion-collapse collapse <?= $index === 0 ? 'show' : '' ?>"
                             aria-labelledby="heading<?= $qNum ?>"
                             data-bs-parent="#questionAccordion">
                            <div class="accordion-body">
                                <div class="mb-3">
                                    <strong>Question:</strong>
                                    <p class="mb-0"><?= nl2br(htmlspecialchars($response['question_text'] ?? '', ENT_QUOTES, 'UTF-8')) ?></p>
                                </div>

                                <div class="row mb-2">
                                    <div class="col-md-6">
                                        <strong class="<?= $isCorrect ? '' : 'text-danger' ?>">Student's Answer:</strong>
                                        <p class="mb-0">
                                            <?php if ($response['question_type'] === 'fill_blank' || $response['question_type'] === 'short_answer'): ?>
                                                <?php if (!empty($response['answer_text'])): ?>
                                                    <?= htmlspecialchars($response['answer_text'], ENT_QUOTES, 'UTF-8') ?>
                                                <?php else: ?>
                                                    <span class="text-muted fst-italic">No answer given</span>
                                                <?php endif; ?>
                                            <?php else: ?>
                                                <?php
                                                    // Look up the selected option text based on selected_option_id
                                                    $selectedText = 'N/A';
                                                    if (!empty($response['selected_option_id']) && !empty($response['correct_answer_data'])) {
                                                        // Try to find the student's selected option text
                                                        // For now just show the option ID
                                                        $selectedText = 'Option #' . (int) $response['selected_option_id'];
                                                    }
                                                ?>
                                                <?php if (!empty($response['selected_option_id'])): ?>
                                                    <?= htmlspecialchars($selectedText, ENT_QUOTES, 'UTF-8') ?>
                                                <?php else: ?>
                                                    <span class="text-muted fst-italic">No answer given</span>
                                                <?php endif; ?>
                                            <?php endif; ?>
                                        </p>
                                    </div>
                                    <div class="col-md-6">
                                        <strong class="text-success">Correct Answer:</strong>
                                        <p class="mb-0"><?= htmlspecialchars($correctAnswer, ENT_QUOTES, 'UTF-8') ?></p>
                                    </div>
                                </div>

                                <?php if (!empty($response['explanation'])): ?>
                                    <div class="mt-2 p-3 bg-light rounded">
                                        <strong>Explanation:</strong>
                                        <p class="mb-0"><?= nl2br(htmlspecialchars($response['explanation'], ENT_QUOTES, 'UTF-8')) ?></p>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>
