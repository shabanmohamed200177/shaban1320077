<?php
require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'middleware.php';
require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'error_handler.php';
require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'db_connect.php';

SecurityMiddleware::checkSession();
if (!isset($_SESSION['courier_id'])) { header('Location: login.php'); exit; }

$courierName = $_SESSION['courier_name'] ?? 'المندوب';
$pageTitle = 'شحنات حسب الحالة';
include __DIR__.'/partials/layout_top.php';
?>
<div class="container py-4">
  <h5 class="mb-3">عرض الشحنات حسب الحالة</h5>
  <div id="status-cards"></div>
</div>

<script>
  function formatMoney(v){ v=parseFloat(v||0); return v.toLocaleString('ar-EG',{minimumFractionDigits:2,maximumFractionDigits:2})+' ج.م'; }
  function renderStatusBadge(name){
    var cls='badge-secondary';
    if(name && name.indexOf('التسليم')!==-1) cls='badge-success';
    else if(name && name.indexOf('تأجيل')!==-1) cls='badge-warning';
    else if(name && name.indexOf('رفض')!==-1) cls='badge-danger';
    return '<span class="badge '+cls+'">'+(name||'')+'</span>';
  }
  function buildCard(item){
    var extra = '';
    if(item.status==7 && item.status_reason_text){ extra = '<div class="text-muted">سبب التأجيل: '+item.status_reason_text+'</div>'; }
    if(item.status==8 && item.status_reason_text){ extra = '<div class="text-muted">سبب الرفض: '+item.status_reason_text+'</div>'; }
    return '<div class="card mb-2"><div class="card-body">\
      <div class="d-flex align-items-center"><div class="mr-auto">'+renderStatusBadge(item.status_name||item.status)+'</div><div style="font-weight:700">'+(item.tracking_number||'—')+'</div></div>\
      <div class="mt-2">\
        <div><b>المستلم:</b> '+(item.recipient_name||'—')+' — '+(item.recipient_phone||'')+'</div>\
        <div class="text-muted"><i class="fas fa-location-dot"></i> '+(item.governorate_name||'—')+' - '+(item.area_name||'—')+'</div>\
        <div><i class="fas fa-sack-dollar"></i> '+formatMoney((item.total_to_collect!==null && item.total_to_collect!==undefined)? item.total_to_collect : item.cod_amount)+'</div>\
        <div><i class="fas fa-coins"></i> <span class="text-success">عمولة المندوب:</span> '+formatMoney(item.courier_commission_net||0)+'</div>'+extra+'\
      </div>\
    </div></div>';
  }
  function loadByStatus(){
    var st = new URLSearchParams(location.search).get('st')||'';
    $.getJSON('api/shipments.php',{status:st,page:1,page_size:100}, function(resp){
      var wrap = $('#status-cards'); wrap.empty();
      if(resp && resp.success){
        if(resp.data.length===0){ wrap.append('<div class="text-center text-muted py-5">لا يوجد شحنات</div>'); return; }
        resp.data.forEach(function(x){ wrap.append(buildCard(x)); });
      }
    });
  }
  $(function(){ loadByStatus(); });
</script>
<?php include __DIR__.'/partials/layout_bottom.php'; ?>


