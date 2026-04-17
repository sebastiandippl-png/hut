-- Migration 005: store suggested player-count poll values from BGG thing data

ALTER TABLE bgg_thing ADD COLUMN best_playercount VARCHAR(16);
ALTER TABLE bgg_thing ADD COLUMN best_playercount_num SMALLINT;
ALTER TABLE bgg_thing ADD COLUMN player_best_values TEXT;
ALTER TABLE bgg_thing ADD COLUMN player_recommended_values TEXT;
ALTER TABLE bgg_thing ADD COLUMN player_not_recommended_values TEXT;
