<?php
include 'db_connect.php';

// جلب العملاء والوكلاء مرة واحدة لاستخدامها في الفلاتر (للاستخدام في جافاسكريبت)
$customers = [];
$agents = [];
$res_c = mysqli_query($conn, "SELECT id, name FROM customers ORDER BY name");
while($r = mysqli_fetch_assoc($res_c)) $customers[] = $r;
$res_a = mysqli_query($conn, "SELECT id, company_name FROM agents ORDER BY company_name");
while($r = mysqli_fetch_assoc($res_a)) $agents[] = $r;

if (isset($_POST['ajax'])) {
    // استقبال الفلاتر من ajax
    $from = $_POST['from'] ?? date('Y-m-01');
    $to = $_POST['to'] ?? date('Y-m-d');
    $type = $_POST['type'] ?? '';
    $direction = $_POST['direction'] ?? '';
    $relation_id = $_POST['relation_id'] ?? '';
    $min_amount = $_POST['min_amount'] ?? '';
    $max_amount = $_POST['max_amount'] ?? '';

    // بناء شروط الفلترة
    $where = "WHERE DATE(created_at) BETWEEN '$from' AND '$to'";
    if ($type) $where .= " AND type = '" . mysqli_real_escape_string($conn, $type) . "'";
    if ($direction) $where .= " AND direction = '" . mysqli_real_escape_string($conn, $direction) . "'";
    if ($relation_id) $where .= " AND relation_id = " . intval($relation_id);
    if ($min_amount !== '') $where .= " AND amount >= " . floatval($min_amount);
    if ($max_amount !== '') $where .= " AND amount <= " . floatval($max_amount);

    // جلب أسماء للعملاء والوكلاء لعرضهم في الجدول
    $names = [];
    $res_c = mysqli_query($conn, "SELECT id, name FROM customers ORDER BY name");
    while($r = mysqli_fetch_assoc($res_c)) $names['customer'][$r['id']] = $r['name'];
    $res_a = mysqli_query($conn, "SELECT id, company_name FROM agents ORDER BY company_name");
    while($r = mysqli_fetch_assoc($res_a)) $names['agent'][$r['id']] = $r['company_name'];

    // ملخص التقرير المالي
    $q_summary = "SELECT 
            SUM(CASE WHEN direction='in' THEN amount ELSE 0 END) as total_in,
            SUM(CASE WHEN direction='out' THEN amount ELSE 0 END) as total_out,
            COUNT(*) as total_count,
            MAX(amount) as max_val,
            MIN(amount) as min_val
          FROM cashbox_transactions
          $where";
    $res = mysqli_query($conn, $q_summary);
    $row = mysqli_fetch_assoc($res);
    $total_in = $row['total_in'] ?? 0;
    $total_out = $row['total_out'] ?? 0;
    $net = $total_in - $total_out;
    $total_count = $row['total_count'] ?? 0;
    $max_val = $row['max_val'] ?? 0;
    $min_val = $row['min_val'] ?? 0;

    // رسم بياني مبسط (جلب بيانات يومية)
    $chart_data = [];
    $q_chart = "SELECT DATE(created_at) as d, SUM(CASE WHEN direction='in' THEN amount ELSE 0 END) as in_sum, SUM(CASE WHEN direction='out' THEN amount ELSE 0 END) as out_sum
    FROM cashbox_transactions
    $where
    GROUP BY DATE(created_at)
    ORDER BY d ASC";
    $res_chart = mysqli_query($conn, $q_chart);
    while($r = mysqli_fetch_assoc($res_chart)) {
        $chart_data[] = ['date'=>$r['d'], 'in'=>floatval($r['in_sum']), 'out'=>floatval($r['out_sum'])];
    }
    ?>
    <!-- ملخص سريع -->
    <div class="row mb-4 text-center">
        <div class="col-md-2">
            <div class="summary-box in">
                <span class="title"><i class="fa-solid fa-arrow-down text-success"></i> الإيرادات</span><br>
                <span><?= number_format($total_in, 2) ?> جنيه</span>
            </div>
        </div>
        <div class="col-md-2">
            <div class="summary-box out">
                <span class="title"><i class="fa-solid fa-arrow-up text-danger"></i> المصروفات</span><br>
                <span><?= number_format($total_out, 2) ?> جنيه</span>
            </div>
        </div>
        <div class="col-md-2">
            <div class="summary-box net">
                <span class="title"><i class="fa-solid fa-calculator"></i> الصافي</span><br>
                <span><?= number_format($net, 2) ?> جنيه</span>
            </div>
        </div>
        <div class="col-md-2">
            <div class="summary-box" style="background:#f3e5f5;">
                <span class="title"><i class="fa-solid fa-list-ol"></i> عدد الحركات</span><br>
                <span><?= $total_count ?></span>
            </div>
        </div>
        <div class="col-md-2">
            <div class="summary-box" style="background:#fffde7;">
                <span class="title"><i class="fa-solid fa-sort-numeric-up"></i> أعلى مبلغ</span><br>
                <span><?= number_format($max_val, 2) ?> جنيه</span>
            </div>
        </div>
        <div class="col-md-2">
            <div class="summary-box" style="background:#e0f2f1;">
                <span class="title"><i class="fa-solid fa-sort-numeric-down"></i> أقل مبلغ</span><br>
                <span><?= number_format($min_val, 2) ?> جنيه</span>
            </div>
        </div>
    </div>
    <!-- رسم بياني مبسط -->
    <div class="chart-container mb-4">
        <canvas id="reportChart"></canvas>
    </div>
    <script>
    // رسم بياني للإيرادات والمصروفات اليومي
    const chartData = <?= json_encode($chart_data) ?>;
    const labels = chartData.map(e=>e.date);
    const inData = chartData.map(e=>e.in);
    const outData = chartData.map(e=>e.out);
    if(window.reportChartObj){ window.reportChartObj.destroy(); }
    const ctx = document.getElementById('reportChart').getContext('2d');
    window.reportChartObj = new Chart(ctx, {
        type: 'bar',
        data: {
            labels: labels,
            datasets: [
                {
                    label: 'إيرادات',
                    data: inData,
                    backgroundColor: '#43e97b',
                },
                {
                    label: 'مصروفات',
                    data: outData,
                    backgroundColor: '#c62828',
                }
            ]
        },
        options: {
            responsive:true,
            plugins:{legend:{rtl:true,labels:{font:{size:14}}}},
            scales: {
                x: {title: {display:true, text:'التاريخ'}},
                y: {title: {display:true, text:'المبلغ'}, beginAtZero:true}
            }
        }
    });
    </script>
    <div class="section-title">تفصيل الحركات المالية في الفترة المختارة</div>
    <div class="table-responsive sticky-header" id="report-table-area">
        <table class="table table-bordered table-striped text-end align-middle" id="reportTable">
            <thead>
                <tr>
                    <th>المعرف</th>
                    <th>النوع</th>
                    <th>الطرف</th>
                    <th>المبلغ</th>
                    <th>الاتجاه</th>
                    <th>ملاحظات</th>
                    <th>التاريخ</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $details = mysqli_query($conn, "
                    SELECT id, type, relation_id, amount, direction, notes, created_at
                    $where
                    ORDER BY created_at DESC
                ");
                while($r = mysqli_fetch_assoc($details)): ?>
                <tr>
                    <td><?= $r['id'] ?></td>
                    <td><?= $r['type']=='customer' ? '<span class="badge bg-info">عميل</span>' : ($r['type']=='agent' ? '<span class="badge bg-warning text-dark">وكيل</span>' : '<span class="badge bg-secondary">'.$r['type'].'</span>') ?></td>
                    <td>
                        <?php
                        if($r['type']=='customer' && isset($names['customer'][$r['relation_id']]))
                            echo htmlspecialchars($names['customer'][$r['relation_id']]) . " <span class='text-muted'>(ID:".$r['relation_id'].")</span>";
                        elseif($r['type']=='agent' && isset($names['agent'][$r['relation_id']]))
                            echo htmlspecialchars($names['agent'][$r['relation_id']]) . " <span class='text-muted'>(ID:".$r['relation_id'].")</span>";
                        else
                            echo $r['relation_id'];
                        ?>
                    </td>
                    <td><?= number_format($r['amount'],2) ?> جنيه</td>
                    <td><?= $r['direction']=='in' ? '<span class="badge bg-success">داخل</span>' : '<span class="badge bg-danger">خارج</span>' ?></td>
                    <td>
                        <?php
                        if(strlen($r['notes'])>20)
                            echo '<span title="'.htmlspecialchars($r['notes']).'">'.htmlspecialchars(mb_substr($r['notes'],0,20)).'...</span>';
                        else
                            echo htmlspecialchars($r['notes']);
                        ?>
                    </td>
                    <td><?= date('Y-m-d H:i', strtotime($r['created_at'])) ?></td>
                </tr>
                <?php endwhile; ?>
                <?php if(mysqli_num_rows($details)==0): ?>
                <tr>
                    <td colspan="7" class="text-center">لا يوجد حركات مالية في الفترة المختارة</td>
                </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php
    exit;
}
?>

<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.rtl.min.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
body {background: #f4f6fb; font-family: 'Tajawal', Arial, sans-serif; direction: rtl;}
.section-card {background:#fff; border-radius:18px; box-shadow:0 4px 18px #ddeafc33; margin-bottom:32px; padding:32px 24px 20px 24px;}
.section-title {font-weight:bold; font-size:1.35em; color:#1976d2; margin-bottom:24px;}
.table-responsive {border-radius:18px;}
.table thead th {background:#e3f6ff; color:#1565c0;}
.table {border-radius:16px; overflow:hidden;}
th, td {text-align:right !important;}
.sticky-header thead th {position:sticky; top:0; background:#e3f6ff;}
.badge {font-size:1em;}
.summary-box {padding:18px 12px; border-radius:12px;}
.summary-box.in {background:#e3f6ff;}
.summary-box.out {background:#ffebee;}
.summary-box.net {background:#e1f5fe;}
.summary-box .title {font-weight:bold;}
.chart-container {height:350px;}
@media (max-width: 576px) {
    .section-card {padding:14px 4px 8px 4px;}
}
</style>

<div class="container py-4">
    <div class="section-card">
        <div class="section-title">
            <i class="fa-solid fa-chart-line"></i> التقارير المالية المفصلة
        </div>
        <form class="row g-2 mb-4 align-items-end" id="reportFilterForm" autocomplete="off">
            <div class="col-md-2">
                <label class="form-label text-end">من</label>
                <input type="date" name="from" class="form-control" required>
            </div>
            <div class="col-md-2">
                <label class="form-label text-end">إلى</label>
                <input type="date" name="to" class="form-control" required>
            </div>
            <div class="col-md-2">
                <label class="form-label text-end">نوع الحركة</label>
                <select name="direction" class="form-select">
                    <option value="">الكل</option>
                    <option value="in">داخل</option>
                    <option value="out">خارج</option>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label text-end">نوع الطرف</label>
                <select name="type" id="typeSelect" class="form-select">
                    <option value="">الكل</option>
                    <option value="customer">عميل</option>
                    <option value="agent">وكيل</option>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label text-end">الطرف</label>
                <select name="relation_id" id="relationSelect" class="form-select">
                    <option value="">الكل</option>
                </select>
            </div>
            <div class="col-md-1">
                <label class="form-label text-end">مبلغ من</label>
                <input type="number" step="0.01" min="0" name="min_amount" class="form-control">
            </div>
            <div class="col-md-1">
                <label class="form-label text-end">مبلغ إلى</label>
                <input type="number" step="0.01" min="0" name="max_amount" class="form-control">
            </div>
            <div class="col-md-12 text-end mt-2">
                <button type="submit" class="btn btn-info"><i class="fa-solid fa-filter"></i> فلترة</button>
                <button type="button" class="btn btn-secondary" id="resetBtn"><i class="fa-solid fa-rotate-left"></i> إعادة ضبط</button>
                <button type="button" class="btn btn-success" onclick="exportTableToExcel('report-table-area')"><i class="fa-solid fa-file-excel"></i> تصدير Excel</button>
                <button type="button" class="btn btn-primary" onclick="printDiv('report-table-area')"><i class="fa-solid fa-print"></i> طباعة</button>
            </div>
        </form>
        <div id="reportContent">
            <!-- سيتم تحميل الملخص والجدول والرسم البياني هنا عبر AJAX -->
        </div>
    </div>
</div>

<!-- مكتبة رسم بياني Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
const customers = <?= json_encode($customers) ?>;
const agents = <?= json_encode($agents) ?>;

document.addEventListener('DOMContentLoaded', function(){
    // إعداد الفلاتر الافتراضية
    let today = new Date().toISOString().slice(0,10);
    let firstDay = today.substring(0,8)+'01';
    document.querySelector('[name="from"]').value = firstDay;
    document.querySelector('[name="to"]').value = today;

    // تحميل أولي للبيانات
    loadReport();

    // فلترة ديناميكية
    document.getElementById('reportFilterForm').addEventListener('submit', function(e){
        e.preventDefault();
        loadReport();
    });

    // إعادة ضبط الفلاتر
    document.getElementById('resetBtn').addEventListener('click', function(){
        document.getElementById('reportFilterForm').reset();
        document.querySelector('[name="from"]').value = firstDay;
        document.querySelector('[name="to"]').value = today;
        document.getElementById('relationSelect').innerHTML = '<option value="">الكل</option>';
        loadReport();
    });

    // تحديث خيارات الطرف حسب نوع الطرف
    document.getElementById('typeSelect').addEventListener('change', function(){
        let type = this.value;
        let relationSelect = document.getElementById('relationSelect');
        relationSelect.innerHTML = '<option value="">الكل</option>';
        if(type=='customer'){
            customers.forEach(function(c){
                relationSelect.innerHTML += '<option value="'+c.id+'">'+c.name+'</option>';
            });
        }else if(type=='agent'){
            agents.forEach(function(a){
                relationSelect.innerHTML += '<option value="'+a.id+'">'+a.company_name+'</option>';
            });
        }
    });
});

// تحميل التقرير عبر AJAX
function loadReport(){
    let form = document.getElementById('reportFilterForm');
    let data = new FormData(form);
    data.append('ajax','1');
    fetch('financial_reports.php', {
        method:'POST',
        body:data
    })
    .then(res=>res.text())
    .then(html=>{
        document.getElementById('reportContent').innerHTML = html;
    });
}

// تصدير الجدول Excel
function exportTableToExcel(divId){
    var table = document.getElementById(divId).getElementsByTagName('table')[0];
    var html = table.outerHTML.replace(/ /g, '%20');
    var a = document.createElement('a');
    a.href = 'data:application/vnd.ms-excel,' + html;
    a.download = 'التقرير_المالي.xls';
    a.click();
}

// طباعة التقرير
function printDiv(divId){
    var contents = document.getElementById(divId).innerHTML;
    var printWindow = window.open('', '', 'height=700,width=900');
    printWindow.document.write('<html><head><title>التقرير المالي</title>');
    printWindow.document.write('<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.rtl.min.css" rel="stylesheet">');
    printWindow.document.write('</head><body dir="rtl">' + contents + '</body></html>');
    printWindow.document.close();
    printWindow.focus();
    printWindow.print();
    printWindow.close();
}
</script>