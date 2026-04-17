CREATE TABLE IF NOT EXISTS user_collection (
    id           INTEGER PRIMARY KEY AUTOINCREMENT,
    bgg_game_id  INTEGER NOT NULL,
    bgg_user     VARCHAR(255) NOT NULL,
    created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE (bgg_game_id, bgg_user)
);
