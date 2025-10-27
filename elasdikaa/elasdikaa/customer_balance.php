<?php
include 'db_connect.php';

// منطقة البحث للعملاء
$search = $_POST['search'] ?? '';
$filter_balance = $_POST['filter_balance'] ?? 'all';

// جلب قائمة العملاء وأرصدة كل عميل مع التصفية والبحث
$where = "WHERE 1";
if($search != '') {
    $safe_search = mysqli_real_escape_string($conn, $search);
    $where .= " AND (c.name LIKE '%$safe_search%' OR c.id LIKE '%$safe_search%')";
}
if($filter_balance == 'positive') {
    $having = "HAVING balance >= 0";
} elseif($filter_balance == 'negative') {
    $having = "HAVING balance < 0";
} else {
    $having = "";
}

$q = "
SELECT c.id, c.name, IFNULL(SUM(IF(t.direction='in', t.amount, -t.amount)),0) as balance,
    COUNT(t.id) as trans_count,
    MAX(t.created_at) as last_trans
FROM customers c
LEFT JOIN cashbox_transactions t ON t.type='customer' AND t.relation_id=c.id
$where
GROUP BY c.id
$having
ORDER BY c.name
";

$res = mysqli_query($conn, $q);

// تفاصيل حركات العميل عند الطلب (AJAX)
if(isset($_GET['customer_id']) && is_numeric($_GET['customer_id'])) {
    $customer_id = intval($_GET['customer_id']);
    $history_q = "
        SELECT id, amount, direction, notes, created_at
        FROM cashbox_transactions
        WHERE type='customer' AND relation_id=$customer_id
        ORDER BY created_at DESC
        LIMIT 100
    ";
    $history = mysqli_query($conn, $history_q);

    // حساب مجموع الدخل ومجموع الصرف
    $totals_q = "
        SELECT
            SUM(IF(direction='in', amount, 0)) as total_in,
            SUM(IF(direction='out', amount, 0)) as total_out
        FROM cashbox_transactions
        WHERE type='customer' AND relation_id=$customer_id
    ";
    $totals = mysqli_fetch_assoc(mysqli_query($conn, $totals_q));
    ?>
    <div class="table-responsive mt-3">
        <table class="table table-bordered table-striped text-end align-middle">
            <thead>
                <tr>
                    <th>رقم الحركة</th>
                    <th>المبلغ</th>
                    <th>الاتجاه</th>
                    <th>ملاحظات</th>
                    <th>تاريخ الحركة</th>
                </tr>
            </thead>
            <tbody>
                <?php while($row = mysqli_fetch_assoc($history)): ?>
                <tr>
                    <td><?= $row['id'] ?></td>
                    <td>
                        <?php if($row['direction']=='in'): ?>
                            <span class="text-success"><i class="fa-solid fa-arrow-down"></i> <?= number_format($row['amount'],2) ?> جنيه</span>
                        <?php else: ?>
                            <span class="text-danger"><i class="fa-solid fa-arrow-up"></i> <?= number_format($row['amount'],2) ?> جنيه</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?= $row['direction']=='in'
                            ? '<span class="badge bg-success">داخل</span>'
                            : '<span class="badge bg-danger">خارج</span>' ?>
                    </td>
                    <td>
                        <?= $row['notes'] ? htmlspecialchars($row['notes']) : '<span class="text-muted">بدون ملاحظات</span>' ?>
                    </td>
                    <td><?= date('Y-m-d H:i', strtotime($row['created_at'])) ?></td>
                </tr>
                <?php endwhile; ?>
                <?php if(mysqli_num_rows($history)==0): ?>
                <tr>
                    <td colspan="5" class="text-center text-muted">لا يوجد حركات لهذا العميل</td>
                </tr>
                <?php endif; ?>
            </tbody>
            <?php if($totals): ?>
            <tfoot>
                <tr>
                    <th colspan="2" class="text-end">إجمالي الدخل:</th>
                    <th colspan="3" class="text-success"><?= number_format($totals['total_in'],2) ?> جنيه</th>
                </tr>
                <tr>
                    <th colspan="2" class="text-end">إجمالي الصرف:</th>
                    <th colspan="3" class="text-danger"><?= number_format($totals['total_out'],2) ?> جنيه</th>
                </tr>
            </tfoot>
            <?php endif; ?>
        </table>
    </div>
    <div class="mt-4 text-center">
        <button class="btn btn-outline-primary" onclick="printDiv('customer-history')"><i class="fa-solid fa-print"></i> طباعة الكشف</button>
        <button class="btn btn-outline-success" onclick="exportTableToExcel('customer-history')"><i class="fa-solid fa-file-excel"></i> تصدير Excel</button>
    </div>
    <script>
    function printDiv(divId){
        var contents = document.getElementById(divId).innerHTML;
        var frame = document.createElement('iframe');
        document.body.appendChild(frame);
        frame.contentDocument.write('<html><head><title>كشف حساب العميل</title></head><body>' + contents + '</body></html>');
        frame.contentWindow.print();
        document.body.removeChild(frame);
    }
    function exportTableToExcel(divId){
        var table = document.getElementById(divId).getElementsByTagName('table')[0];
        var html = table.outerHTML.replace(/ /g, '%20');
        var a = document.createElement('a');
        a.href = 'data:application/vnd.ms-excel,' + html;
        a.download = 'كشف_الحساب.xls';
        a.click();
    }
    </script>
    <?php
    exit;
}

