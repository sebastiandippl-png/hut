-- Migration 003: add thumbnail column to store the BGG CDN image URL
ALTER TABLE games ADD COLUMN thumbnail VARCHAR(512);
