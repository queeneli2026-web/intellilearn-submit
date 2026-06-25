<div class="review-dashboard">
    <!-- Stats Bar -->
    <div class="d-flex gap-3 mb-4">
        <div class="badge bg-primary fs-6 px-3 py-2">
            &#127919; <?= (int) ($xp ?? 0) ?> XP
        </div>
        <div class="badge <?= ($streak ?? 0) > 0 ? 'bg-warning text-dark' : 'bg-secondary' ?> fs-6 px-3 py-2">
            &#128293; <?= (int) ($streak ?? 0) ?> day streak
        </div>
    </div>

    <!-- Due Reviews -->
    <div class="card shadow-sm mb-4">
        <div class="card-header bg-white">
            <h5 class="mb-0">
                Due for Review
                <?php if (count($dueTopics) > 0): ?>
                    <span class="badge bg-danger ms-2"><?= count($dueTopics) ?></span>
                <?php endif; ?>
            </h5>
        </div>
        <div class="card-body">
            <?php if (empty($dueTopics)): ?>
                <div class="text-center py-4">
                    <p class="text-muted mb-2">No reviews due right now.</p>
                    <a href="/quiz/browse" class="btn btn-primary">Take a Quiz</a>
                </div>
            <?php else: ?>
                <div class="list-group">
                    <?php foreach ($dueTopics as $t): ?>
                        <div class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="mb-1">&#129504; <?= htmlspecialchars($t['topic_name'] ?? '', ENT_QUOTES, 'UTF-8') ?></h6>
                                <small class="text-muted">
                                    <?php $qCount = (int) ($t['question_count'] ?? 0); ?>
                                    <?= $qCount ?> question<?= $qCount !== 1 ? 's' : '' ?> to review
                                    &middot; <?= $t['next_review_at'] ? 'Due ' . date('M j', strtotime($t['next_review_at'])) : 'New topic' ?>
                                </small>
                            </div>
                            <a href="/review/<?= (int) ($t['topic_id'] ?? 0) ?>" class="btn btn-primary btn-sm">Review</a>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Upcoming Reviews -->
    <?php if (!empty($upcomingReviews)): ?>
    <div class="card shadow-sm mb-4">
        <div class="card-header bg-white">
            <h5 class="mb-0">Upcoming Reviews</h5>
        </div>
        <div class="card-body p-0">
            <div class="list-group list-group-flush">
                <?php foreach ($upcomingReviews as $t): ?>
                    <div class="list-group-item d-flex justify-content-between align-items-center">
                        <div>
                            <span class="fw-medium"><?= htmlspecialchars($t['topic_name'] ?? '', ENT_QUOTES, 'UTF-8') ?></span>
                            <small class="text-muted ms-2">Due <?= date('M j', strtotime($t['next_review_at'])) ?></small>
                        </div>
                        <span class="badge bg-secondary"><?= $t['interval_days'] ?> day interval</span>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Badges -->
    <div class="card shadow-sm mb-4">
        <div class="card-header bg-white">
            <h5 class="mb-0">&#127942; Badges</h5>
        </div>
        <div class="card-body">
            <div class="badge-grid">
                <?php
                $earnedKeys = array_map(fn($b) => $b['badge_key'], $userBadges ?? []);
                $badgeIcons = [
                    'trophy-fill' => '&#127942;',
                    'star-fill' => '&#11088;',
                    'fire' => '&#128293;',
                    'book-fill' => '&#128214;',
                    'gem' => '&#128142;',
                ];
                ?>
                <?php foreach (($allBadges ?? []) as $badge): ?>
                    <?php $isEarned = in_array($badge['badge_key'], $earnedKeys, true); ?>
                    <div class="badge-card <?= $isEarned ? 'earned' : 'locked' ?>">
                        <div class="badge-icon">
                            <?= $badgeIcons[$badge['icon']] ?? '&#127775;' ?>
                        </div>
                        <div class="badge-name"><?= htmlspecialchars($badge['name'] ?? '', ENT_QUOTES, 'UTF-8') ?></div>
                        <div class="badge-desc"><?= htmlspecialchars($badge['description'] ?? '', ENT_QUOTES, 'UTF-8') ?></div>
                        <?php if ($isEarned): ?>
                            <div class="badge-date">
                                <?php
                                $ub = array_filter($userBadges ?? [], fn($b) => $b['badge_key'] === $badge['badge_key']);
                                $first = reset($ub);
                                echo $first ? 'Earned ' . date('M j, Y', strtotime($first['awarded_at'])) : '';
                                ?>
                            </div>
                        <?php else: ?>
                            <div class="badge-locked-text">Locked</div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>

<link rel="stylesheet" href="/assets/css/review.css">
