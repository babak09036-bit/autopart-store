-- Bulk price changes with undo history, and the permission that guards them.
-- Needed only for databases created before this feature; schema.sql and seed.sql already include it.
CREATE TABLE IF NOT EXISTS price_change_batches (
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

CREATE TABLE IF NOT EXISTS price_change_items (
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

INSERT IGNORE INTO permissions (id, slug, name, group_name) VALUES (34, 'pricing.bulk', 'تغییر گروهی قیمت', 'محصولات');
INSERT IGNORE INTO role_permissions (role_id, permission_id)
  SELECT r.id, 34 FROM roles r WHERE r.slug IN ('super_admin', 'product_manager');
