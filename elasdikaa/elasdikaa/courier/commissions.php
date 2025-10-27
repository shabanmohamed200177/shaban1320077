<?php
require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'middleware.php';
require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'error_handler.php';
SecurityMiddleware::checkSession();
if (!isset($_SESSION['courier_id'])) { header('Location: login.php'); exit; }
$courierName = $_SESSION['courier_name'] ?? 'المندوب';
$pageTitle = 'سجل العمولات';
include __DIR__.'/partials/layout_top.php';
?>
  <div class="container py-3 bg-light">
    <div class="d-flex align-items-center mb-3">
      <h5 class="mb-0">سجل العمولات</h5>
      <div class="ml-auto d-flex" style="gap:8px">
        <input type="date" id="from" class="form-control form-control-sm">
        <input type="date" id="to" class="form-control form-control-sm">
        <button class="btn btn-sm btn-primary" id="btnLoad">عرض</button>
      </div>
    </div>

    <div class="row text-center mb-3">
      <div class="col-4"><div class="card"><div class="card-body p-2"><div class="small text-muted">عدد المسلّم</div><div id="sum-count">--</div></div></div></div>
      <div class="col-4"><div class="card"><div class="card-body p-2"><div class="small text-muted">قيمة المسلّم</div><div id="sum-cod">--</div></div></div></div>
      <div class="col-4"><div class="card"><div class="card-body p-2"><div class="small text-muted">عمولتي</div><div id="sum-commission">--</div></div></div></div>
    </div>

    <div class="table-responsive">
      <table class="table table-sm table-striped">
        <thead class="thead-light"><tr>
          <th>التاريخ</th><th>رقم التتبع</th><th>المستلم</th><th>المنطقة</th><th>COD</th><th>عمولة المندوب (صافية)</th>
        </tr></thead>
        <tbody id="rows"></tbody>
      </table>
    </div>
  </div>
  <script>
    function money(v){return (parseFloat(v||0)).toLocaleString('ar-EG',{minimumFractionDigits:2,maximumFractionDigits:2})+' ج.م'}
    function load(){
      $.getJSON('api/commissions.php',{date_from:$('#from').val(),date_to:$('#to').val()}, function(r){
        if(!r||!r.success) return alert('تعذر جلب البيانات');
        $('#sum-count').text(r.summary.delivered_count);
        $('#sum-cod').text(money(r.summary.cod_delivered));
        $('#sum-commission').text(money(r.summary.commission_total));
        var tb = $('#rows'); tb.empty();
        r.rows.forEach(function(x){
          tb.append('<tr>'+
            '<td>'+(x.date_created||'')+'</td>'+
            '<td>'+(x.tracking_number||'')+'</td>'+
            '<td>'+(x.recipient_name||'')+'</td>'+
            '<td>'+(x.governorate_name||'')+' - '+(x.area_name||'')+'</td>'+
            '<td dir="ltr">'+money(x.cod_amount)+'</td>'+
            '<td dir="ltr">'+money(x.courier_commission_net)+'</td>'+
          '</tr>');
        });
      });
    }
    $('#btnLoad').on('click', load);
    $(function(){ load(); });
  </script>
<?php include __DIR__.'/partials/layout_bottom.php'; ?>


