<?php
// file: customer_settlement.php
// صفحة: تسوية حساب عميل

if (session_status() === PHP_SESSION_NONE) { session_start(); }
include __DIR__ . '/db_connect.php';
require_once __DIR__ . '/customer_financial_calculator.php';

// تأمين الترميز
if (function_exists('ini_set')) {
    @ini_set('display_errors', 0);
    @ini_set('log_errors', 1);
}
$conn->set_charset('utf8mb4');

$calculator = new CustomerFinancialCalculator($conn);

// إنشاء جدول الخزنة إذا لم يكن موجودًا
$conn->query("CREATE TABLE IF NOT EXISTS cashbox_transactions (
    id INT PRIMARY KEY AUTO_INCREMENT,
    type ENUM('customer','agent') NOT NULL,
    relation_id INT NOT NULL,
    amount DECIMAL(12,2) NOT NULL DEFAULT 0,
    direction ENUM('in','out') NOT NULL,
    notes TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");

// عمليات AJAX بسيطة
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    $action = $_POST['action'];

    if ($action === 'get_customer_financials') {
        $cid = intval($_POST['customer_id'] ?? 0);
        try {
            $data = $calculator->calculateCustomerFinancials($cid);
            if (isset($data['error'])) { throw new RuntimeException($data['error']); }
            echo json_encode(['status' => 'success', 'data' => $data]);
        } catch (Throwable $e) {
            echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        }
        exit;
    }

    if ($action === 'settle_customer') {
        $cid = intval($_POST['customer_id'] ?? 0);
        $amount = floatval($_POST['amount'] ?? 0);
        $notes = trim($_POST['notes'] ?? '');
        if ($cid <= 0 || $amount <= 0) {
            echo json_encode(['status' => 'error', 'message' => 'بيانات غير صالحة']);
            exit;
        }
        try {
            // احصل على الرصيد المتبقي للتحقق
            $data = $calculator->calculateCustomerFinancials($cid);
            if (isset($data['error'])) { throw new RuntimeException($data['error']); }
            $remaining = (float)($data['remaining_balance'] ?? 0);
            if ($remaining <= 0) {
                echo json_encode(['status' => 'error', 'message' => 'لا يوجد رصيد مستحق لهذا العميل.']);
                exit;
            }
            if ($amount > $remaining + 0.00001) {
                echo json_encode(['status' => 'error', 'message' => 'المبلغ يتجاوز الرصيد المستحق.']);
                exit;
            }

            // سجل عملية صرف (خروج) في الخزنة
            $stmt = $conn->prepare("INSERT INTO cashbox_transactions (type, relation_id, amount, direction, notes) VALUES ('customer', ?, ?, 'out', ?)");
            $stmt->bind_param('ids', $cid, $amount, $notes);
            $stmt->execute();
            $tid = $conn->insert_id;
            $stmt->close();

            echo json_encode(['status' => 'success', 'message' => 'تمت التسوية بنجاح.', 'transaction_id' => $tid]);
        } catch (Throwable $e) {
            echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        }
        exit;
    }

    echo json_encode(['status' => 'error', 'message' => 'إجراء غير معروف']);
    exit;
}

