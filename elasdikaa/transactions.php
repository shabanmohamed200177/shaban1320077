<?php
include 'db_connect.php';

// استقبال قيم البحث من العنوان (GET) أو (POST)
$type = $_POST['type'] ?? '';
$direction = $_POST['direction'] ?? '';
$relation_id = $_POST['relation_id'] ?? '';
$from = $_POST['from'] ?? '';
$to = $_POST['to'] ?? '';
$search = $_POST['search'] ?? '';

// بناء استعلام البحث
$where = "WHERE 1";
if($type) $where .= " AND type='$type'";
if($direction) $where .= " AND direction='$direction'";
if($relation_id) $where .= " AND relation_id='" . intval($relation_id) . "'";
if($from) $where .= " AND DATE(created_at)>='$from'";
if($to) $where .= " AND DATE(created_at)<='$to'";
if($search) {
    if($type=='customer') {
        $where .= " AND (relation_id IN (SELECT id FROM customers WHERE name LIKE '%$search%') OR notes LIKE '%$search%')";
    } elseif($type=='agent') {
        $where .= " AND (relation_id IN (SELECT id FROM agents WHERE company_name LIKE '%$search%') OR notes LIKE '%$search%')";
    } elseif($type=='courier') {
        $where .= " AND (relation_id IN (SELECT id FROM couriers WHERE name LIKE '%$search%') OR notes LIKE '%$search%')";
    } else {
        $where .= " AND (notes LIKE '%$search%' OR relation_id LIKE '%$search%')";
    }
}

// جلب العملاء والوكلاء والمناديب
$customers = mysqli_query($conn, "SELECT id, name FROM customers ORDER BY name");
$agents = mysqli_query($conn, "SELECT id, company_name FROM agents ORDER BY company_name");
$couriers = mysqli_query($conn, "SELECT id, name FROM couriers ORDER BY name");

// جلب الحركات المالية مع تفاصيل الطرف
$q = "
SELECT t.id, t.type, t.relation_id, t.amount, t.direction, t.notes, t.created_at,
    CASE 
        WHEN t.type='customer' THEN (SELECT name FROM customers WHERE id=t.relation_id)
        WHEN t.type='agent' THEN (SELECT company_name FROM agents WHERE id=t.relation_id)
        WHEN t.type='courier' THEN (SELECT name FROM couriers WHERE id=t.relation_id)
        ELSE 'النظام' 
    END AS party_name
