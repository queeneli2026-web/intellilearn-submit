<div class="performance-dashboard">
    <!-- Section 1: Attempt History -->
    <div class="card shadow-sm mb-4">
        <div class="card-header bg-white d-flex justify-content-between align-items-center">
            <h5 class="mb-0">Attempt History</h5>
        </div>
        <div class="card-body p-0">
            <?php if (empty($attempts)): ?>
                <div class="text-center py-5">
                    <p class="text-muted mb-2">No quiz attempts yet</p>
                    <a href="/quiz/browse" class="btn btn-primary">Browse Quizzes</a>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-striped mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Quiz</th>
                                <th>Topic</th>
                                <th>Score</th>
                                <th>Result</th>
                                <th>Time</th>
                                <th>Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($attempts as $a): ?>
                                <tr>
                                    <td><?= htmlspecialchars($a['quiz_title'] ?? '', ENT_QUOTES, 'UTF-8') ?></td>
                                    <td><?= htmlspecialchars($a['topic_name'] ?? '-', ENT_QUOTES, 'UTF-8') ?></td>
                                    <td>
                                        <?= (float) ($a['score'] ?? 0) ?> / <?= (float) ($a['max_score'] ?? 0) ?>
                                        <small class="text-muted">(<?= (float) ($a['percentage'] ?? 0) ?>%)</small>
                                    </td>
                                    <td>
                                        <?php if ($a['passed'] ?? false): ?>
                                            <span class="badge bg-success">Pass</span>
                                        <?php else: ?>
                                            <span class="badge bg-danger">Fail</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php
                                        $sec = (int) ($a['time_taken_sec'] ?? 0);
                                        $m = intdiv($sec, 60);
                                        $s = $sec % 60;
                                        echo $m > 0 ? "{$m}m {$s}s" : "{$s}s";
                                        ?>
                                    </td>
                                    <td><?= htmlspecialchars($a['completed_at'] ?? '', ENT_QUOTES, 'UTF-8') ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php if ($totalPages > 1): ?>
                    <nav class="p-3">
                        <ul class="pagination pagination-sm mb-0 justify-content-center">
                            <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                                <li class="page-item <?= $i === $currentPage ? 'active' : '' ?>">
                                    <a class="page-link" href="/performance?page=<?= $i ?>"><?= $i ?></a>
                                </li>
                            <?php endfor; ?>
                        </ul>
                    </nav>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- Section 2: Topic Accuracy Bar Chart -->
    <div class="card shadow-sm mb-4">
        <div class="card-header bg-white">
            <h5 class="mb-0">Accuracy by Topic</h5>
        </div>
        <div class="card-body">
            <?php if (empty($topicAccuracy)): ?>
                <div class="text-center py-4">
                    <p class="text-muted mb-0">Complete a quiz to see your topic accuracy breakdown.</p>
                </div>
            <?php else: ?>
                <div class="chart-container">
                    <canvas id="topicAccuracyChart"
                            data-chart-config='<?= htmlspecialchars(json_encode([
                                'labels' => array_map(fn($t) => $t['topic_name'], $topicAccuracy),
                                'data' => array_map(fn($t) => (float) ($t['accuracy'] ?? 0), $topicAccuracy),
                            ]), ENT_QUOTES, 'UTF-8') ?>'></canvas>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Section 3: Accuracy Trend Line Chart -->
    <div class="card shadow-sm mb-4">
        <div class="card-header bg-white">
            <h5 class="mb-0">Accuracy Trend</h5>
        </div>
        <div class="card-body">
            <?php if (count($trend) < 2): ?>
                <div class="text-center py-4">
                    <p class="text-muted mb-0">Complete at least 2 quizzes to see your progress trend.</p>
                </div>
            <?php else: ?>
                <div class="chart-container">
                    <canvas id="accuracyTrendChart"
                            data-chart-config='<?= htmlspecialchars(json_encode([
                                'labels' => array_map(fn($t) => date('M j', strtotime($t['completed_at'])), $trend),
                                'data' => array_map(fn($t) => (float) ($t['percentage'] ?? 0), $trend),
                                'passThreshold' => 50,
                            ]), ENT_QUOTES, 'UTF-8') ?>'></canvas>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Section 4: Topic Mastery Cards -->
    <div class="card shadow-sm mb-4">
        <div class="card-header bg-white">
            <h5 class="mb-0">Topic Mastery</h5>
        </div>
        <div class="card-body">
            <?php if (empty($mastery)): ?>
                <div class="text-center py-4">
                    <p class="text-muted mb-0">Answer some questions to track your topic mastery.</p>
                </div>
            <?php else: ?>
                <div class="row g-3">
                    <?php foreach ($mastery as $m): ?>
                        <?php
                        $level = $m['mastery_level'] ?? 'novice';
                        $levelColors = [
                            'novice' => 'bg-secondary',
                            'apprentice' => 'bg-info',
                            'proficient' => 'bg-success',
                            'expert' => 'bg-warning text-dark',
                        ];
                        $badgeClass = $levelColors[$level] ?? 'bg-secondary';
                        $accuracy = (float) ($m['accuracy'] ?? 0);
                        ?>
                        <div class="col-md-4 col-sm-6">
                            <div class="card h-100 mastery-card border-start border-4 <?= str_replace('bg-', 'border-', explode(' ', $badgeClass)[0]) ?>">
                                <div class="card-body">
                                    <h6 class="card-title"><?= htmlspecialchars($m['topic_name'] ?? '', ENT_QUOTES, 'UTF-8') ?></h6>
                                    <span class="badge <?= $badgeClass ?> mb-2"><?= ucfirst($level) ?></span>
                                    <div class="progress mastery-progress mb-1">
                                        <div class="progress-bar <?= $badgeClass ?>"
                                             role="progressbar"
                                             style="width: <?= $accuracy ?>%"
                                             aria-valuenow="<?= $accuracy ?>"
                                             aria-valuemin="0"
                                             aria-valuemax="100"></div>
                                    </div>
                                    <small class="text-muted">
                                        <?= (int) ($m['correct_answers'] ?? 0) ?> / <?= (int) ($m['total_questions'] ?? 0) ?> correct
                                    </small>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<link rel="stylesheet" href="/assets/css/performance.css">
<script src="https://cdn.jsdelivr.net/npm/chart.js@4"></script>
<script src="/assets/js/performance-charts.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const topicCanvas = document.getElementById('topicAccuracyChart');
    if (topicCanvas) {
        try {
            const config = JSON.parse(topicCanvas.dataset.chartConfig);
            initTopicAccuracyChart(topicCanvas, config);
        } catch (e) {
            console.error('Failed to init topic accuracy chart:', e);
        }
    }

    const trendCanvas = document.getElementById('accuracyTrendChart');
    if (trendCanvas) {
        try {
            const config = JSON.parse(trendCanvas.dataset.chartConfig);
            initAccuracyTrendChart(trendCanvas, config);
        } catch (e) {
            console.error('Failed to init accuracy trend chart:', e);
        }
    }
});
</script>
