<?php

declare(strict_types=1);

namespace Hut\controllers;

use Hut\Auth;
use Hut\FoodSuggestion;

class FoodController
{
    public static function show(array $params): void
    {
        Auth::requireLogin();

        $userId = (int) Auth::user()['id'];
        $suggestions = FoodSuggestion::listForPage($userId);
        $residentNameMap = \Hut\Resident::firstNameToIdMap();

        require __DIR__ . '/../../templates/info/food.php';
    }

    public static function create(array $params): void
    {
        Auth::requireLogin();
        Auth::requireCsrf();

        $title = trim((string) ($_POST['title'] ?? ''));
        $notes = trim((string) ($_POST['notes'] ?? ''));

        if ($title === '') {
            $_SESSION['flash_error'] = 'Please enter a food suggestion title.';
            header('Location: ' . \Hut\Url::to('/news/food'));
            exit;
        }

        $foodSuggestionId = FoodSuggestion::create((int) Auth::user()['id'], $title, $notes);
        FoodSuggestion::attachImageFromSearch($foodSuggestionId, $title);
        $_SESSION['flash_success'] = 'Food suggestion added.';
        header('Location: ' . \Hut\Url::to('/news/food'));
        exit;
    }

    public static function heart(array $params): void
    {
        Auth::requireLogin();
        Auth::requireCsrf(true);

        $foodSuggestionId = (int) $params['id'];
        $hearted = FoodSuggestion::toggleHeart((int) Auth::user()['id'], $foodSuggestionId);
        $hearts = FoodSuggestion::heartCount($foodSuggestionId);
        $heartedBy = FoodSuggestion::heartedBy($foodSuggestionId);

        header('Content-Type: application/json');
        echo json_encode(['hearted' => $hearted, 'hearts' => $hearts, 'heartedBy' => $heartedBy]);
    }

    public static function update(array $params): void
    {
        Auth::requireLogin();
        Auth::requireCsrf();

        $foodSuggestionId = (int) $params['id'];
        $suggestion = FoodSuggestion::find($foodSuggestionId);
        if ($suggestion === null) {
            http_response_code(404);
            require __DIR__ . '/../../templates/404.php';
            return;
        }

        $userId = (int) Auth::user()['id'];
        if ((int) $suggestion['user_id'] !== $userId) {
            http_response_code(403);
            require __DIR__ . '/../../templates/403.php';
            return;
        }

        $title = trim((string) ($_POST['title'] ?? ''));
        $notes = trim((string) ($_POST['notes'] ?? ''));

        if ($title === '') {
            $_SESSION['flash_error'] = 'Please enter a food suggestion title.';
            header('Location: ' . \Hut\Url::to('/news/food'));
            exit;
        }

        if (FoodSuggestion::updateIfOwned($foodSuggestionId, $userId, $title, $notes)) {
            $_SESSION['flash_success'] = 'Food suggestion updated.';
        } else {
            $_SESSION['flash_error'] = 'Food suggestion could not be updated.';
        }

        header('Location: ' . \Hut\Url::to('/news/food'));
        exit;
    }

    public static function delete(array $params): void
    {
        Auth::requireLogin();
        Auth::requireCsrf();

        $foodSuggestionId = (int) $params['id'];
        $suggestion = FoodSuggestion::find($foodSuggestionId);
        if ($suggestion === null) {
            http_response_code(404);
            require __DIR__ . '/../../templates/404.php';
            return;
        }

        $userId = (int) Auth::user()['id'];
        if ((int) $suggestion['user_id'] !== $userId) {
            http_response_code(403);
            require __DIR__ . '/../../templates/403.php';
            return;
        }

        if (!FoodSuggestion::deleteIfOwned($foodSuggestionId, $userId)) {
            $_SESSION['flash_error'] = 'Food suggestion could not be deleted.';
        } else {
            $_SESSION['flash_success'] = 'Food suggestion deleted.';
        }

        header('Location: ' . \Hut\Url::to('/news/food'));
        exit;
    }
}