<?php

declare(strict_types=1);

namespace Src\Controllers;

use App\Security\Auth\AuthService;
use App\Security\Http\CsrfTokenManager;
use App\Application\Security\IpBlockerService;

final class AuthController
{
    public function __construct(
        private readonly AuthService $authService,
        private readonly CsrfTokenManager $csrfTokenManager,
        private readonly IpBlockerService $ipBlockerService,
    ) {
    }

    public function showLogin(?string $error = null): void
    {
        $pageTitle = 'Clean Marvel Album — Login';
        $additionalStyles = [];
        $isAuthenticated = $this->authService->isAuthenticated();

        if ($error === null && isset($_SESSION['auth_error'])) {
            $error = (string) $_SESSION['auth_error'];
            unset($_SESSION['auth_error']);
        }

        require_once dirname(__DIR__, 2) . '/views/pages/login.php';
    }

    public function login(): void
    {
        $csrfToken = $_POST['_token'] ?? null;
        if (!$this->csrfTokenManager->validate(is_string($csrfToken) ? $csrfToken : null)) {
            $this->flashError('Token de seguridad inválido. Recarga la página e inténtalo de nuevo.');
            $this->redirect('/login');
            return;
        }

        $email = (string) ($_POST['email'] ?? '');
        $password = (string) ($_POST['password'] ?? '');
        $ip = is_string($_SERVER['REMOTE_ADDR'] ?? null) ? (string) $_SERVER['REMOTE_ADDR'] : 'unknown';

        if (!$this->ipBlockerService->check($email, $ip)) {
            $minutes = $this->ipBlockerService->getBlockMinutesRemaining($email, $ip);
            http_response_code(429);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode([
                'error' => 'Demasiados intentos de login. Inténtalo de nuevo en ' . $minutes . ' minutos.',
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            return;
        }

        if ($this->authService->login($email, $password)) {
            $this->ipBlockerService->registerSuccessfulLogin($email, $ip);
            $redirectTo = '/seccion';
            if (isset($_SESSION['redirect_to']) && is_string($_SESSION['redirect_to']) && $_SESSION['redirect_to'] !== '') {
                $redirectTo = $_SESSION['redirect_to'];
            }

            unset($_SESSION['redirect_to'], $_SESSION['auth_error']);

            $this->redirect($redirectTo);
            return;
        }

        $this->ipBlockerService->registerFailedAttempt($email, $ip);
        $this->authService->logout();
        $this->flashError('Credenciales inválidas. Usa marvel@gmail.com / marvel2025.');
        $this->redirect('/login');
    }

    public function logout(): void
    {
        $csrfToken = $_POST['_token'] ?? null;
        if (!$this->csrfTokenManager->validate(is_string($csrfToken) ? $csrfToken : null)) {
            $this->redirect('/');
            return;
        }

        $this->authService->logout();
        $this->redirect('/');
    }

    private function redirect(string $path): void
    {
        header('Location: ' . $path, true, 302);
        exit;
    }

    private function flashError(string $message): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $_SESSION['auth_error'] = $message;
    }
}
