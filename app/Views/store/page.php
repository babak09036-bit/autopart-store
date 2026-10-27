<?php
/** CMS page (default / contact / faq templates). @var array $page @var array $faqs */
$settings = \App\Services\SettingsService::all(true);
$crumbs = [['name' => $page['title'], 'url' => null]];
?>
<main id="main" class="container">
  <?php require __DIR__ . '/partials/breadcrumbs.php'; ?>
  <h1 class="page-title"><?= e($page['title']) ?></h1>

<?php if ($page['template'] === 'contact'): ?>
  <div class="contact">
    <aside class="contact__info" aria-label="اطلاعات تماس">
      <div class="contact__item"><?= icon('phone') ?><div><strong>تلفن پشتیبانی</strong><a href="tel:<?= e($settings['phone_link'] ?? '') ?>" dir="ltr"><?= e($settings['phone'] ?? '') ?></a></div></div>
      <div class="contact__item"><?= icon('mail') ?><div><strong>ایمیل</strong><a href="mailto:<?= e($settings['email'] ?? '') ?>" dir="ltr"><?= e($settings['email'] ?? '') ?></a></div></div>
      <div class="contact__item"><?= icon('location') ?><div><strong>نشانی</strong><span><?= e($settings['address'] ?? '') ?></span></div></div>
      <div class="contact__item"><?= icon('clock') ?><div><strong>ساعات پاسخگویی</strong><span><?= e($settings['support_hours'] ?? '') ?></span></div></div>
      <div class="contact__map"><img src="assets/images/ui/map.svg" alt="نقشه نمایشی موقعیت دفتر" width="800" height="400" loading="lazy"></div>
    </aside>
    <div>
      <?php if ($page['content']): ?><div class="rich-text card-surface page-body"><?= $page['content'] ?></div><?php endif; ?>
      <form class="contact__form" id="contact-form" novalidate aria-labelledby="contact-form-title">
        <h2 id="contact-form-title" class="field--full">ارسال پیام به پشتیبانی</h2>
        <div class="alert" id="contact-alert" role="alert" hidden></div>
        <div class="field"><label for="c-name">نام و نام خانوادگی</label><input class="input" id="c-name" name="name" type="text" autocomplete="name"><p class="field__error" data-error-for="name"></p></div>
        <div class="field"><label for="c-contact">موبایل یا ایمیل</label><input class="input" id="c-contact" name="contact" type="text" dir="ltr" autocomplete="email"><p class="field__error" data-error-for="contact"></p></div>
        <div class="field field--full">
          <label for="c-subject">موضوع</label>
          <select class="select" id="c-subject" name="subject">
            <option value="">یک موضوع انتخاب کنید</option>
            <option>مشاوره پیش از خرید</option><option>پیگیری سفارش</option><option>بازگشت کالا</option><option>پیشنهاد یا انتقاد</option>
          </select>
          <p class="field__error" data-error-for="subject"></p>
        </div>
        <div class="field field--full"><label for="c-message">متن پیام</label><textarea class="textarea" id="c-message" name="message" rows="5"></textarea><p class="field__hint">حداقل ۱۰ کاراکتر</p><p class="field__error" data-error-for="message"></p></div>
        <div class="hp-field" aria-hidden="true"><label for="c-website">وب‌سایت</label><input id="c-website" name="website" type="text" tabindex="-1" autocomplete="off"></div>
        <button type="submit" class="btn btn--primary">ارسال پیام</button>
      </form>
    </div>
  </div>
<?php else: ?>
  <article class="page-article">
    <?php if ($page['featured_image']): ?><img class="page-article__image" src="<?= e($page['featured_image']) ?>" alt="" width="1200" height="400"><?php endif; ?>
    <div class="rich-text card-surface page-body"><?= $page['content'] ?></div>
    <?php if ($faqs): ?>
    <section class="faq card-surface page-body" aria-label="پرسش‌های متداول">
      <?php foreach ($faqs as $f): ?><details><summary><?= e($f['question']) ?></summary><p><?= e($f['answer']) ?></p></details><?php endforeach; ?>
    </section>
    <?php endif; ?>
    <?php if ($page['updated_at'] || $page['published_at']): ?><p class="field__hint">آخرین به‌روزرسانی: <?= e(fa_date($page['updated_at'] ?: $page['published_at'])) ?></p><?php endif; ?>
  </article>
<?php endif; ?>
</main>
