<?php
include 'db_connect.php';

// تفعيل عرض الأخطاء أثناء التطوير فقط
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// التعامل مع الطلب عند الإرسال
$message = "";
$success = false;
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['type'], $_POST['relation_id'])) {
    $type = $_POST['type']; // customer أو agent
    $relation_id = intval($_POST['relation_id']);
    $amount = floatval($_POST['amount']);
    $notes = mysqli_real_escape_string($conn, $_POST['notes']);

    $shipment_ids = [];
    // جلب الشحنات لمزيد من التحقق
    if ($type == 'customer') {
        $customer_q = mysqli_query($conn, "SELECT phone FROM customers WHERE id = $relation_id");
        $customer = mysqli_fetch_assoc($customer_q);
        $customer_phone = $customer ? $customer['phone'] : '';
        if ($customer_phone) {
            $shipments_q = "SELECT id FROM parcels WHERE sender_phone = '".mysqli_real_escape_string($conn, $customer_phone)."' AND status = 7 AND (is_paid IS NULL OR is_paid = 0)";
            $shipments_res = mysqli_query($conn, $shipments_q);
            while($r = mysqli_fetch_assoc($shipments_res)) $shipment_ids[] = $r['id'];
        }
    } elseif ($type == 'agent') {
        $shipments_q = "SELECT id FROM parcels WHERE agent_id = $relation_id AND status = 7 AND (is_paid IS NULL OR is_paid = 0)";
        $shipments_res = mysqli_query($conn, $shipments_q);
        while($r = mysqli_fetch_assoc($shipments_res)) $shipment_ids[] = $r['id'];
    }

    if ($amount > 0 && $relation_id > 0 && in_array($type, ['customer', 'agent']) && count($shipment_ids)) {
        // إضافة عملية مالية في جدول الخزنة (صَرْف مستحقات)
        $q = "INSERT INTO cashbox_transactions (type, relation_id, amount, direction, notes, created_at)
              VALUES ('$type', $relation_id, $amount, 'out', '$notes', NOW())";
        if (mysqli_query($conn, $q)) {
            $transaction_id = mysqli_insert_id($conn);
            // تحديث الشحنات: تعليمها أنها صُرفت
            $ids = implode(',', $shipment_ids);
            mysqli_query($conn, "UPDATE parcels SET is_paid=1, paid_at=NOW() WHERE id IN ($ids)");
            $message = '<div class="alert alert-success text-end">تم صرف المبلغ بنجاح وتحديث الشحنات.<br>رقم العملية: <b>#'.$transaction_id.'</b></div>';
            $success = true;
        } else {
            $message = '<div class="alert alert-danger text-end">حدث خطأ أثناء صرف المبلغ. يرجى المحاولة لاحقًا أو مراجعة الدعم.</div>';
        }
    } else {
        $message = '<div class="alert alert-danger text-end">يرجى اختيار الطرف ووجود شحنات جاهزة للدفع.</div>';
    }
}

// جلب العملاء والوكلاء لاستخدامهم في القائمة المنسدلة
$customers = [];
$agents = [];
$customers_res = mysqli_query($conn, "SELECT id, name, phone FROM customers ORDER BY name");
while($row = mysqli_fetch_assoc($customers_res)) $customers[] = $row;
$agents_res = mysqli_query($conn, "SELECT id, company_name FROM agents ORDER BY company_name");
while($row = mysqli_fetch_assoc($agents_res)) $agents[] = $row;

// جلب أرصدة العملاء والوكلاء لعرضها مباشرة
$customer_balances = [];
$agent_balances = [];
$cb_res = mysqli_query($conn, "SELECT c.id, IFNULL(SUM(IF(t.direction='in', t.amount, -t.amount)),0) as balance FROM customers c LEFT JOIN cashbox_transactions t ON t.type='customer' AND t.relation_id=c.id GROUP BY c.id");
while($row = mysqli_fetch_assoc($cb_res)) $customer_balances[$row['id']] = $row['balance'];
$ab_res = mysqli_query($conn, "SELECT a.id, IFNULL(SUM(IF(t.direction='in', t.amount, -t.amount)),0) as balance FROM agents a LEFT JOIN cashbox_transactions t ON t.type='agent' AND t.relation_id=a.id GROUP BY a.id");
while($row = mysqli_fetch_assoc($ab_res)) $agent_balances[$row['id']] = $row['balance'];
?>

