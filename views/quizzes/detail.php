<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h3"><?= htmlspecialchars($quiz['title'] ?? '', ENT_QUOTES, 'UTF-8') ?></h1>
    <div>
        <a href="/admin/quizzes/edit/<?= (int) ($quiz['id'] ?? 0) ?>" class="btn btn-primary">Edit</a>
        <a href="/admin/quizzes" class="btn btn-outline-secondary">Back to Quizzes</a>
    </div>
</div>

<!-- Quiz Info Card -->
<div class="card mb-3">
    <div class="card-header">
        <h5 class="card-title mb-0">Quiz Information</h5>
    </div>
    <div class="card-body">
        <div class="row">
            <div class="col-md-6 mb-2">
                <strong>Topic:</strong>
                <?= htmlspecialchars($quiz['topic_name'] ?? '', ENT_QUOTES, 'UTF-8') ?>
            </div>
            <div class="col-md-3 mb-2">
                <strong>Time Limit:</strong>
                <?php if (isset($quiz['time_limit_min']) && $quiz['time_limit_min'] !== null): ?>
                    <?= (int) $quiz['time_limit_min'] ?> min
                <?php else: ?>
                    <span class="text-muted fst-italic">No limit</span>
                <?php endif; ?>
            </div>
            <div class="col-md-3 mb-2">
                <strong>Passing Score:</strong>
                <?= (int) ($quiz['pass_percentage'] ?? 50) ?>%
            </div>
        </div>

        <?php if (!empty($quiz['description'])): ?>
            <div class="mt-2">
                <strong>Description:</strong>
                <p class="mb-0"><?= nl2br(htmlspecialchars($quiz['description'], ENT_QUOTES, 'UTF-8')) ?></p>
            </div>
        <?php endif; ?>

        <div class="mt-2">
            <strong>Status:</strong>
            <?php if (!empty($quiz['is_active'])): ?>
                <span class="badge bg-success">Active</span>
            <?php else: ?>
                <span class="badge bg-secondary">Inactive</span>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Assigned Questions Card -->
<div class="card">
    <div class="card-header">
        <h5 class="card-title mb-0">Assigned Questions (<?= count($quiz['questions'] ?? []) ?>)</h5>
    </div>
    <div class="card-body p-0">
        <?php if (empty($quiz['questions'])): ?>
            <div class="empty-state">
                <p>No questions assigned to this quiz yet.</p>
                <a href="/admin/quizzes/edit/<?= (int) ($quiz['id'] ?? 0) ?>" class="btn btn-primary btn-sm">Edit Quiz</a>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th style="width: 50px">#</th>
                            <th>Question</th>
                            <th style="width: 120px">Type</th>
                            <th style="width: 80px">Points</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($quiz['questions'] as $index => $question): ?>
                            <?php
                                $typeLabels = [
                                    'mcq_single'   => ['label' => 'MCQ Single', 'class' => 'bg-primary'],
                                    'mcq_multi'    => ['label' => 'MCQ Multi', 'class' => 'bg-info'],
                                    'true_false'   => ['label' => 'T/F', 'class' => 'bg-warning text-dark'],
                                    'fill_blank'   => ['label' => 'Fill Blank', 'class' => 'bg-secondary'],
                                    'short_answer' => ['label' => 'Short Answer', 'class' => 'bg-dark'],
                                ];
                                $typeInfo = $typeLabels[$question['question_type']] ?? ['label' => $question['question_type'], 'class' => 'bg-secondary'];
                            ?>
                            <tr>
                                <td class="fw-medium"><?= (int) ($question['sort_order'] ?? $index + 1) ?></td>
                                <td>
                                    <?= htmlspecialchars(mb_substr($question['question_text'], 0, 120), ENT_QUOTES, 'UTF-8') ?>
                                    <?= mb_strlen($question['question_text']) > 120 ? '&hellip;' : '' ?>
                                </td>
                                <td>
                                    <span class="badge <?= $typeInfo['class'] ?>">
                                        <?= htmlspecialchars($typeInfo['label'], ENT_QUOTES, 'UTF-8') ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if (isset($question['points_override']) && $question['points_override'] !== null): ?>
                                        <?= htmlspecialchars((string) (float) $question['points_override'], ENT_QUOTES, 'UTF-8') ?>
                                        <span class="text-muted">(override)</span>
                                    <?php else: ?>
                                        <?= htmlspecialchars((string) (float) ($question['points'] ?? 1.0), ENT_QUOTES, 'UTF-8') ?>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>
