CREATE TABLE IF NOT EXISTS collection_removals (
    id                  INTEGER PRIMARY KEY AUTOINCREMENT,
    game_id             INTEGER NOT NULL REFERENCES games(id) ON DELETE CASCADE,
    removed_by_user_id  INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    reason              VARCHAR(32) NOT NULL,
    removed_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
);
