-- Drop preview_image_url from links if it exists.
-- Uses IF EXISTS so this is safe to run on environments where the column was
-- never added (e.g. production where migration 009 already omits it).
ALTER TABLE links DROP COLUMN IF EXISTS preview_image_url;