FROM cashbox_transactions t
$where
ORDER BY t.created_at DESC
LIMIT 200
";
$res = mysqli_query($conn, $q);
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <title>سجل الحركات المالية | الخزنة</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <!-- Bootstrap & FontAwesome -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.rtl.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        body {background: #f4f6fb; font-family: 'Tajawal', Arial, sans-serif; direction: rtl;}
        .section-card {background:#fff; border-radius:18px; box-shadow:0 4px 18px #ddeafc33; margin-bottom:32px; padding:32px 28px 20px 28px; direction:rtl;}
        .section-title {font-weight:bold; font-size:1.35em; color:#1976d2; margin-bottom:24px; border-bottom:2px solid #e3f6ff; padding-bottom:12px;}
        .modern-btn {border-radius:1.5rem !important; font-weight:600;}
        .form-select, .form-control {border-radius:1.2rem !important;}
        .search-form .form-control, .search-form .form-select {min-width:120px;}
        .table-responsive {border-radius:18px;}
        .table thead th {background:#e3f6ff; color:#1565c0;}
        .table {border-radius:16px; overflow:hidden;}
        .table td, .table th {vertical-align:middle;}
        .badge {font-size:1em;}
        .no-results {color:#888; padding:20px;}
        th, td {text-align:right !important;}
        .card-header, .card-body, .table th, .table td {direction:rtl;}
        .form-label, .filter-label {text-align:right; display:block;}
        .search-form label {display:block;}
        .btn-main-vault {position:absolute; left:32px; top:32px;}
        @media (max-width: 576px) {
            .section-card {padding:16px 8px 8px 8px;}
            .btn-main-vault {position:static; left:auto; top:auto; margin-bottom:16px;}
        }
    </style>
</head>
<body>
<div class="container py-4" style="direction:rtl;">
    <!-- زر الخزنة الرئيسية -->
    <a href="main_vault.php" class="btn btn-success modern-btn btn-main-vault"><i class="fa-solid fa-vault"></i> الخزنة الرئيسية</a>
    
    <!-- قسم البحث والتصفية -->
    <div class="section-card mb-4">
        <div class="section-title"><i class="fa-solid fa-filter"></i> بحث وتصفية الحركات المالية</div>
        <form method="post" class="row g-3 mb-2 search-form" id="searchForm" autocomplete="off" style="direction:rtl;">
            <div class="col-md-2">
                <label class="filter-label"><i class="fa-solid fa-tag"></i> النوع</label>
                <select name="type" class="form-select" id="type_select">
                    <option value="">الكل</option>
                    <option value="customer" <?= $type=='customer'?'selected':'' ?>>عميل</option>
                    <option value="agent" <?= $type=='agent'?'selected':'' ?>>وكيل</option>
                    <option value="courier" <?= $type=='courier'?'selected':'' ?>>مندوب</option>
                    <!-- تم حذف خيار مشروع نهائيًا -->
                </select>
            </div>
            <div class="col-md-2">
                <label class="filter-label"><i class="fa-solid fa-arrows-left-right"></i> الاتجاه</label>
                <select name="direction" class="form-select">
                    <option value="">الكل</option>
                    <option value="in" <?= $direction=='in'?'selected':'' ?>>داخل</option>
                    <option value="out" <?= $direction=='out'?'selected':'' ?>>خارج</option>
                </select>
            </div>
            <div class="col-md-2" id="relation_id_container">
                <!-- سيتم وضع الحقل المناسب هنا بجافاسكريبت -->
            </div>
            <div class="col-md-2">
                <label class="filter-label"><i class="fa-regular fa-calendar"></i> من</label>
                <input type="date" name="from" class="form-control" value="<?= htmlspecialchars($from) ?>">
            </div>
            <div class="col-md-2">
                <label class="filter-label"><i class="fa-regular fa-calendar"></i> إلى</label>
                <input type="date" name="to" class="form-control" value="<?= htmlspecialchars($to) ?>">
            </div>
            <div class="col-md-2">
                <label class="filter-label"><i class="fa-solid fa-magnifying-glass"></i> بحث</label>
                <input type="text" name="search" class="form-control" placeholder="بحث بالاسم أو ملاحظات" value="<?= htmlspecialchars($search) ?>">
            </div>
            <div class="col-md-12 text-end mt-2">
                <button type="submit" class="btn btn-primary modern-btn"><i class="fa-solid fa-filter"></i> بحث / تصفية</button>
                <button type="button" class="btn btn-secondary modern-btn" id="resetBtn"><i class="fa-solid fa-rotate-left"></i> إعادة ضبط</button>
            </div>
        </form>
    </div>

    <!-- قسم عرض النتائج -->
    <div class="section-card">
        <div class="section-title"><i class="fa-solid fa-list"></i> نتائج الحركات المالية</div>
        <div id="table-area">
            <div class="table-responsive">
                <table class="table table-bordered table-striped text-end">
                    <thead>
                        <tr>
                            <th>المعرف</th>
                            <th>النوع</th>
                            <th>اسم الطرف</th>
                            <th>المبلغ</th>
                            <th>الاتجاه</th>
                            <th>ملاحظات</th>
                            <th>التاريخ</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while($row = mysqli_fetch_assoc($res)): ?>
                        <tr>
                            <td><?= $row['id'] ?></td>
                            <td>
                                <?php
                                if($row['type']=='customer') echo '<span class="badge bg-success"><i class="fa-solid fa-user"></i> عميل</span>';
                                elseif($row['type']=='agent') echo '<span class="badge bg-primary"><i class="fa-solid fa-user-tie"></i> وكيل</span>';
                                elseif($row['type']=='courier') echo '<span class="badge bg-warning text-dark"><i class="fa-solid fa-person-biking"></i> مندوب</span>';
                                else echo '<span class="badge bg-info text-dark"><i class="fa-solid fa-building"></i> النظام</span>';
                                ?>
                            </td>
                            <td><?= $row['party_name'] ?: '-' ?></td>
                            <td><span class="fw-bold"><?= number_format($row['amount'],2) ?> جنيه</span></td>
                            <td>
                                <?= $row['direction']=='in' ? 
                                    '<span class="badge bg-success"><i class="fa-solid fa-arrow-down"></i> داخل</span>' : 
                                    '<span class="badge bg-danger"><i class="fa-solid fa-arrow-up"></i> خارج</span>' ?>
                            </td>
                            <td><?= htmlspecialchars($row['notes']) ?></td>
                            <td><?= date('Y-m-d H:i', strtotime($row['created_at'])) ?></td>
                        </tr>
                        <?php endwhile; ?>
                        <?php if(mysqli_num_rows($res)==0): ?>
                        <tr>
                            <td colspan="7" class="text-center no-results"><i class="fa-regular fa-face-frown"></i> لا يوجد نتائج</td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
<script>
function renderRelationIdField() {
    var type = document.getElementById('type_select').value;
    var container = document.getElementById('relation_id_container');
    container.innerHTML = '';

    <?php
    // تجهيز بيانات العملاء والوكلاء والمناديب كجافاسكريبت
    $js_customers = [];
    mysqli_data_seek($customers,0);
    while($row=mysqli_fetch_assoc($customers)) {
        $js_customers[] = ['id' => $row['id'], 'name' => htmlspecialchars($row['name'], ENT_QUOTES)];
    }
    $js_agents = [];
    mysqli_data_seek($agents,0);
    while($row=mysqli_fetch_assoc($agents)) {
        $js_agents[] = ['id' => $row['id'], 'name' => htmlspecialchars($row['company_name'], ENT_QUOTES)];
    }
    $js_couriers = [];
    mysqli_data_seek($couriers,0);
    while($row=mysqli_fetch_assoc($couriers)) {
        $js_couriers[] = ['id' => $row['id'], 'name' => htmlspecialchars($row['name'], ENT_QUOTES)];
    }
    ?>
    var selectedId = "<?= htmlspecialchars($relation_id) ?>";
    if(type == "customer") {
        var select = document.createElement('select');
        select.className = 'form-select';
        select.name = 'relation_id';
        var opt = document.createElement('option');
        opt.value = '';
        opt.text = 'كل العملاء';
        select.appendChild(opt);
        var customers = <?= json_encode($js_customers) ?>;
        customers.forEach(function(c){
            var option = document.createElement('option');
            option.value = c.id;
            option.text = c.name;
            if(selectedId == c.id) option.selected = true;
            select.appendChild(option);
        });
        container.appendChild(select);
    } else if(type == "agent") {
        var select = document.createElement('select');
        select.className = 'form-select';
        select.name = 'relation_id';
        var opt = document.createElement('option');
        opt.value = '';
        opt.text = 'كل الوكلاء';
        select.appendChild(opt);
        var agents = <?= json_encode($js_agents) ?>;
        agents.forEach(function(a){
            var option = document.createElement('option');
            option.value = a.id;
            option.text = a.name;
            if(selectedId == a.id) option.selected = true;
            select.appendChild(option);
        });
        container.appendChild(select);
    } else if(type == "courier") {
        var select = document.createElement('select');
        select.className = 'form-select';
        select.name = 'relation_id';
        var opt = document.createElement('option');
        opt.value = '';
        opt.text = 'كل المناديب';
        select.appendChild(opt);
        var couriers = <?= json_encode($js_couriers) ?>;
        couriers.forEach(function(c){
            var option = document.createElement('option');
            option.value = c.id;
            option.text = c.name;
            if(selectedId == c.id) option.selected = true;
            select.appendChild(option);
        });
        container.appendChild(select);
    } else {
        var input = document.createElement('input');
        input.className = 'form-control';
        input.name = 'relation_id';
        input.placeholder = 'رقم الطرف';
        input.value = selectedId ? selectedId : '';
        container.appendChild(input);
    }
}
renderRelationIdField();
document.getElementById('type_select').addEventListener('change', renderRelationIdField);

// دعم إعادة الضبط بدون إعادة تحميل الصفحة
document.getElementById('resetBtn').addEventListener('click', function() {
    document.getElementById('searchForm').reset();
    document.getElementById('type_select').dispatchEvent(new Event('change'));
    // أرسل الطلب لجلب كل النتائج بدون تصفية
    fetchResults({});
});

// دعم إظهار النتائج دون إعادة تحميل الصفحة عند الضغط على بحث/تصفيه
document.getElementById('searchForm').addEventListener('submit', function(e) {
    e.preventDefault();
    var formData = new FormData(this);
    var data = {};
    formData.forEach(function(value, key) {
        data[key] = value;
    });
    fetchResults(data);
});

// دالة جلب النتائج بالجافاسكريبت (AJAX)
function fetchResults(data) {
    var xhr = new XMLHttpRequest();
    xhr.open('POST', 'transactions.php', true);
    xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
    xhr.onload = function() {
        if(this.status == 200) {
            var html = this.responseText;
            var tableArea = document.getElementById('table-area');
            // جلب فقط الجدول من الرد
            var start = html.indexOf('<table');
            var end = html.indexOf('</table>')+8;
            if(start !== -1 && end !== -1) {
                tableArea.innerHTML = html.substring(start, end);
            }
        }
    };
    var params = new URLSearchParams(data).toString();
    xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
    xhr.send(params);
}

// دعم جلب الجدول فقط إذا كان الطلب AJAX
<?php
if ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '' == 'XMLHttpRequest') {
    ob_clean();
    ?>
    <div class="table-responsive">
        <table class="table table-bordered table-striped text-end">
            <thead>
                <tr>
                    <th>المعرف</th>
                    <th>النوع</th>
                    <th>اسم الطرف</th>
                    <th>المبلغ</th>
                    <th>الاتجاه</th>
                    <th>ملاحظات</th>
                    <th>التاريخ</th>
                </tr>
            </thead>
            <tbody>
                <?php while($row = mysqli_fetch_assoc($res)): ?>
                <tr>
                    <td><?= $row['id'] ?></td>
                    <td>
                        <?php
                        if($row['type']=='customer') echo '<span class="badge bg-success"><i class="fa-solid fa-user"></i> عميل</span>';
                        elseif($row['type']=='agent') echo '<span class="badge bg-primary"><i class="fa-solid fa-user-tie"></i> وكيل</span>';
                        elseif($row['type']=='courier') echo '<span class="badge bg-warning text-dark"><i class="fa-solid fa-person-biking"></i> مندوب</span>';
                        else echo '<span class="badge bg-info text-dark"><i class="fa-solid fa-building"></i> النظام</span>';
                        ?>
                    </td>
                    <td><?= $row['party_name'] ?: '-' ?></td>
                    <td><span class="fw-bold"><?= number_format($row['amount'],2) ?> جنيه</span></td>
                    <td>
                        <?= $row['direction']=='in' ? 
                            '<span class="badge bg-success"><i class="fa-solid fa-arrow-down"></i> داخل</span>' : 
                            '<span class="badge bg-danger"><i class="fa-solid fa-arrow-up"></i> خارج</span>' ?>
                    </td>
                    <td><?= htmlspecialchars($row['notes']) ?></td>
                    <td><?= date('Y-m-d H:i', strtotime($row['created_at'])) ?></td>
                </tr>
                <?php endwhile; ?>
                <?php if(mysqli_num_rows($res)==0): ?>
                <tr>
                    <td colspan="7" class="text-center no-results"><i class="fa-regular fa-face-frown"></i> لا يوجد نتائج</td>
                </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php
    exit;
}
?>
</script>
</body>
</html>