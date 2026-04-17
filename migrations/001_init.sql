-- Migration 001: initial schema

CREATE TABLE IF NOT EXISTS users (
    id          INTEGER PRIMARY KEY AUTOINCREMENT,
    name        VARCHAR(255) NOT NULL,
    email       VARCHAR(255) NOT NULL UNIQUE,
    password_hash VARCHAR(255),
    google_id   VARCHAR(255) UNIQUE,
    is_admin    TINYINT(1) NOT NULL DEFAULT 0,
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS games (
    id              INTEGER PRIMARY KEY AUTOINCREMENT,
    bgg_id          INTEGER NOT NULL UNIQUE,
    title           VARCHAR(255) NOT NULL,
    year            SMALLINT,
    min_players     TINYINT,
    max_players     TINYINT,
    min_playtime    SMALLINT,
    max_playtime    SMALLINT,
    weight          DECIMAL(4,3),
    description     TEXT,
    thumbnail       VARCHAR(512),
    image           VARCHAR(512),
    categories      TEXT,
    mechanics       TEXT,
    designers       TEXT,
    raw_xml         TEXT,
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS user_games (
    id          INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id     INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    game_id     INTEGER NOT NULL REFERENCES games(id) ON DELETE CASCADE,
    selected    TINYINT(1) NOT NULL DEFAULT 1,
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE (user_id, game_id)
);

CREATE TABLE IF NOT EXISTS votes (
    id          INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id     INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    game_id     INTEGER NOT NULL REFERENCES games(id) ON DELETE CASCADE,
    value       TINYINT NOT NULL CHECK (value IN (-1, 1)),
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE (user_id, game_id)
);

CREATE TABLE IF NOT EXISTS migrations (
    id          INTEGER PRIMARY KEY AUTOINCREMENT,
    name        VARCHAR(255) NOT NULL UNIQUE,
    run_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
);
