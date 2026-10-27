<main id="main" class="container">
  <div class="auth auth--narrow">
    <div class="auth__main">
      <h1>تعیین رمز عبور جدید</h1>
      <div class="alert" id="auth-alert" role="alert" hidden></div>
      <form id="reset-form" novalidate>
        <input type="hidden" name="token" value="<?= e($token) ?>">
        <div class="field"><label for="reset-password">رمز عبور جدید</label><input class="input" id="reset-password" name="password" type="password" dir="ltr" autocomplete="new-password"><p class="field__error" data-error-for="password"></p></div>
        <div class="field"><label for="reset-confirm">تکرار رمز عبور</label><input class="input" id="reset-confirm" name="password_confirmation" type="password" dir="ltr" autocomplete="new-password"><p class="field__error" data-error-for="password_confirmation"></p></div>
        <button type="submit" class="btn btn--primary btn--block">ذخیره رمز عبور</button>
      </form>
    </div>
  </div>
</main>
