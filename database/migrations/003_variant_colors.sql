-- Colour options: a variant may carry a colour (shown as swatches on the product page).
-- Needed only for databases created before this column existed.
ALTER TABLE product_variants ADD COLUMN color_hex CHAR(7) NULL COMMENT 'swatch colour, e.g. #1a1a1a; NULL = not a colour option' AFTER price;
