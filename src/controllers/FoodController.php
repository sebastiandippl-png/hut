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

    public static function cookDate(array $params): void
    {
        Auth::requireLogin();
        Auth::requireCsrf(true);

        $foodSuggestionId = (int) $params['id'];
        $suggestion = FoodSuggestion::find($foodSuggestionId);
        if ($suggestion === null) {
            http_response_code(404);
            echo json_encode(['error' => 'Not found']);
            return;
        }

        $cookDate = trim((string) ($_POST['cook_date'] ?? ''));
        $ok = FoodSuggestion::setCookDate($foodSuggestionId, $cookDate === '' ? null : $cookDate, (int) Auth::user()['id']);

        if (!$ok) {
            http_response_code(422);
            header('Content-Type: application/json');
            echo json_encode(['error' => 'Invalid date']);
            return;
        }

        header('Content-Type: application/json');
        echo json_encode(['cookDate' => $cookDate === '' ? null : $cookDate]);
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
        $isAdmin = (bool) Auth::user()['is_admin'];
        if ((int) $suggestion['user_id'] !== $userId && !$isAdmin) {
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

        $updated = $isAdmin
            ? FoodSuggestion::updateAsAdmin($foodSuggestionId, $title, $notes)
            : FoodSuggestion::updateIfOwned($foodSuggestionId, $userId, $title, $notes);

        if ($updated) {
            FoodSuggestion::attachImageFromSearch($foodSuggestionId, $title);
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
        $isAdmin = (bool) Auth::user()['is_admin'];
        if ((int) $suggestion['user_id'] !== $userId && !$isAdmin) {
            http_response_code(403);
            require __DIR__ . '/../../templates/403.php';
            return;
        }

        $deleted = $isAdmin
            ? FoodSuggestion::deleteAsAdmin($foodSuggestionId)
            : FoodSuggestion::deleteIfOwned($foodSuggestionId, $userId);

        if (!$deleted) {
            $_SESSION['flash_error'] = 'Food suggestion could not be deleted.';
        } else {
            $_SESSION['flash_success'] = 'Food suggestion deleted.';
        }

        header('Location: ' . \Hut\Url::to('/news/food'));
        exit;
    }
}