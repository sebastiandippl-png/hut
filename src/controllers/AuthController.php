<?php

declare(strict_types=1);

namespace Hut\controllers;

use Hut\Auth;
use Google\Client as GoogleClient;

class AuthController
{
    public static function showLogin(array $params): void
    {
        if (Auth::user()) {
            header('Location: ' . \Hut\Url::to('/')); exit;
        }
        require __DIR__ . '/../../templates/login.php';
    }

    public static function login(array $params): void
    {
        Auth::requireCsrf();

        $email    = $_POST['email']    ?? '';
        $password = $_POST['password'] ?? '';

        $user = Auth::attemptLogin($email, $password);
        if (!$user) {
            // Check if user is pending approval (credentials correct but not approved)
            if (Auth::isUserPendingApproval($email)) {
                $_SESSION['flash_error'] = 'Your account is pending admin approval. Please wait for your account to be activated.';
            } else {
                $_SESSION['flash_error'] = 'Invalid email or password.';
            }
            header('Location: ' . \Hut\Url::to('/login')); exit;
        }

        Auth::login($user);
        header('Location: ' . \Hut\Url::to('/')); exit;
    }

    public static function showRegister(array $params): void
    {
        if (Auth::user()) {
            header('Location: ' . \Hut\Url::to('/')); exit;
        }
        require __DIR__ . '/../../templates/register.php';
    }

    public static function register(array $params): void
    {
        Auth::requireCsrf();

        $name     = trim($_POST['name']     ?? '');
        $email    = trim($_POST['email']    ?? '');
        $password = $_POST['password']      ?? '';
        $confirm  = $_POST['password_confirm'] ?? '';

        if (!$name || !$email || !$password) {
            $_SESSION['flash_error'] = 'All fields are required.';
            header('Location: ' . \Hut\Url::to('/register')); exit;
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $_SESSION['flash_error'] = 'Invalid email address.';
            header('Location: ' . \Hut\Url::to('/register')); exit;
        }
        if (strlen($password) < 8) {
            $_SESSION['flash_error'] = 'Password must be at least 8 characters.';
            header('Location: ' . \Hut\Url::to('/register')); exit;
        }
        if ($password !== $confirm) {
            $_SESSION['flash_error'] = 'Passwords do not match.';
            header('Location: ' . \Hut\Url::to('/register')); exit;
        }

        try {
            $user = Auth::register($name, $email, $password);
            if (!((int)($user['is_approved'] ?? 1) === 1)) {
                $_SESSION['flash_success'] = 'Registration successful. Your account is pending admin approval.';
                header('Location: ' . \Hut\Url::to('/login')); exit;
            }
            Auth::login($user);
            header('Location: ' . \Hut\Url::to('/')); exit;
        } catch (\RuntimeException $e) {
            $_SESSION['flash_error'] = $e->getMessage();
            header('Location: ' . \Hut\Url::to('/register')); exit;
        }
    }

    public static function logout(array $params): void
    {
        Auth::requireCsrf();
        Auth::logout();
        header('Location: ' . \Hut\Url::to('/login')); exit;
    }

    public static function googleRedirect(array $params): void
    {
        $client = self::googleClient();
        $state = bin2hex(random_bytes(24));
        $_SESSION['oauth_state'] = $state;
        $client->setState($state);
        $url    = $client->createAuthUrl();
        header('Location: ' . $url); exit;
    }

    public static function googleCallback(array $params): void
    {
        $code = $_GET['code'] ?? '';
        $state = $_GET['state'] ?? '';
        $expectedState = $_SESSION['oauth_state'] ?? '';
        unset($_SESSION['oauth_state']);

        if (!is_string($state) || !is_string($expectedState) || $state === '' || !hash_equals($expectedState, $state)) {
            $_SESSION['flash_error'] = 'Google login failed.';
            header('Location: ' . \Hut\Url::to('/login')); exit;
        }

        if (!$code) {
            $_SESSION['flash_error'] = 'Google login failed.';
            header('Location: ' . \Hut\Url::to('/login')); exit;
        }

        try {
            $client = self::googleClient();
            $token  = $client->fetchAccessTokenWithAuthCode($code);
            if (isset($token['error'])) {
                throw new \RuntimeException($token['error_description'] ?? 'OAuth error');
            }
            $client->setAccessToken($token);
            $oauth  = new \Google\Service\Oauth2($client);
            $info   = $oauth->userinfo->get();

            $user = Auth::findOrCreateGoogleUser(
                $info->getId(),
                $info->getEmail(),
                $info->getName()
            );
            if (!((int)($user['is_approved'] ?? 1) === 1)) {
                $_SESSION['flash_success'] = 'Google account linked. Your account is pending admin approval.';
                header('Location: ' . \Hut\Url::to('/login')); exit;
            }
            Auth::login($user);
            header('Location: ' . \Hut\Url::to('/')); exit;
        } catch (\Exception $e) {
            error_log('Google OAuth callback failed: ' . $e->getMessage());
            $_SESSION['flash_error'] = 'Google login failed.';
            header('Location: ' . \Hut\Url::to('/login')); exit;
        }
    }

    private static function googleClient(): GoogleClient
    {
        $client = new GoogleClient();
        $client->setClientId($_ENV['GOOGLE_CLIENT_ID']);
        $client->setClientSecret($_ENV['GOOGLE_CLIENT_SECRET']);
        $client->setRedirectUri($_ENV['GOOGLE_REDIRECT_URI']);
        $client->addScope('email');
        $client->addScope('profile');
        return $client;
    }
}
