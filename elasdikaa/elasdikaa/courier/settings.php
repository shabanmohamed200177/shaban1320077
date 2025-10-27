<?php
require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'middleware.php';
require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'error_handler.php';
SecurityMiddleware::checkSession();
if (!isset($_SESSION['courier_id'])) { header('Location: login.php'); exit; }
$courierName = $_SESSION['courier_name'] ?? 'المندوب';
$pageTitle = 'الإعدادات';
include __DIR__.'/partials/layout_top.php';
?>
<div class="container py-4">
  <h5 class="mb-4">إعدادات المندوب</h5>
  
  <div class="row">
    <div class="col-md-6">
      <div class="card mb-3">
        <div class="card-header">
          <h6 class="mb-0"><i class="fas fa-bell me-2"></i>الإشعارات</h6>
        </div>
        <div class="card-body">
          <div class="form-check form-switch mb-3">
            <input class="form-check-input" type="checkbox" id="notifyNewShipments" checked>
            <label class="form-check-label" for="notifyNewShipments">
              إشعارات الشحنات الجديدة
            </label>
          </div>
          <div class="form-check form-switch mb-3">
            <input class="form-check-input" type="checkbox" id="notifyStatusChanges" checked>
            <label class="form-check-label" for="notifyStatusChanges">
              إشعارات تغيير الحالة
            </label>
          </div>
          <div class="form-check form-switch">
            <input class="form-check-input" type="checkbox" id="notifyReminders">
            <label class="form-check-label" for="notifyReminders">
              تذكيرات الشحنات المؤجلة
            </label>
          </div>
                     <div class="mt-3">
             <button type="button" class="btn btn-sm btn-outline-primary" id="testSound">
               <i class="fas fa-volume-up"></i> اختبار الصوت
             </button>
             <div class="mt-2">
               <small class="text-muted">
                 <i class="fas fa-info-circle"></i> 
                 الصوت الحالي: <span id="currentSound">مدمج (بيب)</span>
               </small>
             </div>
           </div>
        </div>
      </div>
    </div>
    
    <div class="col-md-6">
      <div class="card mb-3">
        <div class="card-header">
          <h6 class="mb-0"><i class="fas fa-palette me-2"></i>المظهر</h6>
        </div>
        <div class="card-body">
          <div class="form-check form-switch mb-3">
            <input class="form-check-input" type="checkbox" id="darkMode">
            <label class="form-check-label" for="darkMode">
              الوضع الليلي
            </label>
          </div>
          <div class="mb-3">
            <label class="form-label">حجم الخط</label>
            <select class="form-select" id="fontSize">
              <option value="small">صغير</option>
              <option value="medium" selected>متوسط</option>
              <option value="large">كبير</option>
            </select>
          </div>
        </div>
      </div>
    </div>
  </div>
  
  <div class="row">
    <div class="col-md-6">
      <div class="card mb-3">
        <div class="card-header">
          <h6 class="mb-0"><i class="fas fa-language me-2"></i>اللغة</h6>
        </div>
        <div class="card-body">
          <div class="mb-3">
            <label class="form-label">لغة الواجهة</label>
            <select class="form-select" id="language">
              <option value="ar" selected>العربية</option>
              <option value="en">English</option>
            </select>
          </div>
        </div>
      </div>
    </div>
    
    <div class="col-md-6">
      <div class="card mb-3">
        <div class="card-header">
          <h6 class="mb-0"><i class="fas fa-save me-2"></i>الحفظ</h6>
        </div>
        <div class="card-body">
          <button class="btn btn-primary" id="saveSettings">
            <i class="fas fa-save me-2"></i>حفظ الإعدادات
          </button>
          <button class="btn btn-secondary ms-2" id="resetSettings">
            <i class="fas fa-undo me-2"></i>إعادة تعيين
          </button>
        </div>
      </div>
    </div>
  </div>
</div>

