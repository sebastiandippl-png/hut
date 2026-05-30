ALTER TABLE food_suggestions ADD COLUMN image_url VARCHAR(1024) NULL;
ALTER TABLE food_suggestions ADD COLUMN image_source_url VARCHAR(1024) NULL;
ALTER TABLE food_suggestions ADD COLUMN image_creator VARCHAR(255) NULL;
ALTER TABLE food_suggestions ADD COLUMN image_license VARCHAR(255) NULL;