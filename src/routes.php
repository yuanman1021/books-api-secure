<?php
declare(strict_types=1);

use App\Auth\JwtService;
use App\Controllers\AuthController;
use App\Controllers\BookController;
use App\Database;
use App\Middleware\AuthMiddleware;
use App\Middleware\RateLimit;
use App\Repositories\AuditLogRepository;
use App\Repositories\BookRepository;
use App\Repositories\UserRepository;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\App;

return function (App $app): void {
    $pdo = Database::get();
    $jwt = new JwtService();
    $auth = new AuthMiddleware($jwt);

    $auditRepo = new AuditLogRepository($pdo);

    $bookCtrl = new BookController(
        new BookRepository($pdo),
        $auditRepo
    );

    $authCtrl = new AuthController(
        new UserRepository($pdo),
        $jwt,
        $auditRepo
    );

    $loginMw = new RateLimit(
        (int)($_ENV['LOGIN_RATE_LIMIT'] ?? 5),
        (int)($_ENV['LOGIN_WINDOW_SECONDS'] ?? 60),
        'login'
    );

    $app->get('/', function (Request $request, Response $response) {
        $response->getBody()->write(json_encode([
            'name' => 'Books REST API',
            'version' => '4.0.0 (secure)',
            'security' => [
                'jwt_auth' => true,
                'rate_limit' => true,
                'security_headers' => true,
                'cors_allow_list' => true,
                'idor_protection' => true,
                'audit_log' => true,
            ]
        ], JSON_PRETTY_PRINT));

        return $response->withHeader('Content-Type', 'application/json; charset=utf-8');
    });

    $app->post('/auth/register', [$authCtrl, 'register']);
    $app->post('/auth/login', [$authCtrl, 'login'])->add($loginMw);
    $app->get('/auth/me', [$authCtrl, 'me'])->add($auth);

    $app->get('/api/books', [$bookCtrl, 'index']);
    $app->get('/api/books/{id}', [$bookCtrl, 'show']);

    $app->group('/api/books', function ($group) use ($bookCtrl) {
        $group->post('', [$bookCtrl, 'create']);
        $group->put('/{id}', [$bookCtrl, 'update']);
        $group->delete('/{id}', [$bookCtrl, 'delete']);
    })->add($auth);

    $app->options('/{routes:.+}', fn(Request $request, Response $response) => $response);
};