<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.rtl.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<style>
body {background: #f4f6fb; font-family: 'Tajawal', Arial, sans-serif; direction: rtl;}
.section-card {background:#fff; border-radius:18px; box-shadow:0 4px 18px #ddeafc33; margin-bottom:32px; padding:32px 24px 20px 24px;}
.section-title {font-weight:bold; font-size:1.35em; color:#1976d2; margin-bottom:24px; border-bottom:2px solid #e3f6ff; padding-bottom:12px;}
.shadow-lg {box-shadow:0 4px 18px #ddeafc33;}
.form-select, .form-control {border-radius:1.2rem !important;}
.btn-lg {border-radius:1.5rem !important;}
.table-responsive {border-radius:18px;}
.table thead th {background:#e3f6ff; color:#1565c0;}
.table {border-radius:16px; overflow:hidden;}
.table td, .table th {vertical-align:middle;}
th, td {text-align:right !important;}
.badge {font-size:1em;}
.no-results {color:#888; padding:20px;}
.sticky-header thead th {position:sticky; top:0; background:#e3f6ff;}
@media (max-width: 576px) {
    .section-card {padding:14px 4px 8px 4px;}
    .btn-lg {margin-bottom:12px;}
}
</style>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-lg-10">
    <div class="section-card shadow-lg border-0">
        <div class="section-title bg-gradient text-white" style="background:linear-gradient(90deg,#1976d2,#43e97b); border-radius:12px;">
            <i class="bi bi-cash-coin"></i> صرف مستحقات (عميل / وكيل)
        </div>
        <div class="mb-3 p-2 bg-light rounded text-end">
            <i class="bi bi-info-circle"></i> يتم صرف المستحقات فقط للشحنات التي تم تسليمها ولم يتم صرفها مسبقًا.
        </div>
        <?= $message ?>
        <form method="post" class="row g-4" id="payForm" autocomplete="off"
            onsubmit="return confirm('هل أنت متأكد من تنفيذ الصرف؟ سيتم تحديث الشحنات إلى (مدفوعة) ولا يمكن التراجع!')">
            <div class="col-md-4">
                <label class="form-label text-end">نوع الطرف</label>
                <select name="type" id="pay_type" class="form-select" required>
                    <option value="">اختر النوع</option>
                    <option value="customer">عميل</option>
                    <option value="agent">وكيل</option>
                </select>
            </div>
            <div class="col-md-8">
                <label class="form-label text-end" id="label_relation">اختر الطرف</label>
                <select name="relation_id" id="relation_select" class="form-select" required disabled>
                    <option value="">اختر الطرف</option>
                </select>
                <small class="d-block text-end mt-1" id="selected_balance"></small>
            </div>
            <div class="col-md-12">
                <div id="shipments_box" class="d-none"></div>
                <div id="shipments_actions" class="d-none mt-2 text-end">
                    <button type="button" class="btn btn-outline-success btn-sm" id="exportShipmentsBtn">
                        <i class="bi bi-file-earmark-excel"></i> تصدير كشف الشحنات
                    </button>
                    <button type="button" class="btn btn-outline-primary btn-sm" id="printShipmentsBtn">
                        <i class="bi bi-printer"></i> طباعة كشف الشحنات
                    </button>
                </div>
            </div>
            <div class="col-md-4">
                <label class="form-label text-end">الإجمالي المستحق</label>
                <input type="number" step="0.01" min="0.01" name="amount" id="total_amount" class="form-control bg-light shadow-sm" readonly required>
            </div>
            <div class="col-md-8">
                <label class="form-label text-end">ملاحظات</label>
                <input type="text" name="notes" class="form-control shadow-sm" placeholder="إدخال ملاحظة (اختياري)">
            </div>
            <div class="col-12 text-end">
                <button type="submit" class="btn btn-lg btn-success px-5" id="payout_btn" disabled>
                    <i class="bi bi-send-check"></i> صرف المستحقات الآن
                </button>
            </div>
        </form>
    </div>
    </div>
    </div>
</div>

<!-- نافذة بحث العملاء/الوكلاء -->
<div class="modal fade" tabindex="-1" id="searchModal">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-info text-white">
                <h5 class="modal-title">بحث عن طرف</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="text" id="searchInput" class="form-control mb-3" placeholder="اكتب اسم أو هاتف الطرف...">
                <div id="searchResults"></div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
const customers = <?= json_encode($customers) ?>;
const agents = <?= json_encode($agents) ?>;
const customerBalances = <?= json_encode($customer_balances) ?>;
const agentBalances = <?= json_encode($agent_balances) ?>;

const payType = document.getElementById('pay_type');
const relationSelect = document.getElementById('relation_select');
const labelRelation = document.getElementById('label_relation');
const shipmentsBox = document.getElementById('shipments_box');
const totalAmount = document.getElementById('total_amount');
const payoutBtn = document.getElementById('payout_btn');
const selectedBalance = document.getElementById('selected_balance');
const shipmentsActions = document.getElementById('shipments_actions');
const exportShipmentsBtn = document.getElementById('exportShipmentsBtn');
const printShipmentsBtn = document.getElementById('printShipmentsBtn');

let currentShipmentsTable = null;

// تحديث القائمة حسب النوع
payType.addEventListener('change', function() {
    let type = this.value;
    relationSelect.innerHTML = '<option value="">اختر الطرف</option>';
    relationSelect.disabled = !type;
    shipmentsBox.innerHTML = "";
    shipmentsBox.classList.add('d-none');
    shipmentsActions.classList.add('d-none');
    totalAmount.value = "";
    payoutBtn.disabled = true;
    selectedBalance.innerHTML = '';

    if(type === 'customer') {
        labelRelation.innerText = "اسم العميل";
        customers.forEach(c => {
            let opt = document.createElement('option');
            opt.value = c.id;
            opt.text = c.name + " (ID: " + c.id + ")";
            relationSelect.appendChild(opt);
        });
    } else if(type === 'agent') {
        labelRelation.innerText = "اسم الوكيل";
        agents.forEach(a => {
            let opt = document.createElement('option');
            opt.value = a.id;
            opt.text = a.company_name + " (ID: " + a.id + ")";
            relationSelect.appendChild(opt);
        });
    } else {
        labelRelation.innerText = "اختر الطرف";
    }
});

// عند اختيار الطرف، جلب الشحنات تلقائيًا عبر ajax
relationSelect.addEventListener('change', function() {
    let id = this.value;
    let type = payType.value;
    shipmentsBox.innerHTML = "";
    shipmentsBox.classList.add('d-none');
    shipmentsActions.classList.add('d-none');
    totalAmount.value = "";
    payoutBtn.disabled = true;
    selectedBalance.innerHTML = '';

    if(!id || !type) return;

    // عرض الرصيد الحالي
    if(type === 'customer' && customerBalances[id] !== undefined) {
        let bal = parseFloat(customerBalances[id]);
        selectedBalance.innerHTML = 'رصيد العميل الحالي: <span class="' + (bal < 0 ? 'text-danger' : 'text-success') + '">' + bal.toLocaleString("ar-EG", {minimumFractionDigits:2}) + ' جنيه</span>';
    } else if(type === 'agent' && agentBalances[id] !== undefined) {
        let bal = parseFloat(agentBalances[id]);
        selectedBalance.innerHTML = 'رصيد الوكيل الحالي: <span class="' + (bal < 0 ? 'text-danger' : 'text-success') + '">' + bal.toLocaleString("ar-EG", {minimumFractionDigits:2}) + ' جنيه</span>';
    }

    shipmentsBox.innerHTML = '<div class="text-center py-5"><div class="spinner-border text-success"></div><div>جاري تحميل الشحنات...</div></div>';
    shipmentsBox.classList.remove('d-none');

    fetch('payout_shipments_ajax.php?type=' + type + '&id=' + id)
    .then(res => res.json())
    .then(data => {
        if(data.shipments.length === 0) {
            shipmentsBox.innerHTML = '<div class="alert alert-info text-center">لا يوجد شحنات تم تسليمها وجاهزة للدفع لهذا الطرف.</div>';
            totalAmount.value = "";
            payoutBtn.disabled = true;
            shipmentsActions.classList.add('d-none');
            currentShipmentsTable = null;
        } else {
            let rows = data.shipments.map(s => 
                `<tr>
                    <td>${s.reference_number}</td>
                    <td>${s.recipient_name}</td>
                    <td>${s.price.toLocaleString("ar-EG", {minimumFractionDigits:2})}</td>
                    <td>${s.date_created}</td>
                </tr>`
            ).join('');
            let table = `
                <div class="mb-3">
                <h5 class="mb-2 text-end"><i class="bi bi-truck"></i> شحنات تم تسليمها</h5>
                <div class="table-responsive" id="shipmentsTableDiv">
                <table class="table table-bordered table-striped text-end align-middle bg-white" id="shipmentsTable">
                    <thead class="table-success">
                        <tr>
                            <th>رقم الشحنة</th>
                            <th>اسم المستلم</th>
                            <th>القيمة</th>
                            <th>تاريخ التسليم</th>
                        </tr>
                    </thead>
                    <tbody>${rows}</tbody>
                    <tfoot>
                        <tr class="table-light">
                            <th colspan="2" class="text-end">الإجمالي</th>
                            <th colspan="2">${data.total.toLocaleString("ar-EG", {minimumFractionDigits:2})} جنيه</th>
                        </tr>
                    </tfoot>
                </table>
                </div>
                </div>
            `;
            shipmentsBox.innerHTML = table;
            totalAmount.value = data.total;
            payoutBtn.disabled = false;
            shipmentsActions.classList.remove('d-none');
            currentShipmentsTable = document.getElementById('shipmentsTable');
        }
    }).catch(()=>{
        shipmentsBox.innerHTML = '<div class="alert alert-danger text-center">تعذر تحميل الشحنات، حاول لاحقًا.</div>';
        totalAmount.value = "";
        payoutBtn.disabled = true;
        shipmentsActions.classList.add('d-none');
        currentShipmentsTable = null;
    });
});

// دعم زر بحث الطرف في نافذة مستقلة (اختياري)
/*
document.getElementById('searchBtn').addEventListener('click', function(){
    var modal = new bootstrap.Modal(document.getElementById('searchModal'));
    modal.show();
});
document.getElementById('searchInput').addEventListener('input', function(){
    let val = this.value.trim();
    let type = payType.value;
    let results = [];
    if(type === 'customer') {
        results = customers.filter(c => c.name.includes(val) || c.phone.includes(val));
    } else if(type === 'agent') {
        results = agents.filter(a => a.company_name.includes(val));
    }
    let html = results.map(r => `<div><b>${type==='customer'?r.name:r.company_name}</b> (${r.id}${r.phone?', '+r.phone:''})</div>`).join('');
    document.getElementById('searchResults').innerHTML = html || "<div class='text-muted'>لا يوجد نتائج</div>";
});
*/

// تصدير كشف الشحنات Excel
exportShipmentsBtn.addEventListener('click', function(){
    if(!currentShipmentsTable) return;
    let html = currentShipmentsTable.outerHTML.replace(/ /g, '%20');
    let a = document.createElement('a');
    a.href = 'data:application/vnd.ms-excel,' + html;
    a.download = 'كشف_الشحنات.xls';
    a.click();
});

// طباعة كشف الشحنات
printShipmentsBtn.addEventListener('click', function(){
    if(!currentShipmentsTable) return;
    let contents = currentShipmentsTable.outerHTML;
    let win = window.open('', '', 'height=700,width=900');
    win.document.write('<html><head><title>كشف الشحنات</title>');
    win.document.write('<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.rtl.min.css" rel="stylesheet">');
    win.document.write('</head><body dir="rtl">' + contents + '</body></html>');
    win.document.close();
    win.print();
});

// تعطيل زر الصرف أثناء التنفيذ لمنع التكرار
document.getElementById('payForm').addEventListener('submit', function(e){
    payoutBtn.disabled = true;
    payoutBtn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> جارٍ التنفيذ...';
});

</script>