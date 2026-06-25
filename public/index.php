<?php
declare(strict_types=1);

/**
 * IntelliLearn — Front Controller
 *
 * Entry point for all HTTP requests. Loads configuration,
 * registers routes, and dispatches to the matching controller.
 */

// Load database configuration (not autoloaded — required directly)
require_once __DIR__ . '/../config/database.php';

// Load autoloader
require_once __DIR__ . '/../src/autoload.php';

// Configure session cookie before starting
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'httponly' => true,
        'samesite' => 'Lax',
        'lifetime' => 86400,
    ]);
    session_start();
}

use App\Router;

// Instantiate router
$router = new Router();

// ─── Auth Routes (unauthenticated) ───
$router->addRoute('GET', '/admin/login', 'AuthController@loginForm');
$router->addRoute('POST', '/admin/login', 'AuthController@login');
$router->addRoute('GET', '/admin/logout', 'AuthController@logout');

// ─── Admin Dashboard ───
$router->addRoute('GET', '/admin', 'TopicController@index');

// ─── Admin Pages — Protected ───

// Topics
$router->addRoute('GET', '/admin/topics', 'TopicController@index');
$router->addRoute('GET', '/admin/topics/create', 'TopicController@createForm');
$router->addRoute('POST', '/admin/topics/store', 'TopicController@store');
$router->addRoute('GET', '/admin/topics/edit/([^/]+)', 'TopicController@editForm');
$router->addRoute('POST', '/admin/topics/update', 'TopicController@update');
$router->addRoute('POST', '/admin/topics/delete/([^/]+)', 'TopicController@delete');

// Questions
$router->addRoute('GET', '/admin/questions', 'QuestionController@index');
$router->addRoute('GET', '/admin/questions/create', 'QuestionController@createForm');
$router->addRoute('POST', '/admin/questions/store', 'QuestionController@store');
$router->addRoute('GET', '/admin/questions/edit/([^/]+)', 'QuestionController@editForm');
$router->addRoute('POST', '/admin/questions/update', 'QuestionController@update');
$router->addRoute('POST', '/admin/questions/delete/([^/]+)', 'QuestionController@delete');

// Quizzes
$router->addRoute('GET', '/admin/quizzes', 'QuizController@index');
$router->addRoute('GET', '/admin/quizzes/create', 'QuizController@createForm');
$router->addRoute('POST', '/admin/quizzes/store', 'QuizController@store');
$router->addRoute('GET', '/admin/quizzes/edit/([^/]+)', 'QuizController@editForm');
$router->addRoute('POST', '/admin/quizzes/update', 'QuizController@update');
$router->addRoute('POST', '/admin/quizzes/delete/([^/]+)', 'QuizController@delete');
$router->addRoute('GET', '/admin/quizzes/detail/([^/]+)', 'QuizController@detail');

// Results
$router->addRoute('GET', '/admin/results', 'ResultController@index');
$router->addRoute('GET', '/admin/results/detail/([^/]+)', 'ResultController@detail');

// ─── Student Auth Routes ───
$router->addRoute('GET', '/student/login', 'StudentAuthController@loginForm');
$router->addRoute('POST', '/student/login', 'StudentAuthController@login');
$router->addRoute('GET', '/student/logout', 'StudentAuthController@logout');

// ─── Student Quiz Routes ───
$router->addRoute('GET', '/quiz', 'QuizBrowseController@index');
$router->addRoute('GET', '/quiz/browse', 'QuizBrowseController@index');
$router->addRoute('GET', '/quiz/browse/([^/]+)', 'QuizBrowseController@index');
$router->addRoute('POST', '/quiz/attempt/start/([^/]+)', 'AttemptController@start');
$router->addRoute('GET', '/quiz/attempt/([^/]+)/take', 'AttemptController@take');
$router->addRoute('GET', '/quiz/attempt/([^/]+)/next', 'AttemptController@next');
$router->addRoute('POST', '/quiz/attempt/([^/]+)/answer', 'AttemptController@submitAnswer');
$router->addRoute('POST', '/quiz/attempt/([^/]+)/finish', 'AttemptController@finish');
$router->addRoute('POST', '/quiz/attempt/([^/]+)/cancel', 'AttemptController@cancel');
$router->addRoute('GET', '/quiz/attempt/([^/]+)/results', 'AttemptController@results');
$router->addRoute('GET', '/quiz/check-resume', 'AttemptController@checkResume');

// ─── Student Performance Routes ───
$router->addRoute('GET', '/performance', 'PerformanceController@dashboard');

// ─── Student Spaced Review Routes ───
$router->addRoute('GET', '/review', 'ReviewController@dashboard');
$router->addRoute('GET', '/review/([^/]+)', 'ReviewController@start');
$router->addRoute('POST', '/review/([^/]+)/answer', 'ReviewController@answer');
$router->addRoute('POST', '/review/([^/]+)/rate', 'ReviewController@rate');

// Dispatch the request
$router->dispatch($_SERVER['REQUEST_METHOD'], $_SERVER['REQUEST_URI']);
