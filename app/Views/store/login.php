<main id="main" class="container">
  <div class="auth">
    <div class="auth__aside">
      <h1>به <?= e(\App\Services\SettingsService::get('store_name')) ?> خوش آمدید</h1>
      <p>با ورود به حساب کاربری، سفارش‌های خود را پیگیری کنید، علاقه‌مندی‌ها را نگه دارید و برای محصولات نظر ثبت کنید.</p>
      <img src="assets/images/banners/guide-travel.svg" alt="" width="640" height="360" loading="lazy">
    </div>
    <div class="auth__main" id="auth-main">
      <div class="auth__tabs" role="tablist" aria-label="ورود یا ثبت‌نام">
        <button type="button" role="tab" id="tab-login" aria-controls="login-form" aria-selected="true">ورود</button>
        <button type="button" role="tab" id="tab-register" aria-controls="register-form" aria-selected="false" tabindex="-1">ثبت‌نام</button>
      </div>
      <div class="alert" id="auth-alert" role="alert" hidden></div>
      <form id="login-form" role="tabpanel" aria-labelledby="tab-login" novalidate>
        <div class="field">
          <label for="login-mobile">شماره موبایل یا ایمیل</label>
          <input class="input" id="login-mobile" name="mobile" type="text" dir="ltr" placeholder="09xxxxxxxxx" autocomplete="username">
          <p class="field__error" data-error-for="mobile"></p>
        </div>
        <div class="field">
          <label for="login-password">رمز عبور</label>
          <div class="password-field">
            <input class="input" id="login-password" name="password" type="password" dir="ltr" autocomplete="current-password">
            <button type="button" data-toggle-password aria-label="نمایش رمز عبور" aria-pressed="false"><?= icon('eye', 'icon--sm') ?></button>
          </div>
          <p class="field__error" data-error-for="password"></p>
        </div>
        <button type="submit" class="btn btn--primary btn--block">ورود به حساب</button>
        <a class="link-more" href="forgot-password">رمز عبور را فراموش کرده‌اید؟</a>
      </form>
      <form id="register-form" role="tabpanel" aria-labelledby="tab-register" novalidate hidden>
        <div class="field"><label for="reg-name">نام و نام خانوادگی</label><input class="input" id="reg-name" name="full_name" type="text" autocomplete="name"><p class="field__error" data-error-for="full_name"></p></div>
        <div class="field"><label for="reg-mobile">شماره موبایل</label><input class="input" id="reg-mobile" name="mobile" type="tel" inputmode="numeric" dir="ltr" placeholder="09xxxxxxxxx" autocomplete="tel"><p class="field__error" data-error-for="mobile"></p></div>
        <div class="field"><label for="reg-email">ایمیل (برای بازیابی رمز عبور)</label><input class="input" id="reg-email" name="email" type="email" dir="ltr" autocomplete="email"><p class="field__error" data-error-for="email"></p></div>
        <div class="field">
          <label for="reg-password">رمز عبور</label>
          <div class="password-field">
            <input class="input" id="reg-password" name="password" type="password" dir="ltr" autocomplete="new-password" aria-describedby="reg-password-hint">
            <button type="button" data-toggle-password aria-label="نمایش رمز عبور" aria-pressed="false"><?= icon('eye', 'icon--sm') ?></button>
          </div>
          <p class="field__hint" id="reg-password-hint">حداقل ۸ کاراکتر</p>
          <p class="field__error" data-error-for="password"></p>
        </div>
        <div class="field"><label for="reg-confirm">تکرار رمز عبور</label><input class="input" id="reg-confirm" name="password_confirmation" type="password" dir="ltr" autocomplete="new-password"><p class="field__error" data-error-for="password_confirmation"></p></div>
        <label class="check"><input type="checkbox" name="terms" value="1"><span><a href="pages/terms">قوانین و مقررات</a> و <a href="pages/privacy">حریم خصوصی</a> را می‌پذیرم.</span></label>
        <p class="field__error" data-error-for="terms"></p>
        <button type="submit" class="btn btn--primary btn--block">ایجاد حساب کاربری</button>
      </form>
    </div>
  </div>
</main>
