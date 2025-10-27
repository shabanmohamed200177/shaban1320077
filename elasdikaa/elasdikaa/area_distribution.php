<?php
include 'db_connect.php';

$areas = $conn->query("SELECT a.*, g.name as governorate_name FROM areas a LEFT JOIN governorates g ON a.governorate_id = g.id ORDER BY g.name, a.name ASC");
?>

<!DOCTYPE html>
<html lang="ar">
<head>
    <meta charset="UTF-8">
    <title>توزيع المناطق على المناديب</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.rtl.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { background: #f7faff; font-family: 'Tajawal', Arial, sans-serif; direction: rtl; }
        .modern-card { background: #fff; border-radius: 18px; box-shadow: 0 2px 16px #ddeafc33; padding: 28px 24px; margin-bottom: 1.5rem; }
        .table thead { background: #eaf2fb; }
        .table tbody tr:hover { background: #f3f8ff; }
        .area-badge { font-size: .98em; padding: 0.3em 0.8em; border-radius: 1em; background: #e3f2fd; color: #1976d2; margin-left: 4px;}
        .btn-manage { border-radius: 1.25em !important; font-weight: 500; }
        .no-courier { color: #e53935; font-weight: bold; }
    </style>
</head>
<body>
<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h2 class="mb-0"><i class="fa-solid fa-globe"></i> توزيع المناطق على المناديب</h2>
        <button class="btn btn-primary btn-manage" onclick="location.reload();"><i class="fa fa-refresh"></i> تحديث</button>
    </div>
    <div class="modern-card">
        <div class="mb-3 d-flex flex-wrap gap-2">
            <input type="text" id="search-area" class="form-control flex-grow-1" placeholder="ابحث عن منطقة أو محافظة...">
            <select id="governorate-filter" class="form-select" style="max-width: 220px;">
                <option value="">كل المحافظات</option>
                <?php
                $govs = $conn->query("SELECT * FROM governorates ORDER BY name ASC");
                while($gov = $govs->fetch_assoc()): ?>
                    <option value="<?php echo $gov['name'] ?>"><?php echo $gov['name'] ?></option>
                <?php endwhile; ?>
            </select>
        </div>
        <div class="table-responsive">
        <table class="table table-hover align-middle" id="areas-table">
            <thead>
                <tr>
                    <th>المحافظة</th>
                    <th>اسم المنطقة</th>
                    <th>عدد المناديب</th>
                    <th>المناديب المرتبطين</th>
                    <th>الحالة</th>
                    <th>إدارة</th>
                </tr>
            </thead>
            <tbody>
            <?php while($area = $areas->fetch_assoc()): 
                $area_id = $area['id'];
                // احضر بيانات المناديب المرتبطين
                $couriers = $conn->query("SELECT c.name, c.status FROM courier_areas ca LEFT JOIN couriers c ON ca.courier_id=c.id WHERE ca.area_id=$area_id");
                $courier_arr = [];
                $active_count = 0;
                while($c = $couriers->fetch_assoc()){
                    $courier_arr[] = [
                        'name' => $c['name'],
                        'status' => $c['status']
                    ];
                    if($c['status']==1) $active_count++;
                }
            ?>
                <tr data-area="<?php echo $area['name'] ?>" data-gov="<?php echo $area['governorate_name'] ?>">
                    <td><?php echo htmlspecialchars($area['governorate_name']); ?></td>
                    <td><span class="area-badge"><?php echo htmlspecialchars($area['name']); ?></span></td>
                    <td><?php echo count($courier_arr); ?></td>
                    <td>
                        <?php if(count($courier_arr)): ?>
                            <?php foreach($courier_arr as $cr): ?>
                                <span class="badge 
                                    <?php echo $cr['status']==1 ? 'bg-success' : 'bg-secondary'; ?> mx-1 mb-1">
                                    <?php echo htmlspecialchars($cr['name']); ?>
                                    <?php echo $cr['status']==1 ? '<i class="fa fa-check-circle"></i>' : '<i class="fa fa-ban"></i>'; ?>
                                </span>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <span class="no-courier">لا يوجد مندوب</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if(count($courier_arr)==0): ?>
                            <span class="badge bg-danger">بدون مندوب</span>
                        <?php else: ?>
                            <span class="badge bg-<?php echo $active_count>0?'info':'secondary'; ?>">
                                <?php echo $active_count>0 ? "نشط" : "كل المناديب موقوفين"; ?>
                            </span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <button class="btn btn-outline-primary btn-manage btn-sm" data-areaid="<?php echo $area_id; ?>" onclick="manageCouriers(<?php echo $area_id; ?>)">
                            <i class="fa fa-users-cog"></i> إدارة المندوبين
                        </button>
                    </td>
                </tr>
            <?php endwhile; ?>
            </tbody>
        </table>
        </div>
    </div>
</div>

<!-- Modal إدارة المندوبين -->
<div class="modal fade" id="manageCourierModal" tabindex="-1" aria-labelledby="manageCourierModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content" id="modal-content-courier">
        <!-- سيتم تحميل البيانات هنا عبر AJAX -->
    </div>
  </div>
</div>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
function manageCouriers(area_id) {
    $.get('manage_courier_area_modal.php?area_id='+area_id, function(data){
        $('#modal-content-courier').html(data);
        var modal = new bootstrap.Modal(document.getElementById('manageCourierModal'));
        modal.show();
    });
}

// فلترة وبحث ديناميكي
$('#search-area').on('keyup', function(){
    let q = $(this).val().trim();
    $('#areas-table tbody tr').each(function(){
        let area = $(this).data('area');
        let gov = $(this).data('gov');
        if(area.includes(q) || gov.includes(q)) {
            $(this).show();
        } else {
            $(this).hide();
        }
    });
});
$('#governorate-filter').on('change', function(){
    let gov = $(this).val();
    $('#areas-table tbody tr').each(function(){
        if(gov=="" || $(this).data('gov')==gov) {
            $(this).show();
        } else {
            $(this).hide();
        }
    });
});
</script>
</body>
</html>