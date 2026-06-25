<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h3">Quizzes</h1>
    <a href="/admin/quizzes/create" class="btn btn-primary">Create Quiz</a>
</div>

<?php if (isset($flash) && $flash !== ''): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <?= htmlspecialchars($flash, ENT_QUOTES, 'UTF-8') ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>

<div class="card">
    <div class="card-body p-0">
        <?php if (empty($quizzes)): ?>
            <div class="empty-state">
                <p>No quizzes yet. Create your first quiz!</p>
                <a href="/admin/quizzes/create" class="btn btn-primary btn-sm">Create Quiz</a>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Title</th>
                            <th>Topic</th>
                            <th>Questions</th>
                            <th>Time Limit</th>
                            <th>Passing Score</th>
                            <th>Status</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($quizzes as $quiz): ?>
                            <tr onclick="window.location='/admin/quizzes/detail/<?= (int) $quiz['id'] ?>'" style="cursor: pointer">
                                <td class="fw-medium">
                                    <?= htmlspecialchars($quiz['title'], ENT_QUOTES, 'UTF-8') ?>
                                </td>
                                <td>
                                    <?= htmlspecialchars($quiz['topic_name'] ?? '', ENT_QUOTES, 'UTF-8') ?>
                                </td>
                                <td>
                                    <span class="badge bg-secondary"><?= (int) ($quiz['question_count'] ?? 0) ?></span>
                                </td>
                                <td>
                                    <?php if (isset($quiz['time_limit_min']) && $quiz['time_limit_min'] !== null): ?>
                                        <?= (int) $quiz['time_limit_min'] ?> min
                                    <?php else: ?>
                                        <span class="text-muted fst-italic">No limit</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?= (int) ($quiz['pass_percentage'] ?? 50) ?>%
                                </td>
                                <td>
                                    <?php if (!empty($quiz['is_active'])): ?>
                                        <span class="badge bg-success">Active</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary">Inactive</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end">
                                    <a href="/admin/quizzes/edit/<?= (int) $quiz['id'] ?>"
                                       class="btn btn-sm btn-outline-primary"
                                       onclick="event.stopPropagation()">Edit</a>
                                    <form action="/admin/quizzes/delete/<?= (int) $quiz['id'] ?>"
                                          method="POST"
                                          style="display: inline"
                                          onsubmit="return confirm('Delete this quiz? This will also delete all attempts and responses for this quiz.')">
                                        <button type="submit" class="btn btn-sm btn-outline-danger"
                                                onclick="event.stopPropagation()">Delete</button>
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
