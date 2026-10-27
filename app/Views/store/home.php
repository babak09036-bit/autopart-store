<main id="main">
  <h1 class="visually-hidden"><?= e(\App\Services\SettingsService::get('store_name')) ?>؛ <?= e(\App\Services\SettingsService::get('store_tagline')) ?></h1>
  <!-- Sections, their order and content are managed in Admin › مدیریت محتوای سایت › صفحه اصلی -->
  <div id="home-sections" aria-busy="true"></div>
  <script>window.AP_HOME = <?= json_out(\App\Services\ContentService::homepage()) ?>;</script>
</main>
