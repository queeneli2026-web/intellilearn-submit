<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle ?? 'Dashboard', ENT_QUOTES, 'UTF-8') ?> - IntelliLearn Admin</title>
    <!-- Bootstrap 5.3 CDN -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Admin custom styles -->
    <link href="/assets/css/admin.css" rel="stylesheet">
</head>
<body>
    <!-- Top navbar -->
    <nav class="navbar navbar-expand-md navbar-dark bg-dark fixed-top">
        <div class="container-fluid">
            <a class="navbar-brand" href="/admin">IntelliLearn Admin</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#sidebarMenu"
                    aria-controls="sidebarMenu" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="d-flex ms-auto">
                <a href="/admin/logout" class="btn btn-outline-light btn-sm">Logout</a>
            </div>
        </div>
    </nav>

    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar (collapsible on mobile) -->
            <nav id="sidebarMenu" class="col-md-2 d-md-block sidebar collapse">
                <div class="position-sticky pt-3">
                    <ul class="nav nav-pills flex-column">
                        <li class="nav-item">
                            <a class="nav-link <?= str_starts_with($_SERVER['REQUEST_URI'] ?? '', '/admin/quizzes') ? 'active' : '' ?>"
                               href="/admin/quizzes">
                                Quizzes
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?= str_starts_with($_SERVER['REQUEST_URI'] ?? '', '/admin/questions') ? 'active' : '' ?>"
                               href="/admin/questions">
                                Question Bank
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?= str_starts_with($_SERVER['REQUEST_URI'] ?? '', '/admin/topics') ? 'active' : '' ?>"
                               href="/admin/topics">
                                Topics
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?= str_starts_with($_SERVER['REQUEST_URI'] ?? '', '/admin/results') ? 'active' : '' ?>"
                               href="/admin/results">
                                Results
                            </a>
                        </li>
                    </ul>
                </div>
            </nav>

            <!-- Main content area -->
            <main class="col-md-10 ms-sm-auto px-md-4">
                <?php if (isset($content)): ?>
                    <?= $content ?>
                <?php else: ?>
                    <div class="empty-state">
                        <p>Select a section from the sidebar to get started.</p>
                    </div>
                <?php endif; ?>
            </main>
        </div>
    </div>

    <!-- Bootstrap JS bundle -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
