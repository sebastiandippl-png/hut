<?php

declare(strict_types=1);

namespace Hut\controllers;

use Hut\Auth;

class ShoppingListController
{
    // Shared Google Sheet; "Anyone with the link" must stay at least Viewer for the embed to load.
    private const SHEET_ID = '1nILxgO-vsI43cY92XsXieFfVj6ehwBbguG9PJwhzeIU';
    private const SHEET_GID = '1418548641'; // "Einkaufsliste" tab

    public static function show(array $params): void
    {
        Auth::requireLogin();

        // Query param gid= is ignored by /preview; the tab is only honored as a URL hash.
        $embedUrl = 'https://docs.google.com/spreadsheets/d/' . self::SHEET_ID
            . '/preview#gid=' . self::SHEET_GID;
        // Google blocks /edit from being framed cross-origin, so editing happens via this link instead.
        $editUrl = 'https://docs.google.com/spreadsheets/d/' . self::SHEET_ID
            . '/edit#gid=' . self::SHEET_GID;

        require __DIR__ . '/../../templates/info/shopping_list.php';
    }
}
