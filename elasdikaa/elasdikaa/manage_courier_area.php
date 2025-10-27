<?php
include 'db_connect.php';
$area_id = intval($_GET['area_id']);
$area = $conn->query("SELECT * FROM areas WHERE id=$area_id")->fetch_assoc();
$couriers = $conn->query("SELECT * FROM couriers ORDER BY name ASC");
$courier_areas = $conn->query("SELECT courier_id FROM courier_areas WHERE area_id=$area_id");
$linked_couriers = [];
while($ca = $courier_areas->fetch_assoc()) $linked_couriers[] = $ca['courier_id'];
?>
<div class="modal-header bg-info text-white">
    <h5 class="modal-title" id="manageCourierModalLabel">
        <i class="fa fa-users-cog"></i> إدارة المناديب لمنطقة: 
        <span class="badge bg-light text-dark"><?php echo htmlspecialchars($area['name']); ?></span>
    </h5>
    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="إغلاق"></button>
</div>
<form method="post" id="courier-area-form">
    <div class="modal-body">
        <div class="row">
            <?php while($courier = $couriers->fetch_assoc()): ?>
                <div class="col-md-4 mb-2">
                    <label class="d-flex align-items-center gap-2">
                        <input type="checkbox" name="couriers[]" value="<?php echo $courier['id']; ?>"
                        <?php if(in_array($courier['id'], $linked_couriers)) echo 'checked'; ?>>
                        <span class="badge bg-<?php echo $courier['status']==1?'success':'secondary'; ?>">
                            <?php echo htmlspecialchars($courier['name']); ?>
                            <?php echo $courier['status']==1 ? '<i class="fa fa-check-circle"></i>' : '<i class="fa fa-ban"></i>'; ?>
                        </span>
                    </label>
                </div>
            <?php endwhile; ?>
        </div>
        <div id="courier-area-msg" class="mt-2"></div>
    </div>
    <div class="modal-footer">
        <button type="submit" class="btn btn-primary"><i class="fa fa-save"></i> حفظ التغييرات</button>
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إغلاق</button>
    </div>
    <input type="hidden" name="area_id" value="<?php echo $area_id; ?>">
</form>
<script>
$('#courier-area-form').submit(function(e){
    e.preventDefault();
    var data = $(this).serialize();
    $.post('save_courier_area.php', data, function(resp){
        $('#courier-area-msg').html('<div class="alert alert-success">تم حفظ التغييرات بنجاح</div>');
        setTimeout(function(){location.reload()}, 1000);
    });
});
</script>