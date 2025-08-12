<?php
if(!isset($conn)){ include 'db_connect.php'; }
$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$customer = $conn->query("SELECT * FROM customers WHERE id = $id")->fetch_assoc();
?>
<div class="col-lg-6">
  <div class="card card-outline card-primary">
    <div class="card-body">
      <form id="manage-customer">
        <input type="hidden" name="id" value="<?php echo $customer['id'] ?>">
        <div id="msg" class=""></div>
        <div class="form-group">
          <label>اسم العميل</label>
          <input type="text" name="name" class="form-control" required value="<?php echo htmlspecialchars($customer['name']) ?>">
        </div>
        <div class="form-group">
          <label>رقم الجوال</label>
          <input type="text" name="phone" class="form-control" required value="<?php echo htmlspecialchars($customer['phone']) ?>">
        </div>
        <div class="form-group">
          <label>البريد الإلكتروني</label>
          <input type="email" name="email" class="form-control" value="<?php echo htmlspecialchars($customer['email']) ?>">
        </div>
        <div class="form-group">
          <label>العنوان</label>
          <input type="text" name="address" class="form-control" value="<?php echo htmlspecialchars($customer['address']) ?>">
        </div>
        <div class="form-group">
          <label>المحافظة</label>
          <select name="governorate_id" id="customer-gov" class="form-control" required>
            <option value="">اختر المحافظة</option>
            <?php
            $govs = $conn->query("SELECT * FROM governorates ORDER BY name ASC");
            while($g = $govs->fetch_assoc()):
            ?>
            <option value="<?php echo $g['id'] ?>" <?php echo ($g['id'] == $customer['governorate_id']) ? 'selected' : '' ?>>
              <?php echo $g['name'] ?>
            </option>
            <?php endwhile; ?>
          </select>
        </div>
        <div class="form-group">
          <label>المنطقة</label>
          <select name="area_id" id="customer-area" class="form-control" required>
            <option value="">اختر المنطقة</option>
            <?php
            if($customer['governorate_id']){
              $areas = $conn->query("SELECT * FROM areas WHERE governorate_id = ".intval($customer['governorate_id'])." ORDER BY name ASC");
              while($a = $areas->fetch_assoc()):
              ?>
              <option value="<?php echo $a['id'] ?>" <?php echo ($a['id'] == $customer['area_id']) ? 'selected' : '' ?>>
                <?php echo $a['name'] ?>
              </option>
              <?php endwhile;
            }
            ?>
          </select>
        </div>
        <div class="form-group">
          <label>الحالة</label>
          <select name="status" class="form-control">
            <option value="1" <?php echo ($customer['status']==1) ? "selected" : "" ?>>نشط</option>
            <option value="0" <?php echo ($customer['status']==0) ? "selected" : "" ?>>موقوف</option>
          </select>
        </div>
        <button class="btn btn-success">تعديل</button>
        <a class="btn btn-secondary" href="./index.php?page=customer_list">إلغاء</a>
      </form>
    </div>
  </div>
</div>
<script>
$('#customer-gov').change(function(){
  var gov_id = $(this).val();
  $('#customer-area').html('<option value="">اختر المنطقة</option>');
  if(gov_id){
    $.post('ajax.php?action=get_areas_by_gov', {gov_id: gov_id}, function(resp){
      $('#customer-area').html(resp);
    });
  }
});
$('#manage-customer').submit(function(e){
  e.preventDefault();
  $.ajax({
    url:'ajax.php?action=save_customer',
    method:'POST',
    data: $(this).serialize(),
    success:function(resp){
      if(resp == 1){
        alert("تم التعديل بنجاح");
        setTimeout(function(){
          location.href = 'index.php?page=customer_list'
        },1000);
      } else {
        $('#msg').html('<div class="alert alert-danger">'+resp+'</div>');
      }
    }
  });
});
</script>