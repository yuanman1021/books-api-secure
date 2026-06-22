<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Auth\JwtService;
use App\Repositories\AuditLogRepository;
use App\Repositories\UserRepository;
use App\Validation\Validator;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

final class AuthController
{
    public function __construct(
        private UserRepository $users,
        private JwtService $jwt,
        private AuditLogRepository $audit
    ) {
    }

    public function register(Request $request, Response $response): Response
    {
        $body = (array)$request->getParsedBody();
        $ip = $this->ip($request);

        $errors = (new Validator())
            ->required('name', 'email', 'password')
            ->field('name', Validator::nonEmptyString(150), 'name must be 1-150 chars')
            ->field('email', Validator::email(), 'invalid email')
            ->field('password', fn($value): bool => is_string($value) && mb_strlen($value) >= 6, 'password must be at least 6 chars')
            ->validate($body);

        if ($errors) {
            $this->audit->record(null, 'register.fail', 'users', $ip, json_encode($errors));
            return $this->json($response, ['errors' => $errors], 400);
        }

        if ($this->users->emailExists((string)$body['email'])) {
            $this->audit->record(null, 'register.fail', 'users', $ip, 'Email already registered');
            return $this->json($response, ['error' => 'Email already registered'], 409);
        }

        $id = $this->users->create(
            (string)$body['name'],
            (string)$body['email'],
            password_hash((string)$body['password'], PASSWORD_DEFAULT)
        );

        $this->audit->record($id, 'register', 'users/' . $id, $ip, 'New user registered');

        return $this->json($response, [
            'message' => 'Registered',
            'user' => $this->users->findById($id),
        ], 201);
    }

    public function login(Request $request, Response $response): Response
    {
        $body = (array)$request->getParsedBody();
        $email = (string)($body['email'] ?? '');
        $ip = $this->ip($request);

        $user = $this->users->findByEmail($email);

        if (!$user || !password_verify((string)($body['password'] ?? ''), (string)$user['password_hash'])) {
            $this->audit->record($user['id'] ?? null, 'login.fail', 'auth/login', $ip, 'Invalid credentials for ' . $email);
            return $this->json($response, ['error' => 'Invalid credentials'], 401);
        }

        $token = $this->jwt->issue((int)$user['id'], [
            'role' => $user['role'],
            'email' => $user['email'],
        ]);

        $this->audit->record((int)$user['id'], 'login.success', 'auth/login', $ip, 'Login successful');

        return $this->json($response, [
            'token_type' => 'Bearer',
            'expires_in' => $this->jwt->ttl(),
            'access_token' => $token,
            'user' => $this->users->findById((int)$user['id']),
        ]);
    }

    public function me(Request $request, Response $response): Response
    {
        $auth = (array)$request->getAttribute('auth', []);
        $user = $this->users->findById((int)($auth['sub'] ?? 0));

        return $user
            ? $this->json($response, $user)
            : $this->json($response, ['error' => 'Not found'], 404);
    }

    private function ip(Request $request): string
    {
        return (string)($request->getServerParams()['REMOTE_ADDR'] ?? 'unknown');
    }

    private function json(Response $response, mixed $data, int $status = 200): Response
    {
        $response->getBody()->write(json_encode(
            $data,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE
            | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
        ));

        return $response
            ->withHeader('Content-Type', 'application/json; charset=utf-8')
            ->withStatus($status);
    }
}
