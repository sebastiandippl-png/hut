CREATE TABLE IF NOT EXISTS link_categories (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL,
    sort_order INTEGER NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE(name)
);

ALTER TABLE links ADD COLUMN category_id INTEGER DEFAULT NULL REFERENCES link_categories(id) ON DELETE SET NULL;

CREATE INDEX IF NOT EXISTS idx_links_category_id ON links(category_id);
CREATE INDEX IF NOT EXISTS idx_link_categories_sort_order ON link_categories(sort_order);
