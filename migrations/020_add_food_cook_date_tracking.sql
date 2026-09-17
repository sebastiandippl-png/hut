ALTER TABLE food_suggestions ADD COLUMN cook_date_user_id INTEGER DEFAULT NULL REFERENCES users(id) ON DELETE SET NULL;
ALTER TABLE food_suggestions ADD COLUMN cook_date_updated_at DATETIME NULL;
