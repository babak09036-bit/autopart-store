-- Per-product fitment years and notes. Needed only for databases created before this column existed;
-- a fresh install from schema.sql already has them.
ALTER TABLE product_vehicle_compat
  ADD COLUMN year_from SMALLINT UNSIGNED NULL AFTER vehicle_model_id,
  ADD COLUMN year_to SMALLINT UNSIGNED NULL AFTER year_from,
  ADD COLUMN note VARCHAR(160) NULL AFTER year_to;
