<main id="main" class="container">
  <div class="auth auth--narrow">
    <div class="auth__main">
      <h1>بازیابی رمز عبور</h1>
      <p class="field__hint">ایمیل ثبت‌شده در حساب خود را وارد کنید تا پیوند تعیین رمز جدید برایتان ارسال شود.</p>
      <div class="alert" id="auth-alert" role="alert" hidden></div>
      <form id="forgot-form" novalidate>
        <div class="field"><label for="forgot-email">ایمیل</label><input class="input" id="forgot-email" name="email" type="email" dir="ltr" autocomplete="email"><p class="field__error" data-error-for="email"></p></div>
        <button type="submit" class="btn btn--primary btn--block">ارسال پیوند بازیابی</button>
        <a class="link-more" href="login">بازگشت به ورود</a>
      </form>
    </div>
  </div>
</main>
