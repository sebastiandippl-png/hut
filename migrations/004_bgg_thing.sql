-- Migration 004: add bgg_thing table to store BGG /thing API data separately from CSV data

CREATE TABLE IF NOT EXISTS bgg_thing (
    bgg_id          INTEGER PRIMARY KEY,   -- matches games.id
    thumbnail       VARCHAR(512),
    image           VARCHAR(512),
    primary_name    VARCHAR(255),
    description     TEXT,
    yearpublished   SMALLINT,
    minplayers      TINYINT,
    maxplayers      TINYINT,
    minage          TINYINT,
    minplaytime     SMALLINT,
    maxplaytime     SMALLINT,
    categories      TEXT,                  -- comma-separated values
    mechanics       TEXT,                  -- comma-separated values
    designers       TEXT,                  -- comma-separated values
    publishers      TEXT,                  -- comma-separated values
    families        TEXT,                  -- comma-separated values
    usersrated      INTEGER,
    average         DECIMAL(8,5),
    bayesaverage    DECIMAL(8,5),
    stddev          DECIMAL(8,5),
    owned           INTEGER,
    wanting         INTEGER,
    wishing         INTEGER,
    numweights      INTEGER,
    averageweight   DECIMAL(4,3),
    last_updated    DATETIME
);

-- Migrate any existing thumbnail URLs from games into bgg_thing
INSERT INTO bgg_thing (bgg_id, thumbnail)
SELECT id, thumbnail
FROM games
WHERE thumbnail IS NOT NULL AND thumbnail != ''
ON CONFLICT(bgg_id) DO UPDATE SET thumbnail = excluded.thumbnail;

-- Remove thumbnail column from games (games table holds only CSV data)
ALTER TABLE games DROP COLUMN thumbnail;
