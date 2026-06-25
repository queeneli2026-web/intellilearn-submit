<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Repositories\TopicRepository;

class TopicController extends AdminController
{
    private TopicRepository $repo;

    public function __construct()
    {
        $this->repo = new TopicRepository();
    }

    public function indexAction(): void
    {
        $this->requireAuth();
        $topics = $this->repo->getAll();
        $this->render('views/topics/index.php', ['topics' => $topics], 'Topics');
    }

    public function createFormAction(): void
    {
        $this->requireAuth();
        $this->generateCsrfToken();
        $this->render('views/topics/create.php', [], 'Create Topic');
    }

    public function storeAction(): void
    {
        $this->requireAuth();
        $this->requireCsrf();

        $name = trim($_POST['name'] ?? '');
        $description = trim($_POST['description'] ?? '');

        $errors = [];
        if ($name === '') {
            $errors[] = 'Topic name is required.';
        }

        if (!empty($errors)) {
            $this->render('views/topics/create.php', [
                'errors'   => $errors,
                'oldInput' => ['name' => $name, 'description' => $description],
            ], 'Create Topic');
            return;
        }

        $this->repo->create($name, $description !== '' ? $description : null);
        $_SESSION['flash'] = 'Topic created successfully.';
        header('Location: /admin/topics');
        exit;
    }

    public function editFormAction(int $id): void
    {
        $this->requireAuth();
        $topic = $this->repo->getById($id);

        if ($topic === null) {
            http_response_code(404);
            echo '404 Not Found';
            return;
        }

        $this->generateCsrfToken();
        $this->render('views/topics/edit.php', ['topic' => $topic], 'Edit Topic');
    }

    public function updateAction(): void
    {
        $this->requireAuth();
        $this->requireCsrf();

        $id = (int) ($_POST['id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        $description = trim($_POST['description'] ?? '');

        $errors = [];
        if ($id <= 0) {
            $errors[] = 'Invalid topic ID.';
        }
        if ($name === '') {
            $errors[] = 'Topic name is required.';
        }

        if (!empty($errors)) {
            $topic = $this->repo->getById($id) ?: [];
            $this->render('views/topics/edit.php', [
                'topic'    => $topic,
                'errors'   => $errors,
                'oldInput' => ['name' => $name, 'description' => $description],
            ], 'Edit Topic');
            return;
        }

        $this->repo->update($id, $name, $description !== '' ? $description : null);
        $_SESSION['flash'] = 'Topic updated successfully.';
        header('Location: /admin/topics');
        exit;
    }

    public function deleteAction(int $id): void
    {
        $this->requireAuth();
        $this->requireCsrf();
        $this->repo->delete($id);
        $_SESSION['flash'] = 'Topic deleted successfully.';
        header('Location: /admin/topics');
        exit;
    }
}
