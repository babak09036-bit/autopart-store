-- =====================================================================
-- AutoPart store — database schema
-- MySQL 8+ / MariaDB 10.4+ · InnoDB · utf8mb4
-- All DATETIME columns are stored in UTC (the app sets time_zone = '+00:00').
-- Money columns are DECIMAL(14,2), amounts in Toman.
-- =====================================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ---------------------------------------------------------------------
-- Users & authentication
-- ---------------------------------------------------------------------
CREATE TABLE users (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  full_name VARCHAR(120) NOT NULL,
  mobile VARCHAR(11) NULL,
  email VARCHAR(190) NULL,
  password_hash VARCHAR(255) NOT NULL,
  status ENUM('active','suspended') NOT NULL DEFAULT 'active',
  last_login_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_users_mobile (mobile),
  UNIQUE KEY uq_users_email (email),
  KEY idx_users_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE user_addresses (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id BIGINT UNSIGNED NOT NULL,
  title VARCHAR(60) NOT NULL,
  receiver_name VARCHAR(120) NOT NULL,
  receiver_mobile VARCHAR(11) NOT NULL,
  province VARCHAR(60) NOT NULL,
  city VARCHAR(60) NOT NULL,
  postal_code VARCHAR(10) NULL,
  address_line VARCHAR(500) NOT NULL,
  is_default TINYINT(1) NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_addresses_user (user_id),
  CONSTRAINT fk_addresses_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE password_reset_tokens (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id BIGINT UNSIGNED NOT NULL,
  token_hash CHAR(64) NOT NULL,
  expires_at DATETIME NOT NULL,
  used_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_reset_token (token_hash),
  KEY idx_reset_user (user_id),
  CONSTRAINT fk_reset_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE login_attempts (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  scope ENUM('user','admin') NOT NULL,
  identifier VARCHAR(190) NOT NULL,
  ip VARCHAR(45) NOT NULL,
  success TINYINT(1) NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_attempts_identifier (scope, identifier, created_at),
  KEY idx_attempts_ip (scope, ip, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Generic fixed-window rate limiter for sensitive API endpoints.
CREATE TABLE rate_limits (
  bucket CHAR(64) NOT NULL,
  hits INT UNSIGNED NOT NULL DEFAULT 0,
  reset_at DATETIME NOT NULL,
  PRIMARY KEY (bucket),
  KEY idx_rate_reset (reset_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Administrators, roles & permissions
-- ---------------------------------------------------------------------
CREATE TABLE admin_users (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  full_name VARCHAR(120) NOT NULL,
  username VARCHAR(60) NOT NULL,
  email VARCHAR(190) NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
  status ENUM('active','disabled') NOT NULL DEFAULT 'active',
  last_login_at DATETIME NULL,
  last_login_ip VARCHAR(45) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_admin_username (username),
  UNIQUE KEY uq_admin_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE roles (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  slug VARCHAR(60) NOT NULL,
  name VARCHAR(120) NOT NULL,
  description VARCHAR(255) NULL,
  is_system TINYINT(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (id),
  UNIQUE KEY uq_roles_slug (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE permissions (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  slug VARCHAR(80) NOT NULL,
  name VARCHAR(120) NOT NULL,
  group_name VARCHAR(60) NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_permissions_slug (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE role_permissions (
  role_id INT UNSIGNED NOT NULL,
  permission_id INT UNSIGNED NOT NULL,
  PRIMARY KEY (role_id, permission_id),
  KEY idx_rp_permission (permission_id),
  CONSTRAINT fk_rp_role FOREIGN KEY (role_id) REFERENCES roles (id) ON DELETE CASCADE,
  CONSTRAINT fk_rp_permission FOREIGN KEY (permission_id) REFERENCES permissions (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE admin_user_roles (
  admin_user_id BIGINT UNSIGNED NOT NULL,
  role_id INT UNSIGNED NOT NULL,
  PRIMARY KEY (admin_user_id, role_id),
  KEY idx_aur_role (role_id),
  CONSTRAINT fk_aur_admin FOREIGN KEY (admin_user_id) REFERENCES admin_users (id) ON DELETE CASCADE,
  CONSTRAINT fk_aur_role FOREIGN KEY (role_id) REFERENCES roles (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE admin_activity_logs (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  admin_user_id BIGINT UNSIGNED NULL,
  action VARCHAR(60) NOT NULL,
  entity_type VARCHAR(60) NOT NULL,
  entity_id BIGINT UNSIGNED NULL,
  description VARCHAR(255) NULL,
  changes LONGTEXT NULL CHECK (changes IS NULL OR JSON_VALID(changes)),
  ip VARCHAR(45) NULL,
  user_agent VARCHAR(255) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_aal_admin (admin_user_id, created_at),
  KEY idx_aal_entity (entity_type, entity_id),
  KEY idx_aal_created (created_at),
  CONSTRAINT fk_aal_admin FOREIGN KEY (admin_user_id) REFERENCES admin_users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- System / customer events that are not admin actions (orders placed, stock auto-changes, ...).
CREATE TABLE audit_logs (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  actor_type ENUM('system','user','guest','admin') NOT NULL,
  actor_id BIGINT UNSIGNED NULL,
  event VARCHAR(80) NOT NULL,
  entity_type VARCHAR(60) NOT NULL,
  entity_id BIGINT UNSIGNED NULL,
  data LONGTEXT NULL CHECK (data IS NULL OR JSON_VALID(data)),
  ip VARCHAR(45) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_audit_entity (entity_type, entity_id),
  KEY idx_audit_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Media library
-- ---------------------------------------------------------------------
CREATE TABLE media_files (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  path VARCHAR(255) NOT NULL,
  thumb_path VARCHAR(255) NULL,
  original_name VARCHAR(190) NOT NULL,
  mime VARCHAR(60) NOT NULL,
  size_bytes INT UNSIGNED NOT NULL,
  width INT UNSIGNED NULL,
  height INT UNSIGNED NULL,
  alt VARCHAR(255) NULL,
  folder ENUM('products','banners','categories','brands','blog','pages','reviews','general') NOT NULL DEFAULT 'general',
  uploaded_by BIGINT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_media_path (path),
  KEY idx_media_folder (folder, created_at),
  CONSTRAINT fk_media_admin FOREIGN KEY (uploaded_by) REFERENCES admin_users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Catalogue
-- ---------------------------------------------------------------------
CREATE TABLE brands (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  name VARCHAR(120) NOT NULL,
  slug VARCHAR(160) NOT NULL,
  logo_path VARCHAR(255) NULL,
  description TEXT NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  sort_order INT NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_brands_slug (slug),
  KEY idx_brands_active (is_active, sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE categories (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  parent_id BIGINT UNSIGNED NULL,
  name VARCHAR(120) NOT NULL,
  short_name VARCHAR(60) NULL,
  slug VARCHAR(160) NOT NULL,
  description TEXT NULL,
  image_path VARCHAR(255) NULL,
  sort_order INT NOT NULL DEFAULT 0,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_categories_slug (slug),
  KEY idx_categories_parent (parent_id, sort_order),
  CONSTRAINT fk_categories_parent FOREIGN KEY (parent_id) REFERENCES categories (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE products (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  brand_id BIGINT UNSIGNED NULL,
  name VARCHAR(190) NOT NULL,
  slug VARCHAR(190) NOT NULL,
  product_type VARCHAR(80) NULL COMMENT 'Sub-type label used as a filter chip, e.g. «واکس»',
  sku VARCHAR(64) NULL,
  gtin VARCHAR(14) NULL COMMENT 'Only real GTIN/EAN codes; leave NULL when unknown',
  short_description VARCHAR(500) NULL,
  description MEDIUMTEXT NULL COMMENT 'Sanitised HTML',
  price DECIMAL(14,2) NOT NULL,
  sale_price DECIMAL(14,2) NULL,
  sale_starts_at DATETIME NULL,
  sale_ends_at DATETIME NULL,
  status ENUM('draft','published','archived') NOT NULL DEFAULT 'draft',
  is_featured TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Expert pick',
  is_bestseller TINYINT(1) NOT NULL DEFAULT 0,
  is_new TINYINT(1) NOT NULL DEFAULT 0,
  is_amazing TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Amazing offer / flash deal',
  is_universal_fit TINYINT(1) NOT NULL DEFAULT 1 COMMENT 'Fits every passenger car',
  rating_avg DECIMAL(3,2) NOT NULL DEFAULT 0 COMMENT 'Maintained from approved reviews only',
  rating_count INT UNSIGNED NOT NULL DEFAULT 0,
  sales_count INT UNSIGNED NOT NULL DEFAULT 0,
  search_text TEXT NULL COMMENT 'Normalised text for space/ye/kaf-insensitive search',
  published_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_products_slug (slug),
  UNIQUE KEY uq_products_sku (sku),
  KEY idx_products_status (status, published_at),
  KEY idx_products_brand (brand_id),
  KEY idx_products_flags (status, is_amazing, is_bestseller, is_featured, is_new),
  KEY idx_products_price (price),
  CONSTRAINT fk_products_brand FOREIGN KEY (brand_id) REFERENCES brands (id) ON DELETE SET NULL,
  CONSTRAINT chk_products_price CHECK (price >= 0),
  CONSTRAINT chk_products_sale CHECK (sale_price IS NULL OR (sale_price >= 0 AND sale_price < price))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE product_images (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  product_id BIGINT UNSIGNED NOT NULL,
  path VARCHAR(255) NOT NULL,
  alt VARCHAR(255) NULL,
  sort_order INT NOT NULL DEFAULT 0,
  is_primary TINYINT(1) NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_pimages_product (product_id, sort_order),
  CONSTRAINT fk_pimages_product FOREIGN KEY (product_id) REFERENCES products (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE product_categories (
  product_id BIGINT UNSIGNED NOT NULL,
  category_id BIGINT UNSIGNED NOT NULL,
  is_primary TINYINT(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (product_id, category_id),
  KEY idx_pc_category (category_id),
  CONSTRAINT fk_pc_product FOREIGN KEY (product_id) REFERENCES products (id) ON DELETE CASCADE,
  CONSTRAINT fk_pc_category FOREIGN KEY (category_id) REFERENCES categories (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE product_variants (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  product_id BIGINT UNSIGNED NOT NULL,
  name VARCHAR(120) NOT NULL COMMENT 'e.g. «۴ لیتری» or «مشکی»',
  sku VARCHAR(64) NULL,
  price DECIMAL(14,2) NULL COMMENT 'Overrides the product price when set',
  color_hex CHAR(7) NULL COMMENT 'swatch colour, e.g. #1a1a1a; NULL = not a colour option',
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  sort_order INT NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_variants_sku (sku),
  KEY idx_variants_product (product_id, sort_order),
  CONSTRAINT fk_variants_product FOREIGN KEY (product_id) REFERENCES products (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- One row per product (variant_id = 0) or per variant.
CREATE TABLE inventory (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  product_id BIGINT UNSIGNED NOT NULL,
  variant_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
  quantity INT NOT NULL DEFAULT 0,
  low_stock_threshold INT NOT NULL DEFAULT 5,
  updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_inventory_item (product_id, variant_id),
  KEY idx_inventory_low (quantity),
  CONSTRAINT fk_inventory_product FOREIGN KEY (product_id) REFERENCES products (id) ON DELETE CASCADE,
  CONSTRAINT chk_inventory_qty CHECK (quantity >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE price_change_batches (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  admin_user_id BIGINT UNSIGNED NULL,
  description VARCHAR(255) NOT NULL,
  rule_json TEXT NOT NULL COMMENT 'scope and rule as submitted (for the history view)',
  products_count INT NOT NULL DEFAULT 0,
  items_count INT NOT NULL DEFAULT 0,
  reverted_at DATETIME NULL,
  reverted_by BIGINT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_pcb_created (created_at),
  CONSTRAINT fk_pcb_admin FOREIGN KEY (admin_user_id) REFERENCES admin_users (id) ON DELETE SET NULL,
  CONSTRAINT fk_pcb_reverter FOREIGN KEY (reverted_by) REFERENCES admin_users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE price_change_items (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  batch_id BIGINT UNSIGNED NOT NULL,
  product_id BIGINT UNSIGNED NOT NULL,
  variant_id BIGINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '0 = the product row itself',
  old_price DECIMAL(14,2) NULL,
  new_price DECIMAL(14,2) NULL,
  old_sale_price DECIMAL(14,2) NULL,
  new_sale_price DECIMAL(14,2) NULL,
  PRIMARY KEY (id),
  KEY idx_pci_batch (batch_id),
  KEY idx_pci_product (product_id),
  CONSTRAINT fk_pci_batch FOREIGN KEY (batch_id) REFERENCES price_change_batches (id) ON DELETE CASCADE,
  CONSTRAINT fk_pci_product FOREIGN KEY (product_id) REFERENCES products (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE inventory_movements (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  product_id BIGINT UNSIGNED NOT NULL,
  variant_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
  change_qty INT NOT NULL,
  quantity_after INT NOT NULL,
  reason ENUM('initial','manual','order','order_cancel','return','adjustment') NOT NULL,
  reference_type VARCHAR(40) NULL,
  reference_id BIGINT UNSIGNED NULL,
  note VARCHAR(255) NULL,
  admin_user_id BIGINT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_im_product (product_id, created_at),
  CONSTRAINT fk_im_product FOREIGN KEY (product_id) REFERENCES products (id) ON DELETE CASCADE,
  CONSTRAINT fk_im_admin FOREIGN KEY (admin_user_id) REFERENCES admin_users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE product_attributes (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  name VARCHAR(120) NOT NULL,
  sort_order INT NOT NULL DEFAULT 0,
  PRIMARY KEY (id),
  UNIQUE KEY uq_attributes_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE product_attribute_values (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  product_id BIGINT UNSIGNED NOT NULL,
  attribute_id BIGINT UNSIGNED NOT NULL,
  value VARCHAR(255) NOT NULL,
  sort_order INT NOT NULL DEFAULT 0,
  PRIMARY KEY (id),
  UNIQUE KEY uq_pav (product_id, attribute_id),
  KEY idx_pav_attribute (attribute_id),
  CONSTRAINT fk_pav_product FOREIGN KEY (product_id) REFERENCES products (id) ON DELETE CASCADE,
  CONSTRAINT fk_pav_attribute FOREIGN KEY (attribute_id) REFERENCES product_attributes (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE product_relations (
  product_id BIGINT UNSIGNED NOT NULL,
  related_product_id BIGINT UNSIGNED NOT NULL,
  type ENUM('related','complementary') NOT NULL DEFAULT 'related',
  sort_order INT NOT NULL DEFAULT 0,
  PRIMARY KEY (product_id, related_product_id, type),
  KEY idx_prel_related (related_product_id),
  CONSTRAINT fk_prel_product FOREIGN KEY (product_id) REFERENCES products (id) ON DELETE CASCADE,
  CONSTRAINT fk_prel_related FOREIGN KEY (related_product_id) REFERENCES products (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Vehicle compatibility (used by the vehicle selector and product filters).
CREATE TABLE vehicle_brands (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  name VARCHAR(80) NOT NULL,
  slug VARCHAR(80) NOT NULL,
  sort_order INT NOT NULL DEFAULT 0,
  PRIMARY KEY (id),
  UNIQUE KEY uq_vbrands_slug (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE vehicle_models (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  vehicle_brand_id BIGINT UNSIGNED NOT NULL,
  name VARCHAR(80) NOT NULL,
  slug VARCHAR(80) NOT NULL,
  year_from SMALLINT UNSIGNED NOT NULL COMMENT 'Solar Hijri year',
  year_to SMALLINT UNSIGNED NOT NULL,
  sort_order INT NOT NULL DEFAULT 0,
  PRIMARY KEY (id),
  UNIQUE KEY uq_vmodels_slug (slug),
  KEY idx_vmodels_brand (vehicle_brand_id),
  CONSTRAINT fk_vmodels_brand FOREIGN KEY (vehicle_brand_id) REFERENCES vehicle_brands (id) ON DELETE CASCADE,
  CONSTRAINT chk_vmodels_years CHECK (year_to >= year_from)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE product_vehicle_compat (
  product_id BIGINT UNSIGNED NOT NULL,
  vehicle_model_id BIGINT UNSIGNED NOT NULL,
  year_from SMALLINT UNSIGNED NULL COMMENT 'first fitting production year (solar); NULL = the model''s first year',
  year_to SMALLINT UNSIGNED NULL COMMENT 'last fitting production year (solar); NULL = the model''s last year',
  note VARCHAR(160) NULL COMMENT 'fitment detail, e.g. engine or trim',
  PRIMARY KEY (product_id, vehicle_model_id),
  KEY idx_pvc_model (vehicle_model_id),
  CONSTRAINT fk_pvc_product FOREIGN KEY (product_id) REFERENCES products (id) ON DELETE CASCADE,
  CONSTRAINT fk_pvc_model FOREIGN KEY (vehicle_model_id) REFERENCES vehicle_models (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE product_reviews (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  product_id BIGINT UNSIGNED NOT NULL,
  user_id BIGINT UNSIGNED NOT NULL,
  rating TINYINT UNSIGNED NOT NULL,
  title VARCHAR(120) NOT NULL,
  body TEXT NOT NULL COMMENT 'Plain text; escaped on output',
  status ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  rejection_reason VARCHAR(255) NULL,
  is_verified_buyer TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Set by the server from delivered orders only',
  admin_reply TEXT NULL,
  admin_reply_visible TINYINT(1) NOT NULL DEFAULT 0,
  admin_reply_by BIGINT UNSIGNED NULL,
  admin_reply_at DATETIME NULL,
  moderated_by BIGINT UNSIGNED NULL,
  moderated_at DATETIME NULL,
  edited_at DATETIME NULL,
  ip VARCHAR(45) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_review_once (product_id, user_id),
  KEY idx_reviews_product (product_id, status, created_at),
  KEY idx_reviews_status (status, created_at),
  KEY idx_reviews_user (user_id),
  CONSTRAINT fk_reviews_product FOREIGN KEY (product_id) REFERENCES products (id) ON DELETE CASCADE,
  CONSTRAINT fk_reviews_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE,
  CONSTRAINT fk_reviews_reply_admin FOREIGN KEY (admin_reply_by) REFERENCES admin_users (id) ON DELETE SET NULL,
  CONSTRAINT fk_reviews_mod_admin FOREIGN KEY (moderated_by) REFERENCES admin_users (id) ON DELETE SET NULL,
  CONSTRAINT chk_reviews_rating CHECK (rating BETWEEN 1 AND 5)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE product_review_images (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  review_id BIGINT UNSIGNED NOT NULL,
  path VARCHAR(255) NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_rimages_review (review_id),
  CONSTRAINT fk_rimages_review FOREIGN KEY (review_id) REFERENCES product_reviews (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE product_questions (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  product_id BIGINT UNSIGNED NOT NULL,
  user_id BIGINT UNSIGNED NULL,
  question TEXT NOT NULL,
  answer TEXT NULL,
  status ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  answered_by BIGINT UNSIGNED NULL,
  answered_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_questions_product (product_id, status),
  CONSTRAINT fk_questions_product FOREIGN KEY (product_id) REFERENCES products (id) ON DELETE CASCADE,
  CONSTRAINT fk_questions_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE SET NULL,
  CONSTRAINT fk_questions_admin FOREIGN KEY (answered_by) REFERENCES admin_users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Global FAQs (product_id NULL) or product FAQs.
CREATE TABLE faqs (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  product_id BIGINT UNSIGNED NULL,
  question VARCHAR(255) NOT NULL,
  answer TEXT NOT NULL,
  sort_order INT NOT NULL DEFAULT 0,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_faqs_product (product_id, sort_order),
  CONSTRAINT fk_faqs_product FOREIGN KEY (product_id) REFERENCES products (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Coupons
-- ---------------------------------------------------------------------
CREATE TABLE coupons (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  code VARCHAR(40) NOT NULL,
  description VARCHAR(255) NULL,
  type ENUM('percent','fixed') NOT NULL,
  value DECIMAL(14,2) NOT NULL,
  min_order_amount DECIMAL(14,2) NOT NULL DEFAULT 0,
  max_discount_amount DECIMAL(14,2) NULL,
  starts_at DATETIME NULL,
  ends_at DATETIME NULL,
  usage_limit INT UNSIGNED NULL,
  per_user_limit INT UNSIGNED NULL,
  used_count INT UNSIGNED NOT NULL DEFAULT 0,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_coupons_code (code),
  CONSTRAINT chk_coupons_value CHECK (value > 0 AND (type <> 'percent' OR value <= 100))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE coupon_products (
  coupon_id BIGINT UNSIGNED NOT NULL,
  product_id BIGINT UNSIGNED NOT NULL,
  PRIMARY KEY (coupon_id, product_id),
  CONSTRAINT fk_cprod_coupon FOREIGN KEY (coupon_id) REFERENCES coupons (id) ON DELETE CASCADE,
  CONSTRAINT fk_cprod_product FOREIGN KEY (product_id) REFERENCES products (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE coupon_categories (
  coupon_id BIGINT UNSIGNED NOT NULL,
  category_id BIGINT UNSIGNED NOT NULL,
  PRIMARY KEY (coupon_id, category_id),
  CONSTRAINT fk_ccat_coupon FOREIGN KEY (coupon_id) REFERENCES coupons (id) ON DELETE CASCADE,
  CONSTRAINT fk_ccat_category FOREIGN KEY (category_id) REFERENCES categories (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Carts, wishlists, orders, payments, shipments
-- ---------------------------------------------------------------------
CREATE TABLE carts (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id BIGINT UNSIGNED NULL,
  token_hash CHAR(64) NULL COMMENT 'SHA-256 of the guest cart cookie',
  coupon_id BIGINT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_carts_user (user_id),
  UNIQUE KEY uq_carts_token (token_hash),
  CONSTRAINT fk_carts_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE,
  CONSTRAINT fk_carts_coupon FOREIGN KEY (coupon_id) REFERENCES coupons (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE cart_items (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  cart_id BIGINT UNSIGNED NOT NULL,
  product_id BIGINT UNSIGNED NOT NULL,
  variant_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
  quantity INT UNSIGNED NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_cart_line (cart_id, product_id, variant_id),
  KEY idx_citems_product (product_id),
  CONSTRAINT fk_citems_cart FOREIGN KEY (cart_id) REFERENCES carts (id) ON DELETE CASCADE,
  CONSTRAINT fk_citems_product FOREIGN KEY (product_id) REFERENCES products (id) ON DELETE CASCADE,
  CONSTRAINT chk_citems_qty CHECK (quantity > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE wishlists (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id BIGINT UNSIGNED NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_wishlists_user (user_id),
  CONSTRAINT fk_wishlists_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE wishlist_items (
  wishlist_id BIGINT UNSIGNED NOT NULL,
  product_id BIGINT UNSIGNED NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (wishlist_id, product_id),
  KEY idx_witems_product (product_id),
  CONSTRAINT fk_witems_wishlist FOREIGN KEY (wishlist_id) REFERENCES wishlists (id) ON DELETE CASCADE,
  CONSTRAINT fk_witems_product FOREIGN KEY (product_id) REFERENCES products (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE orders (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  order_number VARCHAR(20) NOT NULL,
  user_id BIGINT UNSIGNED NULL,
  status ENUM('pending_payment','paid','processing','ready_to_ship','shipped','delivered','cancelled','refunded') NOT NULL DEFAULT 'pending_payment',
  payment_status ENUM('unpaid','paid','failed','refunded') NOT NULL DEFAULT 'unpaid',
  subtotal DECIMAL(14,2) NOT NULL,
  discount_total DECIMAL(14,2) NOT NULL DEFAULT 0,
  shipping_total DECIMAL(14,2) NOT NULL DEFAULT 0,
  grand_total DECIMAL(14,2) NOT NULL,
  coupon_id BIGINT UNSIGNED NULL,
  coupon_code VARCHAR(40) NULL,
  customer_name VARCHAR(120) NOT NULL,
  customer_mobile VARCHAR(11) NOT NULL,
  customer_email VARCHAR(190) NULL,
  customer_note VARCHAR(500) NULL,
  admin_note TEXT NULL,
  idempotency_key CHAR(64) NULL,
  placed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_orders_number (order_number),
  UNIQUE KEY uq_orders_idempotency (idempotency_key),
  KEY idx_orders_user (user_id, placed_at),
  KEY idx_orders_status (status, placed_at),
  KEY idx_orders_placed (placed_at),
  CONSTRAINT fk_orders_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE SET NULL,
  CONSTRAINT fk_orders_coupon FOREIGN KEY (coupon_id) REFERENCES coupons (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE order_items (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  order_id BIGINT UNSIGNED NOT NULL,
  product_id BIGINT UNSIGNED NULL,
  variant_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
  product_name VARCHAR(190) NOT NULL,
  variant_name VARCHAR(120) NULL,
  sku VARCHAR(64) NULL,
  unit_price DECIMAL(14,2) NOT NULL,
  original_price DECIMAL(14,2) NOT NULL,
  quantity INT UNSIGNED NOT NULL,
  line_total DECIMAL(14,2) NOT NULL,
  PRIMARY KEY (id),
  KEY idx_oitems_order (order_id),
  KEY idx_oitems_product (product_id),
  CONSTRAINT fk_oitems_order FOREIGN KEY (order_id) REFERENCES orders (id) ON DELETE CASCADE,
  CONSTRAINT fk_oitems_product FOREIGN KEY (product_id) REFERENCES products (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE order_addresses (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  order_id BIGINT UNSIGNED NOT NULL,
  receiver_name VARCHAR(120) NOT NULL,
  receiver_mobile VARCHAR(11) NOT NULL,
  province VARCHAR(60) NOT NULL,
  city VARCHAR(60) NOT NULL,
  postal_code VARCHAR(10) NULL,
  address_line VARCHAR(500) NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_oaddress_order (order_id),
  CONSTRAINT fk_oaddress_order FOREIGN KEY (order_id) REFERENCES orders (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE order_status_history (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  order_id BIGINT UNSIGNED NOT NULL,
  from_status VARCHAR(30) NULL,
  to_status VARCHAR(30) NOT NULL,
  note VARCHAR(500) NULL,
  admin_user_id BIGINT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_osh_order (order_id, created_at),
  CONSTRAINT fk_osh_order FOREIGN KEY (order_id) REFERENCES orders (id) ON DELETE CASCADE,
  CONSTRAINT fk_osh_admin FOREIGN KEY (admin_user_id) REFERENCES admin_users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE payments (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  order_id BIGINT UNSIGNED NOT NULL,
  gateway VARCHAR(40) NOT NULL,
  is_test TINYINT(1) NOT NULL DEFAULT 0,
  amount DECIMAL(14,2) NOT NULL,
  status ENUM('pending','succeeded','failed','refunded') NOT NULL DEFAULT 'pending',
  reference VARCHAR(100) NULL,
  paid_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_payments_order (order_id),
  CONSTRAINT fk_payments_order FOREIGN KEY (order_id) REFERENCES orders (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE payment_transactions (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  payment_id BIGINT UNSIGNED NOT NULL,
  type ENUM('request','verify','callback','refund') NOT NULL,
  status VARCHAR(30) NOT NULL,
  payload LONGTEXT NULL COMMENT 'Never store card data or secrets' CHECK (payload IS NULL OR JSON_VALID(payload)),
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_ptx_payment (payment_id),
  CONSTRAINT fk_ptx_payment FOREIGN KEY (payment_id) REFERENCES payments (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE shipments (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  order_id BIGINT UNSIGNED NOT NULL,
  carrier VARCHAR(60) NULL,
  tracking_code VARCHAR(80) NULL,
  status ENUM('pending','shipped','delivered','returned') NOT NULL DEFAULT 'pending',
  shipped_at DATETIME NULL,
  delivered_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_shipments_order (order_id),
  CONSTRAINT fk_shipments_order FOREIGN KEY (order_id) REFERENCES orders (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE coupon_usages (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  coupon_id BIGINT UNSIGNED NOT NULL,
  order_id BIGINT UNSIGNED NOT NULL,
  user_id BIGINT UNSIGNED NULL,
  customer_mobile VARCHAR(11) NULL,
  discount_amount DECIMAL(14,2) NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_cusage_order (coupon_id, order_id),
  KEY idx_cusage_user (coupon_id, user_id),
  KEY idx_cusage_mobile (coupon_id, customer_mobile),
  CONSTRAINT fk_cusage_coupon FOREIGN KEY (coupon_id) REFERENCES coupons (id) ON DELETE CASCADE,
  CONSTRAINT fk_cusage_order FOREIGN KEY (order_id) REFERENCES orders (id) ON DELETE CASCADE,
  CONSTRAINT fk_cusage_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Site content
-- ---------------------------------------------------------------------
CREATE TABLE banners (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  placement ENUM('hero','promo','category','special_offer','product','blog','mobile') NOT NULL,
  title VARCHAR(160) NOT NULL,
  eyebrow VARCHAR(80) NULL,
  description VARCHAR(255) NULL,
  button_text VARCHAR(60) NULL,
  button_url VARCHAR(500) NULL,
  image_desktop VARCHAR(255) NOT NULL,
  image_mobile VARCHAR(255) NULL,
  image_alt VARCHAR(255) NULL,
  bg_color VARCHAR(9) NULL,
  text_theme ENUM('light','dark') NOT NULL DEFAULT 'light',
  sort_order INT NOT NULL DEFAULT 0,
  starts_at DATETIME NULL,
  ends_at DATETIME NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_banners_live (placement, is_active, sort_order),
  CONSTRAINT chk_banners_window CHECK (ends_at IS NULL OR starts_at IS NULL OR ends_at > starts_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE homepage_sections (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  type ENUM('hero_slider','quick_categories','amazing','promo_banners','visual_categories','ranked_products',
            'brands','product_grid','product_slider','compact_grid','mosaic','tabbed_products','discounted',
            'vehicle_selector','big_banner','two_banners','text','blog_posts','benefits','testimonials','newsletter') NOT NULL,
  title VARCHAR(160) NULL,
  subtitle VARCHAR(255) NULL,
  bg_color VARCHAR(9) NULL,
  settings LONGTEXT NULL COMMENT 'limit, source, category_id, banner_placement, link, body ...' CHECK (settings IS NULL OR JSON_VALID(settings)),
  sort_order INT NOT NULL DEFAULT 0,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_hsections_order (is_active, sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE homepage_section_items (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  section_id BIGINT UNSIGNED NOT NULL,
  item_type ENUM('product','category','banner','brand','post','testimonial') NOT NULL,
  item_id BIGINT UNSIGNED NULL,
  payload LONGTEXT NULL COMMENT 'Free data, e.g. testimonial text' CHECK (payload IS NULL OR JSON_VALID(payload)),
  sort_order INT NOT NULL DEFAULT 0,
  PRIMARY KEY (id),
  KEY idx_hitems_section (section_id, sort_order),
  CONSTRAINT fk_hitems_section FOREIGN KEY (section_id) REFERENCES homepage_sections (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE pages (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  title VARCHAR(190) NOT NULL,
  slug VARCHAR(190) NOT NULL,
  excerpt VARCHAR(500) NULL,
  content MEDIUMTEXT NULL COMMENT 'Sanitised HTML',
  featured_image VARCHAR(255) NULL,
  template ENUM('default','contact','faq') NOT NULL DEFAULT 'default',
  status ENUM('draft','published') NOT NULL DEFAULT 'draft',
  published_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_pages_slug (slug),
  KEY idx_pages_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE menus (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  location VARCHAR(40) NOT NULL COMMENT 'header, mobile, footer_guide, footer_service, footer_categories, footer_legal',
  name VARCHAR(120) NOT NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (id),
  UNIQUE KEY uq_menus_location (location)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE menu_items (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  menu_id BIGINT UNSIGNED NOT NULL,
  parent_id BIGINT UNSIGNED NULL,
  title VARCHAR(120) NOT NULL,
  url VARCHAR(500) NOT NULL,
  image_path VARCHAR(255) NULL COMMENT 'Mega-menu promo image',
  is_highlight TINYINT(1) NOT NULL DEFAULT 0,
  sort_order INT NOT NULL DEFAULT 0,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (id),
  KEY idx_mitems_menu (menu_id, parent_id, sort_order),
  CONSTRAINT fk_mitems_menu FOREIGN KEY (menu_id) REFERENCES menus (id) ON DELETE CASCADE,
  CONSTRAINT fk_mitems_parent FOREIGN KEY (parent_id) REFERENCES menu_items (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE site_settings (
  setting_key VARCHAR(80) NOT NULL,
  value TEXT NULL,
  type ENUM('string','text','int','bool','json','image','color') NOT NULL DEFAULT 'string',
  group_name VARCHAR(40) NOT NULL DEFAULT 'general',
  label VARCHAR(120) NOT NULL,
  is_public TINYINT(1) NOT NULL DEFAULT 0,
  sort_order INT NOT NULL DEFAULT 0,
  updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (setting_key),
  KEY idx_settings_group (group_name, sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE redirects (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  source_path VARCHAR(255) NOT NULL COMMENT 'Path relative to the site root, starting with /',
  target_url VARCHAR(500) NOT NULL,
  status_code SMALLINT UNSIGNED NOT NULL DEFAULT 301,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  is_auto TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Created automatically after a slug change',
  hits INT UNSIGNED NOT NULL DEFAULT 0,
  last_hit_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_redirects_source (source_path),
  CONSTRAINT chk_redirects_code CHECK (status_code IN (301,302))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Blog
-- ---------------------------------------------------------------------
CREATE TABLE blog_authors (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  name VARCHAR(120) NOT NULL,
  slug VARCHAR(160) NOT NULL,
  avatar_path VARCHAR(255) NULL,
  bio TEXT NULL,
  expertise VARCHAR(190) NULL,
  admin_user_id BIGINT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_bauthors_slug (slug),
  CONSTRAINT fk_bauthors_admin FOREIGN KEY (admin_user_id) REFERENCES admin_users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE blog_categories (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  parent_id BIGINT UNSIGNED NULL,
  name VARCHAR(120) NOT NULL,
  slug VARCHAR(160) NOT NULL,
  description TEXT NULL,
  image_path VARCHAR(255) NULL,
  sort_order INT NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_bcategories_slug (slug),
  KEY idx_bcategories_parent (parent_id),
  CONSTRAINT fk_bcategories_parent FOREIGN KEY (parent_id) REFERENCES blog_categories (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE blog_tags (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  name VARCHAR(80) NOT NULL,
  slug VARCHAR(120) NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_btags_slug (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE blog_posts (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  author_id BIGINT UNSIGNED NULL,
  title VARCHAR(190) NOT NULL,
  subtitle VARCHAR(255) NULL,
  slug VARCHAR(190) NOT NULL,
  excerpt VARCHAR(500) NULL,
  content MEDIUMTEXT NULL COMMENT 'Sanitised HTML',
  featured_image VARCHAR(255) NULL,
  featured_image_alt VARCHAR(255) NULL,
  featured_image_caption VARCHAR(255) NULL,
  status ENUM('draft','scheduled','published','unpublished') NOT NULL DEFAULT 'draft',
  is_featured TINYINT(1) NOT NULL DEFAULT 0,
  is_pillar TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Cornerstone content',
  allow_comments TINYINT(1) NOT NULL DEFAULT 1,
  reading_minutes SMALLINT UNSIGNED NOT NULL DEFAULT 1,
  view_count INT UNSIGNED NOT NULL DEFAULT 0,
  focus_keyword VARCHAR(120) NULL,
  related_keywords VARCHAR(255) NULL,
  published_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_bposts_slug (slug),
  KEY idx_bposts_live (status, published_at),
  KEY idx_bposts_author (author_id),
  CONSTRAINT fk_bposts_author FOREIGN KEY (author_id) REFERENCES blog_authors (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE blog_post_categories (
  post_id BIGINT UNSIGNED NOT NULL,
  category_id BIGINT UNSIGNED NOT NULL,
  PRIMARY KEY (post_id, category_id),
  KEY idx_bpc_category (category_id),
  CONSTRAINT fk_bpc_post FOREIGN KEY (post_id) REFERENCES blog_posts (id) ON DELETE CASCADE,
  CONSTRAINT fk_bpc_category FOREIGN KEY (category_id) REFERENCES blog_categories (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE blog_post_tags (
  post_id BIGINT UNSIGNED NOT NULL,
  tag_id BIGINT UNSIGNED NOT NULL,
  PRIMARY KEY (post_id, tag_id),
  KEY idx_bpt_tag (tag_id),
  CONSTRAINT fk_bpt_post FOREIGN KEY (post_id) REFERENCES blog_posts (id) ON DELETE CASCADE,
  CONSTRAINT fk_bpt_tag FOREIGN KEY (tag_id) REFERENCES blog_tags (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE blog_post_relations (
  post_id BIGINT UNSIGNED NOT NULL,
  related_post_id BIGINT UNSIGNED NOT NULL,
  PRIMARY KEY (post_id, related_post_id),
  CONSTRAINT fk_bprel_post FOREIGN KEY (post_id) REFERENCES blog_posts (id) ON DELETE CASCADE,
  CONSTRAINT fk_bprel_related FOREIGN KEY (related_post_id) REFERENCES blog_posts (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE blog_post_products (
  post_id BIGINT UNSIGNED NOT NULL,
  product_id BIGINT UNSIGNED NOT NULL,
  PRIMARY KEY (post_id, product_id),
  CONSTRAINT fk_bpprod_post FOREIGN KEY (post_id) REFERENCES blog_posts (id) ON DELETE CASCADE,
  CONSTRAINT fk_bpprod_product FOREIGN KEY (product_id) REFERENCES products (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE blog_comments (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  post_id BIGINT UNSIGNED NOT NULL,
  parent_id BIGINT UNSIGNED NULL,
  user_id BIGINT UNSIGNED NULL,
  admin_user_id BIGINT UNSIGNED NULL COMMENT 'Set when the comment is an official reply',
  author_name VARCHAR(120) NOT NULL,
  author_email VARCHAR(190) NULL,
  body TEXT NOT NULL,
  status ENUM('pending','approved','rejected','spam') NOT NULL DEFAULT 'pending',
  report_count INT UNSIGNED NOT NULL DEFAULT 0,
  ip VARCHAR(45) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_bcomments_post (post_id, status, created_at),
  KEY idx_bcomments_status (status, created_at),
  CONSTRAINT fk_bcomments_post FOREIGN KEY (post_id) REFERENCES blog_posts (id) ON DELETE CASCADE,
  CONSTRAINT fk_bcomments_parent FOREIGN KEY (parent_id) REFERENCES blog_comments (id) ON DELETE CASCADE,
  CONSTRAINT fk_bcomments_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE SET NULL,
  CONSTRAINT fk_bcomments_admin FOREIGN KEY (admin_user_id) REFERENCES admin_users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE blog_post_revisions (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  post_id BIGINT UNSIGNED NOT NULL,
  title VARCHAR(190) NOT NULL,
  excerpt VARCHAR(500) NULL,
  content MEDIUMTEXT NULL,
  admin_user_id BIGINT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_brev_post (post_id, created_at),
  CONSTRAINT fk_brev_post FOREIGN KEY (post_id) REFERENCES blog_posts (id) ON DELETE CASCADE,
  CONSTRAINT fk_brev_admin FOREIGN KEY (admin_user_id) REFERENCES admin_users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- SEO
-- ---------------------------------------------------------------------
-- One row per content item (entity_type + entity_id) or per static route (route_key).
CREATE TABLE seo_metadata (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  entity_type ENUM('product','category','brand','page','post','blog_category','blog_tag','author','static') NOT NULL,
  entity_id BIGINT UNSIGNED NULL,
  route_key VARCHAR(60) NULL COMMENT 'For static routes: home, products, blog ...',
  seo_title VARCHAR(190) NULL,
  meta_description VARCHAR(320) NULL,
  canonical_url VARCHAR(500) NULL,
  meta_robots VARCHAR(60) NULL,
  is_indexable TINYINT(1) NOT NULL DEFAULT 1,
  og_title VARCHAR(190) NULL,
  og_description VARCHAR(320) NULL,
  og_image VARCHAR(255) NULL,
  twitter_title VARCHAR(190) NULL,
  twitter_description VARCHAR(320) NULL,
  twitter_image VARCHAR(255) NULL,
  updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_seo_entity (entity_type, entity_id),
  UNIQUE KEY uq_seo_route (route_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE sitemap_settings (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  section VARCHAR(40) NOT NULL,
  is_enabled TINYINT(1) NOT NULL DEFAULT 1,
  changefreq ENUM('always','hourly','daily','weekly','monthly','yearly','never') NOT NULL DEFAULT 'weekly',
  priority DECIMAL(2,1) NOT NULL DEFAULT 0.5,
  PRIMARY KEY (id),
  UNIQUE KEY uq_sitemap_section (section)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Customer communication
-- ---------------------------------------------------------------------
CREATE TABLE contact_messages (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  name VARCHAR(120) NOT NULL,
  contact VARCHAR(190) NOT NULL,
  subject VARCHAR(120) NOT NULL,
  message TEXT NOT NULL,
  status ENUM('new','read','archived') NOT NULL DEFAULT 'new',
  ip VARCHAR(45) NULL,
  read_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_cmessages_status (status, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE newsletter_subscribers (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  email VARCHAR(190) NOT NULL,
  status ENUM('subscribed','unsubscribed') NOT NULL DEFAULT 'subscribed',
  ip VARCHAR(45) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_newsletter_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE notifications (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  recipient_type ENUM('admin','user') NOT NULL,
  recipient_id BIGINT UNSIGNED NULL COMMENT 'NULL = every admin who can see the linked section',
  type VARCHAR(50) NOT NULL,
  title VARCHAR(190) NOT NULL,
  body VARCHAR(500) NULL,
  link VARCHAR(255) NULL,
  read_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_notifications_recipient (recipient_type, recipient_id, read_at, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;
