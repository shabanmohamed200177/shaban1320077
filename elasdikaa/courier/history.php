<?php
require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'middleware.php';
require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'error_handler.php';
SecurityMiddleware::checkSession();
if (!isset($_SESSION['courier_id'])) { header('Location: login.php'); exit; }
$courierName = $_SESSION['courier_name'] ?? 'المندوب';
$pageTitle = 'الشحنات التاريخية';
include __DIR__.'/partials/layout_top.php';
?>
  <div class="container py-3 bg-light">
    <div class="d-flex align-items-center mb-3">
      <h5 class="mb-0">الشحنات التاريخية</h5>
      <div class="ml-auto d-flex" style="gap:8px">
        <input type="date" id="from" class="form-control form-control-sm">
        <input type="date" id="to" class="form-control form-control-sm">
        <select id="st" class="form-control form-control-sm"><option value="">كل الحالات</option><option value="3">جاري التوصيل</option><option value="4">تم التسليم</option><option value="6">دفع جزئي</option><option value="7">مؤجل</option><option value="8">مرفوض</option><option value="9">مرتجع للفرع</option><option value="10">مرتجع للعميل</option></select>
        <input type="text" id="q" class="form-control form-control-sm" placeholder="بحث (تتبع/اسم/هاتف)">
        <button class="btn btn-sm btn-primary" id="btnLoad">عرض</button>
      </div>
    </div>

    <div class="table-responsive">
      <table class="table table-sm table-striped">
        <thead class="thead-light"><tr>
          <th>التاريخ</th><th>رقم التتبع</th><th>المستلم</th><th>الهاتف</th><th>الحالة</th><th>الموقع</th><th>المبلغ</th>
        </tr></thead>
        <tbody id="rows"></tbody>
      </table>
    </div>
  </div>
  <script>
    function money(v){return (parseFloat(v||0)).toLocaleString('ar-EG',{minimumFractionDigits:2,maximumFractionDigits:2})+' ج.م'}
    function load(){
      $.getJSON('api/history.php',{date_from:$('#from').val(),date_to:$('#to').val(),status:$('#st').val(),q:$('#q').val()}, function(r){
        if(!r||!r.success) return alert('تعذر جلب البيانات');
        var tb = $('#rows'); tb.empty();
        r.rows.forEach(function(x){
          tb.append('<tr>'+
            '<td>'+(x.date_created||'')+'</td>'+
            '<td>'+(x.tracking_number||'')+'</td>'+
            '<td>'+(x.recipient_name||'')+'</td>'+
            '<td>'+(x.recipient_phone||'')+'</td>'+
            '<td>'+(x.status_name||x.status)+'</td>'+
            '<td>'+(x.governorate_name||'')+' - '+(x.area_name||'')+'</td>'+
            '<td dir="ltr">'+money(x.cod_amount)+'</td>'+
          '</tr>');
        });
      });
    }
    $('#btnLoad').on('click', load);
    $(function(){ load(); });
  </script>
<?php include __DIR__.'/partials/layout_bottom.php'; ?>