<script>
$(function(){
  // تحميل الإعدادات المحفوظة
  loadSettings();
  
  // حفظ الإعدادات
  $('#saveSettings').on('click', function(){
    var settings = {
      notifications: {
        newShipments: $('#notifyNewShipments').is(':checked'),
        statusChanges: $('#notifyStatusChanges').is(':checked'),
        reminders: $('#notifyReminders').is(':checked')
      },
      appearance: {
        darkMode: $('#darkMode').is(':checked'),
        fontSize: $('#fontSize').val()
      },
      language: $('#language').val()
    };
    
    localStorage.setItem('courierSettings', JSON.stringify(settings));
    
    // تطبيق الإعدادات فوراً
    applySettings(settings);
    
    // رسالة نجاح
    alert('تم حفظ الإعدادات بنجاح');
  });
  
  // إعادة تعيين الإعدادات
  $('#resetSettings').on('click', function(){
    if(confirm('هل أنت متأكد من إعادة تعيين جميع الإعدادات؟')){
      localStorage.removeItem('courierSettings');
      loadSettings();
      alert('تم إعادة تعيين الإعدادات');
    }
  });
  
  // اختبار الصوت
  $('#testSound').on('click', function(){
    try {
      // استخدام نفس دالة الصوت من layout_bottom.php
      if (typeof playNotificationSound === 'function') {
        playNotificationSound();
        alert('تم تشغيل الصوت! إذا لم تسمعه، تأكد من أن الصوت مفعل في المتصفح');
      } else {
        // استخدام Web Audio API مباشرة
        if (window.AudioContext || window.webkitAudioContext) {
          var audioContext = new (window.AudioContext || window.webkitAudioContext)();
          
          // استئناف AudioContext إذا كان معلق
          if (audioContext.state === 'suspended') {
            audioContext.resume();
          }
          
          var oscillator = audioContext.createOscillator();
          var gainNode = audioContext.createGain();
          
          oscillator.connect(gainNode);
          gainNode.connect(audioContext.destination);
          
          oscillator.frequency.setValueAtTime(800, audioContext.currentTime);
          oscillator.frequency.setValueAtTime(600, audioContext.currentTime + 0.1);
          oscillator.frequency.setValueAtTime(800, audioContext.currentTime + 0.2);
          
          gainNode.gain.setValueAtTime(0.3, audioContext.currentTime);
          gainNode.gain.exponentialRampToValueAtTime(0.01, audioContext.currentTime + 0.3);
          
          oscillator.start(audioContext.currentTime);
          oscillator.stop(audioContext.currentTime + 0.3);
          
          alert('تم تشغيل الصوت باستخدام Web Audio API! إذا لم تسمعه، تأكد من أن الصوت مفعل في المتصفح');
        } else {
          alert('متصفحك لا يدعم Web Audio API. جرب متصفح حديث مثل Chrome أو Firefox');
        }
      }
    } catch(e) {
      alert('خطأ في تشغيل الصوت: ' + e.message);
    }
  });
  
  // فحص وجود ملف الصوت عند تحميل الصفحة
  $(function(){
    // فحص سريع لوجود ملف الصوت
    var testAudio = new Audio('assets/sounds/notification.mp3');
    testAudio.addEventListener('error', function() {
      $('#currentSound').text('مدمج (بيب) - ملف الصوت غير موجود');
    });
    
    testAudio.addEventListener('canplay', function() {
      $('#currentSound').text('مخصص: notification.mp3');
    });
    
    testAudio.load();
  });
  

  
  // تحميل الإعدادات
  function loadSettings(){
    var saved = localStorage.getItem('courierSettings');
    if(saved){
      var settings = JSON.parse(saved);
      $('#notifyNewShipments').prop('checked', settings.notifications?.newShipments !== false);
      $('#notifyStatusChanges').prop('checked', settings.notifications?.statusChanges !== false);
      $('#notifyReminders').prop('checked', settings.notifications?.reminders || false);
      $('#darkMode').prop('checked', settings.appearance?.darkMode || false);
      $('#fontSize').val(settings.appearance?.fontSize || 'medium');
      $('#language').val(settings.language || 'ar');
      
      applySettings(settings);
    }
  }
  
  // تطبيق الإعدادات
  function applySettings(settings){
    // تطبيق الوضع الليلي
    if(settings.appearance?.darkMode){
      $('body').addClass('dark-mode');
    } else {
      $('body').removeClass('dark-mode');
    }
    
    // تطبيق حجم الخط
    var fontSize = settings.appearance?.fontSize || 'medium';
    $('body').removeClass('font-small font-medium font-large').addClass('font-' + fontSize);
  }
});
</script>

<style>
.dark-mode {
  background: #1a1a1a !important;
  color: #ffffff !important;
}
.dark-mode .card {
  background: #2d2d2d !important;
  border-color: #404040 !important;
}
.dark-mode .card-header {
  background: #404040 !important;
  border-color: #505050 !important;
}
.font-small { font-size: 14px; }
.font-medium { font-size: 16px; }
.font-large { font-size: 18px; }
</style>

<?php include __DIR__.'/partials/layout_bottom.php'; ?>
