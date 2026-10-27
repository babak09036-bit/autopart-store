# نمودار متنی روابط جدول‌ها

۶۹ جدول InnoDB با کدگذاری `utf8mb4`. فلش `←` یعنی کلید خارجی (FK). زمان‌ها به UTC ذخیره و در پنل به وقت تهران نمایش داده می‌شوند. مبلغ‌ها `DECIMAL(14,2)` به تومان هستند.

## کاتالوگ

```
brands ─────────────┐
                    ▼
categories ◄── product_categories ──► products ◄── product_images
  ▲ (parent_id)                         │  ▲
  └── categories                        │  ├── product_variants ◄─┐
                                        │  ├── inventory (product_id + variant_id، صفر = سطح محصول)
                                        │  ├── inventory_movements ──► admin_users
                                        │  ├── product_attribute_values ──► product_attributes
                                        │  ├── product_relations (related_product_id ──► products؛ نوع: related / complementary)
                                        │  ├── product_vehicle_compat ──► vehicle_models ──► vehicle_brands
                                        │  ├── product_reviews ──► users ؛ ◄── product_review_images
                                        │  ├── product_questions ──► users
                                        │  └── faqs (product_id خالی = سؤال عمومی فروشگاه)
```

## مشتری، سبد خرید و سفارش

```
users ◄── user_addresses
  ▲   ◄── password_reset_tokens (فقط هش توکن ذخیره می‌شود)
  │   ◄── wishlists ◄── wishlist_items ──► products
  │   ◄── carts (user_id یا token_hash مهمان) ◄── cart_items ──► products
  │                └──► coupons
  └── orders (idempotency_key یکتا) ──► coupons
        ◄── order_items ──► products   (نام، قیمت و SKU در لحظه خرید کپی می‌شود)
        ◄── order_addresses
        ◄── order_status_history ──► admin_users
        ◄── payments ◄── payment_transactions
        ◄── shipments
        ◄── coupon_usages ──► coupons, users

coupons ◄── coupon_products ──► products
        ◄── coupon_categories ──► categories
```

## محتوا و سئو

```
banners                     (placement + بازه نمایش)
homepage_sections ◄── homepage_section_items (item_type + item_id یا payload JSON)
menus ◄── menu_items (parent_id ──► menu_items، یک سطح زیرمنو)
pages
site_settings               (key / value / type)
redirects                   (source_path یکتا، ۳۰۱ / ۳۰۲)
seo_metadata                (entity_type + entity_id یکتا، یا route_key برای صفحات ثابت)
sitemap_settings
contact_messages, newsletter_subscribers, notifications
media_files ──► admin_users (uploaded_by)
```

## وبلاگ

```
blog_authors ──► admin_users (اختیاری)
blog_posts ──► blog_authors
  ◄── blog_post_categories ──► blog_categories (parent_id ──► blog_categories)
  ◄── blog_post_tags ──► blog_tags
  ◄── blog_post_relations (related_post_id ──► blog_posts)
  ◄── blog_post_products ──► products
  ◄── blog_post_revisions ──► admin_users
  ◄── blog_comments (parent_id ──► blog_comments؛ user_id ──► users؛ admin_user_id ──► admin_users)
```

## مدیران و دسترسی‌ها

```
admin_users ◄── admin_user_roles ──► roles ◄── role_permissions ──► permissions
            ◄── admin_activity_logs
audit_logs, login_attempts, rate_limits   (بدون FK؛ برای ثبت رویداد و محدودسازی درخواست)
```

قواعد حذف: سفارش‌ها و اقلام آن‌ها هرگز با حذف محصول از بین نمی‌روند. محصولی که در سفارش آمده به‌جای حذف بایگانی می‌شود، و برند یا دسته‌ای که محصول دارد حذف نمی‌شود.
