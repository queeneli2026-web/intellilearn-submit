<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h3">Results</h1>
</div>

<?php if (isset($flash) && $flash !== ''): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <?= htmlspecialchars($flash, ENT_QUOTES, 'UTF-8') ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>

<!-- Stats Cards -->
<div class="row g-3 mb-3">
    <div class="col-md-3">
        <div class="card bg-primary text-white">
            <div class="card-body">
                <h6 class="card-subtitle mb-1">Total Attempts</h6>
                <h2 class="card-title mb-0"><?= (int) ($stats['total_attempts'] ?? 0) ?></h2>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card bg-info text-white">
            <div class="card-body">
                <h6 class="card-subtitle mb-1">Unique Students</h6>
                <h2 class="card-title mb-0"><?= (int) ($stats['total_students'] ?? 0) ?></h2>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card bg-warning text-dark">
            <div class="card-body">
                <h6 class="card-subtitle mb-1">Average Score</h6>
                <h2 class="card-title mb-0"><?= htmlspecialchars(number_format((float) ($stats['avg_percentage'] ?? 0), 1), ENT_QUOTES, 'UTF-8') ?>%</h2>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card bg-success text-white">
            <div class="card-body">
                <h6 class="card-subtitle mb-1">Passed</h6>
                <h2 class="card-title mb-0"><?= (int) ($stats['passed_count'] ?? 0) ?></h2>
            </div>
        </div>
    </div>
</div>

<!-- Filter -->
<div class="card mb-3">
    <div class="card-body">
        <form method="GET" action="/admin/results" class="row g-2">
            <div class="col-md-4">
                <label for="quiz_id" class="form-label visually-hidden">Quiz</label>
                <select id="quiz_id" name="quiz_id" class="form-select">
                    <option value="">All Quizzes</option>
                    <?php foreach ($quizzes as $quiz): ?>
                        <option value="<?= (int) $quiz['id'] ?>"
                            <?= ($selectedQuizId !== null && (int) $selectedQuizId === (int) $quiz['id']) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($quiz['title'], ENT_QUOTES, 'UTF-8') ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-4 d-flex align-items-end">
                <button type="submit" class="btn btn-outline-primary me-2">Filter</button>
                <a href="/admin/results" class="btn btn-outline-secondary">Clear</a>
            </div>
        </form>
    </div>
</div>

<!-- Results Table -->
<div class="card">
    <div class="card-body p-0">
        <?php if (empty($attempts)): ?>
            <div class="empty-state">
                <p>No completed attempts yet.</p>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Student Name</th>
                            <th>Quiz Title</th>
                            <th>Score</th>
                            <th>Percentage</th>
                            <th>Pass/Fail</th>
                            <th>Date</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($attempts as $attempt): ?>
                            <tr onclick="window.location='/admin/results/detail/<?= (int) $attempt['id'] ?>'" style="cursor: pointer">
                                <td class="fw-medium">
                                    <?= htmlspecialchars($attempt['student_name'] ?? $attempt['username'] ?? '', ENT_QUOTES, 'UTF-8') ?>
                                </td>
                                <td>
                                    <?= htmlspecialchars($attempt['quiz_title'] ?? '', ENT_QUOTES, 'UTF-8') ?>
                                </td>
                                <td>
                                    <?= htmlspecialchars((string) (float) ($attempt['score'] ?? 0), ENT_QUOTES, 'UTF-8') ?>
                                    /
                                    <?= htmlspecialchars((string) (float) ($attempt['max_score'] ?? 0), ENT_QUOTES, 'UTF-8') ?>
                                </td>
                                <td>
                                    <?= htmlspecialchars(number_format((float) ($attempt['percentage'] ?? 0), 1), ENT_QUOTES, 'UTF-8') ?>%
                                </td>
                                <td>
                                    <?php if (!empty($attempt['passed'])): ?>
                                        <span class="badge bg-success">Pass</span>
                                    <?php else: ?>
                                        <span class="badge bg-danger">Fail</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if (!empty($attempt['completed_at'])): ?>
                                        <?= htmlspecialchars(date('M d, Y H:i', strtotime($attempt['completed_at'])), ENT_QUOTES, 'UTF-8') ?>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end">
                                    <a href="/admin/results/detail/<?= (int) $attempt['id'] ?>"
                                       class="btn btn-sm btn-outline-primary"
                                       onclick="event.stopPropagation()">View</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>
