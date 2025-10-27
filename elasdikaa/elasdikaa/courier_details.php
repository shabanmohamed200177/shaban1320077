<?php
include 'db_connect.php';
ini_set('display_errors', 1);
error_reporting(E_ALL);

// حالة التسليم الناجح
$DELIVERED_STATUS = 4;

// التحقق من وجود رقم المندوب
if (!isset($_GET['id']) || empty($_GET['id'])) {
    echo "<div class='alert alert-danger'>رقم المندوب غير موجود.</div>";
    exit;
}
$courier_id = intval($_GET['id']);

// تاريخ الفلترة (افتراضي اليوم)
$filter_date = isset($_GET['date']) && !empty($_GET['date']) ? $_GET['date'] : date('Y-m-d');

// بيانات المندوب
$courier_qry = $conn->query("SELECT * FROM couriers WHERE id = {$courier_id}");
if (!$courier_qry || $courier_qry->num_rows == 0) {
    echo "<div class='alert alert-danger'>المندوب غير موجود.</div>";
    exit;
}
$courier = $courier_qry->fetch_assoc();

// جلب أسماء الحالات بالعربي + ألوان البادج
$status_names = [];
$status_colors = [];
$row_colors = [];

$status_qry = $conn->query("SELECT id, name_ar FROM parcel_status");
while($srow = $status_qry->fetch_assoc()){
    $status_names[$srow['id']] = $srow['name_ar'];
    switch($srow['id']){
        case 1: $status_colors[$srow['id']] = 'badge badge-warning'; $row_colors[$srow['id']] = '#fff7e6'; break;
        case 2: $status_colors[$srow['id']] = 'badge badge-info'; $row_colors[$srow['id']] = '#e6f7ff'; break;
        case 3: $status_colors[$srow['id']] = 'badge badge-primary'; $row_colors[$srow['id']] = '#e6f7ff'; break;
        case 4: $status_colors[$srow['id']] = 'badge badge-success'; $row_colors[$srow['id']] = '#e6ffed'; break;
        case 5: $status_colors[$srow['id']] = 'badge badge-success'; $row_colors[$srow['id']] = '#e6ffed'; break;
        case 6: $status_colors[$srow['id']] = 'badge badge-secondary'; $row_colors[$srow['id']] = '#f0f0f0'; break;
        case 7: $status_colors[$srow['id']] = 'badge badge-warning'; $row_colors[$srow['id']] = '#fff7e6'; break;
        case 8: $status_colors[$srow['id']] = 'badge badge-danger'; $row_colors[$srow['id']] = '#ffe6e6'; break;
        case 9: $status_colors[$srow['id']] = 'badge badge-dark'; $row_colors[$srow['id']] = '#f2f2f2'; break;
        case 10: $status_colors[$srow['id']] = 'badge badge-dark'; $row_colors[$srow['id']] = '#f2f2f2'; break;
        default: $status_colors[$srow['id']] = 'badge badge-secondary'; $row_colors[$srow['id']] = '#ffffff';
    }
}

// فلترة حسب الحالة
$status_filter = isset($_GET['status']) ? intval($_GET['status']) : -1;
$status_condition = ($status_filter >= 0) 
    ? "AND status = {$status_filter}" 
    : "AND status != {$DELIVERED_STATUS}";

// الإحصائيات
$stats_sql = "
    SELECT 
        IFNULL(SUM(total_to_collect),0) AS total_with_courier,
        IFNULL(SUM(delivery_agent_fee),0) AS total_commission,
        IFNULL(SUM(company_profit),0) AS my_share,
        IFNULL(SUM(CASE WHEN status = {$DELIVERED_STATUS} THEN total_to_collect ELSE 0 END),0) AS delivered_value,
        IFNULL(SUM(total_to_collect),0) AS total_to_collect
    FROM parcels
    WHERE courier_id = {$courier_id}
    AND DATE(date_created) = '{$filter_date}'
";
$stats = $conn->query($stats_sql)->fetch_assoc();

// جلب الشحنات
$parcels_sql = "
    SELECT * FROM parcels
    WHERE courier_id = {$courier_id}
    {$status_condition}
    AND DATE(date_created) = '{$filter_date}'
    ORDER BY date_created DESC
";
$parcels_qry = $conn->query($parcels_sql);
?>