// قسم الجدول للعملاء (عند الطلب AJAX)
if(isset($_POST['ajax_customers'])) {
    ob_clean();
    ?>
    <div class="table-responsive sticky-header">
        <table class="table table-bordered table-striped text-end align-middle">
            <thead>
                <tr>
                    <th>رقم الصف</th>
                    <th>اسم العميل</th>
                    <th>الرقم التعريفي</th>
                    <th>الرصيد الحالي</th>
                    <th>آخر حركة</th>
                    <th>عدد الحركات</th>
                    <th>كشف الحركات</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $row_number = 1;
                while($row = mysqli_fetch_assoc($res)):
                    $rowClass = ($row['balance']<0 ? 'negative-row' : '');
                ?>
                <tr class="<?= $rowClass ?>">
                    <td class="row-number"><?= $row_number++ ?></td>
                    <td><?= htmlspecialchars($row['name']) ?></td>
                    <td><?= $row['id'] ?></td>
                    <td>
                        <?php if($row['balance'] >= 0): ?>
                            <span class="balance-positive"><i class="fa-solid fa-plus-circle"></i> <?= number_format($row['balance'],2) ?> جنيه</span>
                        <?php else: ?>
                            <span class="balance-negative"><i class="fa-solid fa-minus-circle"></i> <?= number_format($row['balance'],2) ?> جنيه</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if($row['last_trans']): ?>
                            <?= date('Y-m-d H:i', strtotime($row['last_trans'])) ?>
                        <?php else: ?>
                            <span class="text-muted">لا يوجد</span>
                        <?php endif; ?>
                    </td>
                    <td><?= $row['trans_count'] ?></td>
                    <td>
                        <button type="button"
                            class="btn btn-info btn-details"
                            onclick="showHistory(<?= $row['id'] ?>,'<?= htmlspecialchars($row['name'],ENT_QUOTES) ?>')">
                            <i class="fa-solid fa-eye"></i> كشف الحركات
                        </button>
                    </td>
                </tr>
                <?php endwhile; ?>
                <?php if($row_number==1): ?>
                <tr>
                    <td colspan="7" class="text-center">لا يوجد عملاء</td>
                </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php
    exit;
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <title>كشف أرصدة العملاء | الخزنة</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <!-- Bootstrap & FontAwesome -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.rtl.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        body {background: #f4f6fb; font-family: 'Tajawal', Arial, sans-serif; direction: rtl;}
        .main-btn {border-radius:1.5rem !important; font-weight:600;}
        .section-card {background:#fff; border-radius:18px; box-shadow:0 2px 14px #ddeafc33; margin-bottom:32px; padding:32px 24px 20px 24px;}
        .section-title {font-weight:bold; font-size:1.35em; color:#1976d2; margin-bottom:24px; border-bottom:2px solid #e3f6ff; padding-bottom:12px;}
        .tooltip-icon {color:#1976d2; cursor:help;}
        .table-responsive {border-radius:18px;}
        .table thead th {background:#e3f6ff; color:#1565c0;}
        .table {border-radius:16px; overflow:hidden;}
        .table td, .table th {vertical-align:middle;}
        th, td {text-align:right !important;}
        .badge {font-size:1em;}
        .no-results {color:#888; padding:20px;}
        .balance-positive {color:#067c09; font-weight:bold;}
        .balance-negative {color:#c62828; font-weight:bold;}
        .row-number {font-weight:bold; color:#1976d2;}
        .btn-details {font-size:0.95em; border-radius:1.3rem;}
        .history-section {background:#fff; border-radius:18px; box-shadow:0 2px 14px #ddeafc33; margin-bottom:32px; padding:28px 20px 18px 20px;}
        .sticky-header thead th {position:sticky; top:0; background:#e3f6ff;}
        .negative-row {background:#ffeeee;}
        .filter-area {background:#e9f7fd; border-radius:12px; padding:16px 14px; margin-bottom:24px;}
        @media (max-width: 576px) {
            .section-card, .history-section, .filter-area {padding:12px 4px 8px 4px;}
            .main-btn {margin-bottom:12px;}
        }
    </style>
</head>
<body>
<div class="container py-4" style="direction:rtl;">
    <!-- زر الخزنة الرئيسية -->
    <div class="mb-3 text-start">
        <a href="main_vault.php" class="btn btn-success main-btn"><i class="fa-solid fa-vault"></i> الخزنة الرئيسية</a>
    </div>

    <!-- منطقة البحث والتصفية -->
    <div class="filter-area" id="filter-area">
        <form id="filterForm" class="row g-2 align-items-center" autocomplete="off">
            <div class="col-md-4 mb-2">
                <input type="text" id="searchCustomer" name="search" class="form-control" placeholder="ابحث باسم العميل أو الرقم التعريفي...">
            </div>
            <div class="col-md-3 mb-2">
                <select name="filter_balance" id="filter_balance" class="form-select">
                    <option value="all">كل الأرصدة</option>
                    <option value="positive">أرصدة موجبة فقط</option>
                    <option value="negative">أرصدة سالبة فقط</option>
                </select>
            </div>
            <div class="col-md-2 mb-2">
                <button type="submit" class="btn btn-primary main-btn"><i class="fa-solid fa-filter"></i> تصفية</button>
            </div>
            <div class="col-md-3 mb-2 text-end">
                <button type="button" class="btn btn-outline-secondary main-btn" id="resetBtn"><i class="fa-solid fa-rotate-left"></i> إعادة ضبط</button>
                <button type="button" class="btn btn-outline-success main-btn" onclick="exportTableToExcel('customers-table-area')"><i class="fa-solid fa-file-excel"></i> تصدير قائمة العملاء</button>
                <button type="button" class="btn btn-outline-primary main-btn" onclick="printDiv('customers-table-area')"><i class="fa-solid fa-print"></i> طباعة القائمة</button>
            </div>
        </form>
    </div>

    <!-- كشف أرصدة العملاء -->
    <div class="section-card" id="customers-table-area">
        <div class="section-title">
            <i class="fa-solid fa-users"></i> كشف أرصدة العملاء
        </div>
        <div id="customers-table-content">
            <!-- الجدول يظهر هنا -->
            <div class="table-responsive sticky-header">
                <table class="table table-bordered table-striped text-end align-middle">
                    <thead>
                        <tr>
                            <th>رقم الصف</th>
                            <th>اسم العميل</th>
                            <th>الرقم التعريفي</th>
                            <th>الرصيد الحالي</th>
                            <th>آخر حركة</th>
                            <th>عدد الحركات</th>
                            <th>كشف الحركات</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $row_number = 1;
                        mysqli_data_seek($res,0);
                        while($row = mysqli_fetch_assoc($res)):
                            $rowClass = ($row['balance']<0 ? 'negative-row' : '');
                        ?>
                        <tr class="<?= $rowClass ?>">
                            <td class="row-number"><?= $row_number++ ?></td>
                            <td><?= htmlspecialchars($row['name']) ?></td>
                            <td><?= $row['id'] ?></td>
                            <td>
                                <?php if($row['balance'] >= 0): ?>
                                    <span class="balance-positive"><i class="fa-solid fa-plus-circle"></i> <?= number_format($row['balance'],2) ?> جنيه</span>
                                <?php else: ?>
                                    <span class="balance-negative"><i class="fa-solid fa-minus-circle"></i> <?= number_format($row['balance'],2) ?> جنيه</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if($row['last_trans']): ?>
                                    <?= date('Y-m-d H:i', strtotime($row['last_trans'])) ?>
                                <?php else: ?>
                                    <span class="text-muted">لا يوجد</span>
                                <?php endif; ?>
                            </td>
                            <td><?= $row['trans_count'] ?></td>
                            <td>
                                <button type="button"
                                    class="btn btn-info btn-details"
                                    onclick="showHistory(<?= $row['id'] ?>,'<?= htmlspecialchars($row['name'],ENT_QUOTES) ?>')">
                                    <i class="fa-solid fa-eye"></i> كشف الحركات
                                </button>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                        <?php if($row_number==1): ?>
                        <tr>
                            <td colspan="7" class="text-center">لا يوجد عملاء</td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- كشف حساب العميل -->
    <div class="history-section" id="customer-history-section" style="display:none;">
        <div class="section-title" id="history-title"><i class="fa-solid fa-file-lines"></i> كشف حساب العميل</div>
        <div id="customer-history"></div>
        <button type="button" class="btn btn-secondary main-btn mt-3" onclick="hideHistory()">
            <i class="fa-solid fa-xmark"></i> إغلاق الكشف
        </button>
    </div>
</div>
<script>
function updateCustomersTable(data){
    var xhr = new XMLHttpRequest();
    xhr.open('POST', 'customer_balance.php', true);
    xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
    xhr.onload = function(){
        document.getElementById('customers-table-content').innerHTML = this.responseText;
    };
    var formData = new FormData();
    for(var key in data){ formData.append(key, data[key]); }
    formData.append('ajax_customers', '1');
    xhr.send(formData);
}

// دعم إعادة الضبط بدون إعادة تحميل الصفحة
document.getElementById('resetBtn').addEventListener('click', function(e){
    document.getElementById('searchCustomer').value = '';
    document.getElementById('filter_balance').value = 'all';
    updateCustomersTable({search:'',filter_balance:'all'});
});

// دعم الفلترة والبحث بدون إعادة تحميل الصفحة
document.getElementById('filterForm').addEventListener('submit', function(e){
    e.preventDefault();
    var search = document.getElementById('searchCustomer').value;
    var balance = document.getElementById('filter_balance').value;
    updateCustomersTable({search:search,filter_balance:balance});
});

function showHistory(id, name){
    document.getElementById('customer-history-section').style.display = 'block';
    document.getElementById('history-title').innerHTML = '<i class="fa-solid fa-file-lines"></i> كشف حساب: ' + name;
    document.getElementById('customer-history').innerHTML = '<div class="text-center p-3"><i class="fa-solid fa-spinner fa-spin fa-2x text-info"></i><br>جاري التحميل...</div>';
    var xhr = new XMLHttpRequest();
    xhr.open('GET', 'customer_balance.php?customer_id='+id, true);
    xhr.onload = function(){
        document.getElementById('customer-history').innerHTML = '<div id="customer-history">' + this.responseText + '</div>';
    };
    xhr.send();
}
function hideHistory(){
    document.getElementById('customer-history-section').style.display = 'none';
}

// تصدير الجدول Excel
function exportTableToExcel(divId){
    var table = document.getElementById(divId).getElementsByTagName('table')[0];
    var html = table.outerHTML.replace(/ /g, '%20');
    var a = document.createElement('a');
    a.href = 'data:application/vnd.ms-excel,' + html;
    a.download = 'كشف_ارصدة_العملاء.xls';
    a.click();
}

// طباعة الجدول
function printDiv(divId){
    var contents = document.getElementById(divId).innerHTML;
    var win = window.open('', '', 'height=700,width=900');
    win.document.write('<html><head><title>كشف أرصدة العملاء</title>');
    win.document.write('<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.rtl.min.css" rel="stylesheet">');
    win.document.write('</head><body dir="rtl">' + contents + '</body></html>');
    win.document.close();
    win.print();
}
</script>
</body>
</html>