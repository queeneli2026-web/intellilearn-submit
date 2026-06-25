<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h3">Create Topic</h1>
    <a href="/admin/topics" class="btn btn-outline-secondary">Cancel</a>
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

<div class="card">
    <div class="card-body">
        <form action="/admin/topics/store" method="POST">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
            <div class="mb-3">
                <label for="name" class="form-label">Name <span class="text-danger">*</span></label>
                <input type="text"
                       id="name"
                       name="name"
                       class="form-control <?= isset($errors) && in_array('Topic name is required.', $errors) ? 'is-invalid' : '' ?>"
                       value="<?= htmlspecialchars($oldInput['name'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                       required>
                <div class="form-text">A short, descriptive name for this topic category.</div>
            </div>

            <div class="mb-3">
                <label for="description" class="form-label">Description</label>
                <textarea id="description"
                          name="description"
                          class="form-control"
                          rows="3"><?= htmlspecialchars($oldInput['description'] ?? '', ENT_QUOTES, 'UTF-8') ?></textarea>
                <div class="form-text">Optional description of what this topic covers.</div>
            </div>

            <div class="d-flex gap-2">
                <button type="submit" class="btn btn-primary">Save Topic</button>
                <a href="/admin/topics" class="btn btn-outline-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>
