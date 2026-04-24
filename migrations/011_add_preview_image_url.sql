-- Re-add preview_image_url to links.
-- NULL = not yet tried, '' = tried but nothing found, non-empty = cached image URL.
ALTER TABLE links ADD COLUMN preview_image_url TEXT DEFAULT NULL;
