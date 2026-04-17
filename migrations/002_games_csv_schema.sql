PRAGMA foreign_keys = OFF;

ALTER TABLE games RENAME TO games_legacy;

CREATE TABLE games (
    id                    INTEGER PRIMARY KEY,
    name                  VARCHAR(255) NOT NULL,
    yearpublished         SMALLINT,
    rank                  INTEGER,
    bayesaverage          DECIMAL(8,5),
    average               DECIMAL(8,5),
    usersrated            INTEGER,
    is_expansion          TINYINT(1),
    abstracts_rank        INTEGER,
    cgs_rank              INTEGER,
    childrensgames_rank   INTEGER,
    familygames_rank      INTEGER,
    partygames_rank       INTEGER,
    strategygames_rank    INTEGER,
    thematic_rank         INTEGER,
    wargames_rank         INTEGER
);

INSERT INTO games (
    id,
    name,
    yearpublished,
    rank,
    bayesaverage,
    average,
    usersrated,
    is_expansion,
    abstracts_rank,
    cgs_rank,
    childrensgames_rank,
    familygames_rank,
    partygames_rank,
    strategygames_rank,
    thematic_rank,
    wargames_rank
)
SELECT
    COALESCE(games_legacy.bgg_id, games_legacy.id) AS id,
    games_legacy.title AS name,
    games_legacy.year AS yearpublished,
    CASE WHEN json_valid(games_legacy.raw_xml) THEN CAST(json_extract(games_legacy.raw_xml, '$.rank') AS INTEGER) END AS rank,
    CASE WHEN json_valid(games_legacy.raw_xml) THEN CAST(json_extract(games_legacy.raw_xml, '$.bayesaverage') AS REAL) END AS bayesaverage,
    CASE WHEN json_valid(games_legacy.raw_xml) THEN CAST(json_extract(games_legacy.raw_xml, '$.average') AS REAL) END AS average,
    CASE WHEN json_valid(games_legacy.raw_xml) THEN CAST(json_extract(games_legacy.raw_xml, '$.usersrated') AS INTEGER) END AS usersrated,
    CASE WHEN json_valid(games_legacy.raw_xml) THEN COALESCE(CAST(json_extract(games_legacy.raw_xml, '$.is_expansion') AS INTEGER), 0) ELSE 0 END AS is_expansion,
    CASE WHEN json_valid(games_legacy.raw_xml) THEN CAST(json_extract(games_legacy.raw_xml, '$.abstracts_rank') AS INTEGER) END AS abstracts_rank,
    CASE WHEN json_valid(games_legacy.raw_xml) THEN CAST(json_extract(games_legacy.raw_xml, '$.cgs_rank') AS INTEGER) END AS cgs_rank,
    CASE WHEN json_valid(games_legacy.raw_xml) THEN CAST(json_extract(games_legacy.raw_xml, '$.childrensgames_rank') AS INTEGER) END AS childrensgames_rank,
    CASE WHEN json_valid(games_legacy.raw_xml) THEN CAST(json_extract(games_legacy.raw_xml, '$.familygames_rank') AS INTEGER) END AS familygames_rank,
    CASE WHEN json_valid(games_legacy.raw_xml) THEN CAST(json_extract(games_legacy.raw_xml, '$.partygames_rank') AS INTEGER) END AS partygames_rank,
    CASE WHEN json_valid(games_legacy.raw_xml) THEN CAST(json_extract(games_legacy.raw_xml, '$.strategygames_rank') AS INTEGER) END AS strategygames_rank,
    CASE WHEN json_valid(games_legacy.raw_xml) THEN CAST(json_extract(games_legacy.raw_xml, '$.thematic_rank') AS INTEGER) END AS thematic_rank,
    CASE WHEN json_valid(games_legacy.raw_xml) THEN CAST(json_extract(games_legacy.raw_xml, '$.wargames_rank') AS INTEGER) END AS wargames_rank
FROM games_legacy;

ALTER TABLE user_games RENAME TO user_games_legacy;

CREATE TABLE user_games (
    id          INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id     INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    game_id     INTEGER NOT NULL REFERENCES games(id) ON DELETE CASCADE,
    selected    TINYINT(1) NOT NULL DEFAULT 1,
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE (user_id, game_id)
);

INSERT INTO user_games (id, user_id, game_id, selected, created_at)
SELECT
    ug.id,
    ug.user_id,
    gl.bgg_id,
    ug.selected,
    ug.created_at
FROM user_games_legacy ug
JOIN games_legacy gl ON gl.id = ug.game_id
WHERE gl.bgg_id IS NOT NULL;

ALTER TABLE votes RENAME TO votes_legacy;

CREATE TABLE votes (
    id          INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id     INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    game_id     INTEGER NOT NULL REFERENCES games(id) ON DELETE CASCADE,
    value       TINYINT NOT NULL CHECK (value IN (-1, 1)),
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE (user_id, game_id)
);

INSERT INTO votes (id, user_id, game_id, value, created_at)
SELECT
    v.id,
    v.user_id,
    gl.bgg_id,
    v.value,
    v.created_at
FROM votes_legacy v
JOIN games_legacy gl ON gl.id = v.game_id
WHERE gl.bgg_id IS NOT NULL;

DROP TABLE votes_legacy;
DROP TABLE user_games_legacy;
DROP TABLE games_legacy;

PRAGMA foreign_keys = ON;