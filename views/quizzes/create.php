<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h3">Create Quiz</h1>
    <a href="/admin/quizzes" class="btn btn-outline-secondary">Cancel</a>
</div>

<?php if (!empty($errors)): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <ul class="mb-0">
            <?php foreach ($errors as $error): ?>
                <li><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></li>
            <?php endforeach; ?>
        </ul>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>

<form action="/admin/quizzes/store" method="POST">
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
    <!-- Quiz Metadata Card -->
    <div class="card mb-3">
        <div class="card-header">
            <h5 class="card-title mb-0">Quiz Details</h5>
        </div>
        <div class="card-body">
            <div class="mb-3">
                <label for="title" class="form-label">Title <span class="text-danger">*</span></label>
                <input type="text"
                       id="title"
                       name="title"
                       class="form-control <?= isset($errors) && in_array('Quiz title is required.', $errors ?? []) ? 'is-invalid' : '' ?>"
                       value="<?= htmlspecialchars($oldInput['title'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                       required>
            </div>

            <div class="mb-3">
                <label for="description" class="form-label">Description</label>
                <textarea id="description"
                          name="description"
                          class="form-control"
                          rows="3"><?= htmlspecialchars($oldInput['description'] ?? '', ENT_QUOTES, 'UTF-8') ?></textarea>
            </div>

            <div class="row">
                <div class="col-md-6 mb-3">
                    <label for="topic_id" class="form-label">Topic <span class="text-danger">*</span></label>
                    <select id="topic_id"
                            name="topic_id"
                            class="form-select <?= isset($errors) && in_array('Please select a topic.', $errors ?? []) ? 'is-invalid' : '' ?>"
                            required>
                        <option value="">Select a topic...</option>
                        <?php foreach ($topics as $topic): ?>
                            <option value="<?= (int) $topic['id'] ?>"
                                <?= ((int) ($oldInput['topic_id'] ?? 0)) === (int) $topic['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($topic['name'], ENT_QUOTES, 'UTF-8') ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-md-3 mb-3">
                    <label for="time_limit_min" class="form-label">Time Limit (minutes)</label>
                    <input type="number"
                           id="time_limit_min"
                           name="time_limit_min"
                           class="form-control"
                           min="0"
                           value="<?= htmlspecialchars($oldInput['time_limit_min'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                           placeholder="No limit">
                    <div class="form-text">Leave blank for no time limit.</div>
                </div>

                <div class="col-md-3 mb-3">
                    <label for="pass_percentage" class="form-label">Passing Score (%)</label>
                    <input type="number"
                           id="pass_percentage"
                           name="pass_percentage"
                           class="form-control"
                           min="0"
                           max="100"
                           value="<?= htmlspecialchars($oldInput['pass_percentage'] ?? '50', ENT_QUOTES, 'UTF-8') ?>">
                    <div class="form-text">Percentage required to pass (0-100).</div>
                </div>
            </div>

            <div class="form-check">
                <input type="checkbox"
                       id="is_active"
                       name="is_active"
                       class="form-check-input"
                       value="1"
                       <?= !empty($oldInput['is_active']) ? 'checked' : '' ?>>
                <label for="is_active" class="form-check-label">Active (available for students)</label>
            </div>
        </div>
    </div>

    <!-- Question Selection Card -->
    <div class="card mb-3">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="card-title mb-0">Select Questions</h5>
            <span id="selectedCount" class="badge bg-primary">0 questions selected</span>
        </div>
        <div class="card-body">
            <!-- Filter bar -->
            <div class="row g-2 mb-3">
                <div class="col-md-6">
                    <label for="questionTopicFilter" class="form-label visually-hidden">Filter by Topic</label>
                    <select id="questionTopicFilter" class="form-select">
                        <option value="">All Topics</option>
                        <?php
                        $seenTopics = [];
                        foreach ($allQuestions as $question):
                            $tid = (int) $question['topic_id'];
                            if (!isset($seenTopics[$tid])):
                                $seenTopics[$tid] = htmlspecialchars($question['topic_name'] ?? '', ENT_QUOTES, 'UTF-8');
                        ?>
                            <option value="<?= $tid ?>"><?= $seenTopics[$tid] ?></option>
                        <?php
                            endif;
                        endforeach;
                        ?>
                    </select>
                </div>
                <div class="col-md-6 d-flex align-items-end">
                    <button type="button" id="addAllFromTopic" class="btn btn-outline-primary btn-sm">Add all from topic</button>
                </div>
            </div>

            <!-- Questions table -->
            <?php if (empty($allQuestions)): ?>
                <div class="empty-state">
                    <p>No questions available. Create questions in the Question Bank first.</p>
                    <a href="/admin/questions/create" class="btn btn-primary btn-sm">Create Question</a>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-sm table-hover mb-0" id="questionBankTable">
                        <thead class="table-light">
                            <tr>
                                <th style="width: 40px">Select</th>
                                <th>Question</th>
                                <th style="width: 120px">Type</th>
                                <th style="width: 150px">Topic</th>
                                <th style="width: 60px">Order</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($allQuestions as $index => $question): ?>
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
                                <tr class="question-row" data-topic-id="<?= (int) $question['topic_id'] ?>" data-question-id="<?= (int) $question['id'] ?>">
                                    <td>
                                        <input type="checkbox"
                                               name="question_ids[]"
                                               value="<?= (int) $question['id'] ?>"
                                               class="form-check-input question-checkbox"
                                               data-question-id="<?= (int) $question['id'] ?>"
                                               <?= in_array((int) $question['id'], (array) ($oldInput['question_ids'] ?? [])) ? 'checked' : '' ?>>
                                    </td>
                                    <td>
                                        <?= htmlspecialchars(mb_substr($question['question_text'], 0, 80), ENT_QUOTES, 'UTF-8') ?>
                                        <?= mb_strlen($question['question_text']) > 80 ? '&hellip;' : '' ?>
                                    </td>
                                    <td>
                                        <span class="badge <?= $typeInfo['class'] ?>">
                                            <?= htmlspecialchars($typeInfo['label'], ENT_QUOTES, 'UTF-8') ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?= htmlspecialchars($question['topic_name'] ?? '', ENT_QUOTES, 'UTF-8') ?>
                                    </td>
                                    <td>
                                        <div class="btn-group btn-group-sm" role="group">
                                            <button type="button" class="btn btn-outline-secondary move-up" title="Move up">&uarr;</button>
                                            <button type="button" class="btn btn-outline-secondary move-down" title="Move down">&darr;</button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="d-flex gap-2 mb-4">
        <button type="submit" class="btn btn-primary">Save Quiz</button>
        <a href="/admin/quizzes" class="btn btn-outline-secondary">Cancel</a>
    </div>
</form>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const checkboxes = document.querySelectorAll('.question-checkbox');
    const selectedCount = document.getElementById('selectedCount');
    const filterSelect = document.getElementById('questionTopicFilter');
    const addAllBtn = document.getElementById('addAllFromTopic');
    const rows = document.querySelectorAll('.question-row');

    // ─── Update selected count ───
    function updateSelectedCount() {
        const checked = document.querySelectorAll('.question-checkbox:checked');
        selectedCount.textContent = checked.length + ' questions selected';
    }

    checkboxes.forEach(function (cb) {
        cb.addEventListener('change', updateSelectedCount);
    });

    // ─── Filter by topic ───
    filterSelect.addEventListener('change', function () {
        const topicId = this.value;
        rows.forEach(function (row) {
            if (topicId === '' || row.dataset.topicId === topicId) {
                row.style.display = '';
            } else {
                row.style.display = 'none';
            }
        });
    });

    // ─── Add all from topic ───
    addAllBtn.addEventListener('click', function () {
        const topicId = filterSelect.value;
        rows.forEach(function (row) {
            if (topicId === '' || row.dataset.topicId === topicId) {
                const checkbox = row.querySelector('.question-checkbox');
                if (checkbox) {
                    checkbox.checked = true;
                }
            }
        });
        updateSelectedCount();
    });

    // ─── Up/Down arrow reordering ───
    document.querySelectorAll('.move-up').forEach(function (btn) {
        btn.addEventListener('click', function (e) {
            e.preventDefault();
            const row = this.closest('tr');
            const prev = row.previousElementSibling;
            if (prev) {
                row.parentNode.insertBefore(row, prev);
                updateVisualOrder();
            }
        });
    });

    document.querySelectorAll('.move-down').forEach(function (btn) {
        btn.addEventListener('click', function (e) {
            e.preventDefault();
            const row = this.closest('tr');
            const next = row.nextElementSibling;
            if (next) {
                row.parentNode.insertBefore(next, row);
                updateVisualOrder();
            }
        });
    });

    function updateVisualOrder() {
        // Visual only — server recalculates sort_order on save
        // This function is a placeholder for future drag-and-drop integration
    }

    // Initial count
    updateSelectedCount();
});
</script>
