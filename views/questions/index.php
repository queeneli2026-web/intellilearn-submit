<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h3">Question Bank</h1>
    <a href="/admin/questions/create" class="btn btn-primary">Create Question</a>
</div>

<?php if (isset($flash) && $flash !== ''): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <?= htmlspecialchars($flash, ENT_QUOTES, 'UTF-8') ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>

<div class="card">
    <div class="card-body">
        <!-- Filter bar -->
        <form method="GET" action="/admin/questions" class="row g-2 mb-3">
            <div class="col-md-4">
                <label for="type" class="form-label visually-hidden">Type</label>
                <select id="type" name="type" class="form-select">
                    <option value="">All Types</option>
                    <option value="mcq_single" <?= ($selectedType ?? '') === 'mcq_single' ? 'selected' : '' ?>>MCQ Single</option>
                    <option value="mcq_multi" <?= ($selectedType ?? '') === 'mcq_multi' ? 'selected' : '' ?>>MCQ Multi</option>
                    <option value="true_false" <?= ($selectedType ?? '') === 'true_false' ? 'selected' : '' ?>>True/False</option>
                    <option value="fill_blank" <?= ($selectedType ?? '') === 'fill_blank' ? 'selected' : '' ?>>Fill Blank</option>
                    <option value="short_answer" <?= ($selectedType ?? '') === 'short_answer' ? 'selected' : '' ?>>Short Answer</option>
                </select>
            </div>
            <div class="col-md-4">
                <label for="topic_id" class="form-label visually-hidden">Topic</label>
                <select id="topic_id" name="topic_id" class="form-select">
                    <option value="">All Topics</option>
                    <?php foreach ($topics as $topic): ?>
                        <option value="<?= (int) $topic['id'] ?>"
                            <?= ($selectedTopicId ?? 0) === (int) $topic['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($topic['name'], ENT_QUOTES, 'UTF-8') ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-4 d-flex align-items-end">
                <button type="submit" class="btn btn-outline-primary me-2">Filter</button>
                <a href="/admin/questions" class="btn btn-outline-secondary">Clear</a>
            </div>
        </form>

        <!-- Questions table -->
        <?php if (empty($questions)): ?>
            <div class="empty-state">
                <p>No questions yet. Create your first question!</p>
                <a href="/admin/questions/create" class="btn btn-primary btn-sm">Create Question</a>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Question</th>
                            <th>Type</th>
                            <th>Topic</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($questions as $question): ?>
                            <tr>
                                <td>
                                    <?= htmlspecialchars(mb_substr($question['question_text'], 0, 80), ENT_QUOTES, 'UTF-8') ?>
                                    <?= mb_strlen($question['question_text']) > 80 ? '&hellip;' : '' ?>
                                </td>
                                <td>
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
                                    <span class="badge <?= $typeInfo['class'] ?>">
                                        <?= htmlspecialchars($typeInfo['label'], ENT_QUOTES, 'UTF-8') ?>
                                    </span>
                                </td>
                                <td>
                                    <?= htmlspecialchars($question['topic_name'] ?? '', ENT_QUOTES, 'UTF-8') ?>
                                </td>
                                <td class="text-end">
                                    <a href="/admin/questions/edit/<?= (int) $question['id'] ?>"
                                       class="btn btn-sm btn-outline-primary">Edit</a>
                                    <form action="/admin/questions/delete/<?= (int) $question['id'] ?>"
                                          method="POST"
                                          style="display: inline"
                                          onsubmit="return confirm('Delete this question? This action cannot be undone.')">
                                        <button type="submit" class="btn btn-sm btn-outline-danger">Delete</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>