// جلب قائمة العملاء للاختيار
$customers = [];
if ($res = $conn->query("SELECT id, name, phone FROM customers ORDER BY name ASC")) {
    while ($r = $res->fetch_assoc()) { $customers[] = $r; }
    $res->close();
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>تسوية حساب عميل</title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.rtl.min.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" />
  <style>
    body { background: #f4f6fb; font-family: 'Tajawal', Arial, sans-serif; }
    .section-card { background:#fff; border-radius:18px; box-shadow:0 4px 18px #ddeafc33; margin:22px auto; padding:22px; }
    .section-title { font-weight:bold; font-size:1.35em; color:#1976d2; margin-bottom:16px; display:flex; align-items:center; gap:8px; justify-content:flex-end; }
    .metric { background:#f8fbff; border:1px solid #e7f1ff; border-radius:12px; padding:14px; text-align:center; }
    .metric .label { color:#6c757d; font-size: .92rem; }
    .metric .value { font-size:1.25rem; font-weight:700; }
    .table thead th { background:#e3f6ff; color:#1565c0; }
    .readonly { background-color:#eef3f8 !important; }
  </style>
</head>
<body>
<div class="container py-4">
  <div class="row justify-content-center">
    <div class="col-lg-10">
      <div class="section-card">
        <div class="section-title"><i class="fa-solid fa-scale-balanced"></i> تسوية حساب عميل</div>
        <div class="row g-3 align-items-end">
          <div class="col-md-7">
            <label class="form-label">اختر العميل</label>
            <select id="customer_select" class="form-select">
              <option value="">— اختر العميل —</option>
              <?php foreach ($customers as $c): ?>
                <option value="<?= (int)$c['id'] ?>"><?php echo htmlspecialchars($c['name'] . ' — ' . $c['phone']); ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-5 text-end">
            <button id="refresh_btn" type="button" class="btn btn-outline-primary"><i class="fa fa-rotate"></i> تحديث البيانات</button>
          </div>
        </div>
        <hr>
        <div id="summary_wrap" class="row g-3 d-none">
          <div class="col-md-4"><div class="metric"><div class="label">إجمالي قيمة الشحنات (COD)</div><div id="m_total_cod" class="value">0.00</div></div></div>
          <div class="col-md-4"><div class="metric"><div class="label">إجمالي المدفوع من المستلمين</div><div id="m_total_paid" class="value">0.00</div></div></div>
          <div class="col-md-4"><div class="metric"><div class="label">الرصيد المستحق للعميل</div><div id="m_remaining" class="value text-danger">0.00</div></div></div>
        </div>
        <div id="ships_wrap" class="mt-4 d-none">
          <div class="table-responsive">
            <table class="table table-bordered table-striped" id="ships_table">
              <thead>
                <tr>
                  <th>رقم الشحنة</th>
                  <th>الحالة</th>
                  <th>COD</th>
                  <th>رسوم الشحن</th>
                  <th>على من</th>
                  <th>المدفوع</th>
                  <th>صافي مستحق</th>
                </tr>
              </thead>
              <tbody></tbody>
            </table>
          </div>
        </div>
        <hr>
        <form id="settle_form" class="row g-3 d-none">
          <div class="col-md-4">
            <label class="form-label">مبلغ التسوية</label>
            <input type="number" step="0.01" min="0.01" id="settle_amount" class="form-control" placeholder="0.00">
          </div>
          <div class="col-md-8">
            <label class="form-label">ملاحظة</label>
            <input type="text" id="settle_note" class="form-control" placeholder="اختياري: أي ملاحظة على التسوية">
          </div>
          <div class="col-12 text-end">
            <button type="button" id="btn_settle_all" class="btn btn-outline-secondary"><i class="fa fa-equals"></i> تسوية كامل الرصيد</button>
            <button type="submit" class="btn btn-success"><i class="fa fa-check"></i> تنفيذ التسوية</button>
          </div>
        </form>
        <div id="msg_box" class="mt-3"></div>
      </div>
    </div>
  </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script>
(function(){
  const customerSel = $('#customer_select');
  const summaryWrap = $('#summary_wrap');
  const shipsWrap = $('#ships_wrap');
  const shipsBody = $('#ships_table tbody');
  const settleForm = $('#settle_form');
  const settleAmount = $('#settle_amount');
  const settleNote = $('#settle_note');
  const msgBox = $('#msg_box');

  function fmt(n){ return (parseFloat(n||0)).toFixed(2); }

  function loadFinancials(){
    const cid = parseInt(customerSel.val()||0, 10);
    if(!cid){ summaryWrap.addClass('d-none'); shipsWrap.addClass('d-none'); settleForm.addClass('d-none'); return; }
    msgBox.html('');
    $.ajax({
      url: 'customer_settlement.php',
      type: 'POST',
      dataType: 'json',
      data: { action: 'get_customer_financials', customer_id: cid },
      success: function(resp){
        if(resp.status === 'success'){
          const d = resp.data;
          const totalCod = parseFloat(d.total_cod_amount || 0);
          const totalPaid = parseFloat(d.total_paid_amount || 0);
          const remaining = parseFloat(d.remaining_balance || 0);
          $('#m_total_cod').text(fmt(totalCod));
          $('#m_total_paid').text(fmt(totalPaid));
          $('#m_remaining').text(fmt(remaining)).toggleClass('text-danger', remaining>0).toggleClass('text-success', remaining<=0);
          summaryWrap.removeClass('d-none');

          // جدول الشحنات
          shipsBody.empty();
          const list = Array.isArray(d.shipments_details) ? d.shipments_details : [];
          list.forEach(function(s){
            const payerTxt = (String(s.shipping_payer||'sender')==='sender')? 'المرسل':'المستلم';
            shipsBody.append('<tr>' +
              '<td>'+ (s.tracking_number||'') +'</td>'+
              '<td>'+ (s.status||'') +'</td>'+
              '<td>'+ fmt(s.cod_amount) +'</td>'+
              '<td>'+ fmt(s.shipping_fees) +'</td>'+
              '<td>'+ payerTxt +'</td>'+
              '<td>'+ fmt(s.paid_amount) +'</td>'+
              '<td><strong>'+ fmt(s.net_amount) +'</strong></td>'+
            '</tr>');
          });
          shipsWrap.toggleClass('d-none', list.length===0);

          // تهيئة نموذج التسوية
          settleAmount.val( remaining > 0 ? fmt(remaining) : '' );
          settleForm.removeClass('d-none');
        } else {
          msgBox.html('<div class="alert alert-danger">'+ (resp.message||'فشل تحميل البيانات') +'</div>');
        }
      },
      error: function(){ msgBox.html('<div class="alert alert-danger">حدث خطأ غير متوقع</div>'); }
    });
  }

  $('#refresh_btn').on('click', loadFinancials);
  customerSel.on('change', loadFinancials);

  $('#btn_settle_all').on('click', function(){
    const txt = $('#m_remaining').text().trim();
    const n = parseFloat(txt.replace(/[^\d.\-]/g,'')) || 0;
    if(n>0){ settleAmount.val(n.toFixed(2)); }
  });

  settleForm.on('submit', function(e){
    e.preventDefault();
    const cid = parseInt(customerSel.val()||0, 10);
    const amt = parseFloat(settleAmount.val()||0);
    const note = settleNote.val()||'';
    if(!cid || !amt || amt<=0){
      msgBox.html('<div class="alert alert-warning">يرجى اختيار عميل وإدخال مبلغ صحيح.</div>');
      return;
    }
    msgBox.html('');
    $.ajax({
      url: 'customer_settlement.php',
      type: 'POST',
      dataType: 'json',
      data: { action: 'settle_customer', customer_id: cid, amount: amt, notes: note },
      success: function(resp){
        if(resp.status === 'success'){
          msgBox.html('<div class="alert alert-success">'+ resp.message +' — رقم العملية: #' + resp.transaction_id + '</div>');
          loadFinancials();
        } else {
          msgBox.html('<div class="alert alert-danger">'+ (resp.message||'فشل تنفيذ التسوية') +'</div>');
        }
      },
      error: function(){ msgBox.html('<div class="alert alert-danger">حدث خطأ غير متوقع</div>'); }
    });
  });
})();
</script>
</body>
</html>