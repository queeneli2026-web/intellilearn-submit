<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?= htmlspecialchars($csrfToken ?? '', ENT_QUOTES, 'UTF-8') ?>">
    <title><?= htmlspecialchars($pageTitle ?? 'Quiz', ENT_QUOTES, 'UTF-8') ?> - IntelliLearn</title>
    <!-- Bootstrap 5.3 CDN -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Quiz custom styles -->
    <link href="/assets/css/quiz.css" rel="stylesheet">
</head>
<body>
    <!-- Minimal top bar (no sidebar per Phase 2 context decisions) -->
    <nav class="navbar navbar-expand navbar-dark bg-primary fixed-top">
        <div class="container">
            <a class="navbar-brand fw-bold" href="/quiz">IntelliLearn</a>
            <div class="navbar-nav me-auto">
                <a class="nav-link" href="/quiz/browse">Quizzes</a>
                <a class="nav-link" href="/performance">Performance</a>
                <a class="nav-link" href="/review">Review</a>
            </div>
            <div class="d-flex align-items-center gap-2">
                <?php if (isset($_SESSION['full_name'])): ?>
                    <span class="text-white small d-none d-sm-inline">
                        &#127919; <?= (int) ($_SESSION['xp'] ?? 0) ?> XP
                    </span>
                    <?php if (($_SESSION['streak_count'] ?? 0) > 0): ?>
                        <span class="text-white small d-none d-sm-inline">
                            &#128293; <?= (int) ($_SESSION['streak_count'] ?? 0) ?>
                        </span>
                    <?php endif; ?>
                    <span class="text-white small d-none d-sm-inline ms-1"><?= htmlspecialchars($_SESSION['full_name'], ENT_QUOTES, 'UTF-8') ?></span>
                <?php endif; ?>
                <a href="/student/logout" class="btn btn-outline-light btn-sm">Logout</a>
            </div>
        </div>
    </nav>

    <!-- Main content area (with top padding for fixed navbar) -->
    <main class="container py-4" style="padding-top: 76px;">
        <?php if (isset($flash) && $flash): ?>
            <div class="alert alert-info alert-dismissible fade show" role="alert">
                <?= htmlspecialchars((string) $flash, ENT_QUOTES, 'UTF-8') ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        <?php if (isset($content)): ?>
            <?= $content ?>
        <?php endif; ?>
    </main>

    <!-- Bootstrap JS bundle -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
