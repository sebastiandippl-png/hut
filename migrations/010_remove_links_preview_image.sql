-- Recreate links table without preview_image_url column.
CREATE TABLE IF NOT EXISTS links_new (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    title TEXT NOT NULL,
    url TEXT NOT NULL,
    description TEXT NOT NULL DEFAULT '',
    sort_order INTEGER NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
);
INSERT OR IGNORE INTO links_new (id, title, url, description, sort_order, created_at)
    SELECT id, title, url, description, sort_order, created_at FROM links;
DROP TABLE links;
ALTER TABLE links_new RENAME TO links;
