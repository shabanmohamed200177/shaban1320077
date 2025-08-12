<?php
require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'middleware.php';
require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'error_handler.php';

SecurityMiddleware::checkSession();

// إذا كان المندوب مسجلاً بالفعل، اعد توجيهه للوحة التحكم
if (isset($_SESSION['courier_id'])) {
    header('Location: dashboard.php');
    exit;
}

$csrf_token = SecurityMiddleware::generateCSRFToken();
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <?php echo csrf_meta(); ?>
  <title>تسجيل دخول المندوب</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@300;400;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css">
  <style>
    body { font-family: 'Cairo', sans-serif; background: #f5f7fb; }
    .login-container { min-height: 100vh; display: flex; align-items: center; justify-content: center; }
    .card { width: 100%; max-width: 420px; border: none; box-shadow: 0 10px 25px rgba(0,0,0,0.08); }
    .brand { font-weight: 700; font-size: 22px; color: #1f2937; }
    .btn-primary { background: #2563eb; border-color: #2563eb; }
    .btn-primary:disabled { opacity: .8; }
    .form-control { height: 48px; }
    .small-text { font-size: 13px; color: #6b7280; }
  </style>
  <script>
    // دعم بسيط لاكتشاف HTTPS لتعيين الكوكيز الآمن لاحقًا من الـ API
    window.__IS_HTTPS__ = (location.protocol === 'https:');
  </script>
  <script src="https://code.jquery.com/jquery-3.6.4.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>
</head>
<body>
  <div class="login-container">
    <div class="card">
      <div class="card-body p-4">
        <div class="mb-3 text-center">
          <div class="brand">لوحة المندوب - تسجيل الدخول</div>
          <div class="small-text mt-1">يرجى إدخال رقم الجوال أو البريد وكلمة المرور</div>
        </div>

        <div id="alert-box" class="alert d-none" role="alert"></div>

        <form id="courier-login-form" method="post" action="api/login.php" novalidate>
          <?php echo csrf_input(); ?>

          <div class="form-group">
            <label for="identifier">رقم الجوال أو البريد الإلكتروني</label>
            <input type="text" class="form-control" id="identifier" name="identifier" required placeholder="مثال: 010xxxxxxx أو name@example.com" />
          </div>

          <div class="form-group">
            <label for="password">كلمة المرور</label>
            <input type="password" class="form-control" id="password" name="password" required />
          </div>

          <div class="form-group form-check">
            <input type="checkbox" class="form-check-input" id="remember_me" name="remember_me" value="1" />
            <label class="form-check-label" for="remember_me">تذكرني</label>
          </div>

          <button type="submit" class="btn btn-primary btn-block" id="login-btn">
            <span class="spinner-border spinner-border-sm d-none" id="btn-spinner" role="status" aria-hidden="true"></span>
            <span id="btn-text">تسجيل الدخول</span>
          </button>
        </form>

        <div class="mt-3 small-text text-center">
          إذا واجهت مشكلة، تواصل مع الإدارة.
        </div>
      </div>
    </div>
  </div>

  <script>
    function showAlert(type, message) {
      var box = $('#alert-box');
      box.removeClass('d-none alert-success alert-danger alert-warning').addClass('alert-' + type).text(message);
    }

    $('#courier-login-form').on('submit', function(e){
      e.preventDefault();
      var $btn = $('#login-btn');
      var $spinner = $('#btn-spinner');
      var $text = $('#btn-text');
      $btn.prop('disabled', true);
      $spinner.removeClass('d-none');
      $text.text('جارِ التحقق...');

      var formData = $(this).serializeArray();
      // أضف تلميح أننا نرغب بـ JSON
      formData.push({name: 'return_json', value: '1'});

      $.ajax({
        url: 'api/login.php',
        method: 'POST',
        data: $.param(formData),
        dataType: 'json',
        success: function(resp){
          if (resp && (resp.success === true || resp.status === 1)) {
            var redirect = resp.redirect || 'dashboard.php';
            window.location.href = redirect;
          } else {
            var msg = (resp && (resp.message || resp.error)) || 'بيانات الدخول غير صحيحة.';
            showAlert('danger', msg);
          }
        },
        error: function(xhr){
          var msg = 'فشل الاتصال بالخادم';
          if (xhr && xhr.responseText) {
            try { var j = JSON.parse(xhr.responseText); msg = j.message || j.error || msg; } catch(_){}
          }
          showAlert('danger', msg);
        },
        complete: function(){
          $btn.prop('disabled', false);
          $spinner.addClass('d-none');
          $text.text('تسجيل الدخول');
        }
      });
    });
  </script>
</body>
</html>





