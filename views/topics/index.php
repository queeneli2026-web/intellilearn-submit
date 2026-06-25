<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h3">Topics</h1>
    <a href="/admin/topics/create" class="btn btn-primary">Create Topic</a>
</div>

<?php if (isset($flash) && $flash !== ''): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <?= htmlspecialchars($flash, ENT_QUOTES, 'UTF-8') ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>

<div class="card">
    <div class="card-body p-0">
        <?php if (empty($topics)): ?>
            <div class="empty-state">
                <p>No topics yet. Create your first topic!</p>
                <a href="/admin/topics/create" class="btn btn-primary btn-sm">Create Topic</a>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Name</th>
                            <th>Description</th>
                            <th>Quiz Count</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($topics as $topic): ?>
                            <tr>
                                <td class="fw-medium">
                                    <?= htmlspecialchars($topic['name'], ENT_QUOTES, 'UTF-8') ?>
                                </td>
                                <td>
                                    <?php if (!empty($topic['description'])): ?>
                                        <?= htmlspecialchars(mb_substr($topic['description'], 0, 100), ENT_QUOTES, 'UTF-8') ?>
                                        <?= mb_strlen($topic['description']) > 100 ? '&hellip;' : '' ?>
                                    <?php else: ?>
                                        <span class="text-muted fst-italic">No description</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="badge bg-secondary"><?= (int) ($topic['quiz_count'] ?? 0) ?></span>
                                </td>
                                <td class="text-end">
                                    <a href="/admin/topics/edit/<?= (int) $topic['id'] ?>"
                                       class="btn btn-sm btn-outline-primary">Edit</a>
                                    <form action="/admin/topics/delete/<?= (int) $topic['id'] ?>"
                                          method="POST"
                                          style="display: inline"
                                          onsubmit="return confirm('Delete this topic? This will also delete all quizzes and questions in this topic.')">
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