<div class="col-lg-12">
    <div class="card card-outline card-primary">
        <div class="card-header">
            <h4>📅 شحنات المندوب (يومية) - <?php echo htmlspecialchars($courier['name']); ?></h4>
            <a href="index.php?page=couriers_list" class="btn btn-secondary btn-sm float-right">رجوع للقائمة</a>
        </div>
        <div class="card-body">
            <p><strong>الاسم:</strong> <?php echo htmlspecialchars($courier['name']); ?> | 
               <strong>الجوال:</strong> <?php echo htmlspecialchars($courier['phone']); ?></p>
            <hr>

            <!-- فورم فلترة -->
            <form method="get" class="form-inline mb-3">
                <input type="hidden" name="page" value="courier_details">
                <input type="hidden" name="id" value="<?php echo $courier_id; ?>">
                <label class="mr-2">التاريخ:</label>
                <input type="date" name="date" value="<?php echo $filter_date; ?>" class="form-control mr-2">
                <label class="mr-2">الحالة:</label>
                <select name="status" class="form-control mr-2">
                    <option value="-1" <?php echo ($status_filter==-1)?'selected':''; ?>>غير المسلمة</option>
                    <?php foreach($status_names as $code=>$name): ?>
                        <option value="<?php echo $code; ?>" <?php echo ($status_filter==$code)?'selected':''; ?>>
                            <?php echo htmlspecialchars($name); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <button type="submit" class="btn btn-primary">عرض</button>
            </form>

            <!-- الإحصائيات -->
            <div class="row text-center">
                <div class="col-md-3 mb-3">
                    <div class="card bg-info text-white">
                        <div class="card-body">
                            <h6>إجمالي الشحنات</h6>
                            <h4><?php echo number_format($stats['total_with_courier'],2); ?> ج</h4>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 mb-3">
                    <div class="card bg-success text-white">
                        <div class="card-body">
                            <h6>عمولة المندوب</h6>
                            <h4><?php echo number_format($stats['total_commission'],2); ?> ج</h4>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 mb-3">
                    <div class="card bg-warning text-dark">
                        <div class="card-body">
                            <h6>نسبتي من عمولته</h6>
                            <h4><?php echo number_format($stats['my_share'],2); ?> ج</h4>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 mb-3">
                    <div class="card bg-primary text-white">
                        <div class="card-body">
                            <h6>المطلوب تحصيله</h6>
                            <h4><?php echo number_format($stats['total_to_collect'],2); ?> ج</h4>
                        </div>
                    </div>
                </div>
            </div>

            <!-- جدول الشحنات -->
            <table class="table table-bordered table-hover">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>رقم التتبع</th>
                        <th>المرسل</th>
                        <th>المستلم</th>
                        <th>إجمالي المطلوب</th>
                        <th>رسوم الشحن</th>
                        <th>عمولة المندوب</th>
                        <th>نسبتي</th>
                        <th>المطلوب تحصيله</th>
                        <th>التاريخ</th>
                        <th>الحالة</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $i=1;
                    if($parcels_qry->num_rows > 0):
                        while($row = $parcels_qry->fetch_assoc()):
                            $status_code = $row['status'];
                            $status_label = $status_names[$status_code] ?? 'غير معروف';
                            $status_class = $status_colors[$status_code] ?? 'badge badge-secondary';
                            $bg_color = $row_colors[$status_code] ?? '#fff';
                    ?>
                    <tr style="background-color: <?php echo $bg_color; ?>">
                        <td><?php echo $i++; ?></td>
                        <td><?php echo htmlspecialchars($row['tracking_number']); ?></td>
                        <td><?php echo htmlspecialchars($row['sender_name']); ?></td>
                        <td><?php echo htmlspecialchars($row['recipient_name']); ?></td>
                        <td class="text-primary"><?php echo number_format($row['total_to_collect'],2); ?> ج</td>
                        <td class="text-primary"><?php echo number_format($row['shipping_fees'],2); ?> ج</td>
                        <td class="text-success"><?php echo number_format($row['delivery_agent_fee'],2); ?> ج</td>
                        <td class="text-success"><?php echo number_format($row['company_profit'],2); ?> ج</td>
                        <td class="text-danger"><?php echo number_format($row['total_to_collect'],2); ?> ج</td>
                        <td><?php echo $row['date_created']; ?></td>
                        <td><span class="<?php echo $status_class; ?>"><?php echo htmlspecialchars($status_label); ?></span></td>
                    </tr>
                    <?php 
                        endwhile;
                    else:
                    ?>
                    <tr><td colspan="11" class="text-center">لا توجد شحنات في هذا التاريخ</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
