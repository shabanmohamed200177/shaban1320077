<?php if(!isset($conn)){ include 'db_connect.php'; } ?>
<div class="col-lg-12">
  <div class="card card-outline card-primary">
    <div class="card-body">
      <form action="" id="manage-courier">
        <input type="hidden" name="id" value="<?php echo isset($id) ? $id : '' ?>">
        <div id="msg" class=""></div>
        <div class="form-group">
          <label>اسم المندوب</label>
          <input type="text" name="name" class="form-control" required value="<?php echo isset($name) ? $name : '' ?>">
        </div>
        <div class="form-group">
          <label>رقم الجوال</label>
          <input type="text" name="phone" class="form-control" required value="<?php echo isset($phone) ? $phone : '' ?>">
        </div>
        <div class="form-group">
          <label>البريد الإلكتروني</label>
          <input type="email" name="email" class="form-control" value="<?php echo isset($email) ? $email : '' ?>">
        </div>
        <div class="form-group">
          <label>كلمة المرور <?php echo isset($id) ? '(اتركها فارغة إذا لا تريد تغييرها)' : '' ?></label>
          <input type="password" name="password" class="form-control" <?php echo !isset($id) ? 'required' : '' ?>>
        </div>
        <div class="form-group">
          <label>الفرع</label>
          <select name="branch_id" class="form-control" required>
            <option value="">اختر الفرع</option>
            <?php
            $branches = $conn->query("SELECT * FROM branches");
            while($row = $branches->fetch_assoc()):
            ?>
            <option value="<?php echo $row['id'] ?>" <?php echo isset($branch_id) && $branch_id == $row['id'] ? "selected":'' ?>><?php echo $row['branch_code'] ?></option>
            <?php endwhile; ?>
          </select>
        </div>
        <div class="form-group">
          <label>المحافظة</label>
          <select name="governorate_id" id="courier-gov" class="form-control" required>
            <option value="">اختر المحافظة</option>
            <?php
            $govs = $conn->query("SELECT * FROM governorates ORDER BY name ASC");
            while($g = $govs->fetch_assoc()):
            ?>
            <option value="<?php echo $g['id'] ?>" data-price="<?php echo $g['price'] ?>" <?php echo (isset($governorate_id) && $governorate_id == $g['id']) ? 'selected' : '' ?>>
              <?php echo $g['name'] ?>
            </option>
            <?php endwhile; ?>
          </select>
          <div id="gov-shipping-price" class="mt-2" style="display:none; font-weight:bold; color:#00695c;"></div>
        </div>
        <div class="form-group">
          <label>المنطقة</label>
          <select name="area_id" id="courier-area" class="form-control" required>
            <option value="">اختر المنطقة</option>
            <?php
            // المناطق فقط في حالة التعديل
            if(isset($governorate_id)){
              $areas = $conn->query("SELECT * FROM areas WHERE governorate_id = ".intval($governorate_id)." ORDER BY name ASC");
              while($a = $areas->fetch_assoc()):
              ?>
                <option value="<?php echo $a['id'] ?>" data-price="<?php echo $a['price'] ?>" <?php echo (isset($area_id) && $area_id == $a['id']) ? 'selected' : '' ?>>
                  <?php echo $a['name'] ?>
                </option>
              <?php
              endwhile;
            }
            ?>
          </select>
          <div id="area-shipping-price" class="mt-2" style="display:none; font-weight:bold; color:#ff9800;"></div>
        </div>
        <div class="form-group">
          <label>الحالة</label>
          <select name="status" class="form-control">
            <option value="1" <?php echo (isset($status) && $status == 1) ? "selected" : "" ?>>نشط</option>
            <option value="0" <?php echo (isset($status) && $status == 0) ? "selected" : "" ?>>موقوف</option>
          </select>
        </div>
      </form>
    </div>
    <div class="card-footer border-top border-info">
      <div class="d-flex w-100 justify-content-center align-items-center">
        <button class="btn btn-flat  bg-gradient-primary mx-2" form="manage-courier">حفظ</button>
        <a class="btn btn-flat bg-gradient-secondary mx-2" href="./index.php?page=courier_list">إلغاء</a>
      </div>
    </div>
  </div>
</div>
<script>
function formatPrice(price) {
  return parseFloat(price) ? (parseFloat(price).toFixed(2) + " ج") : "غير محدد";
}
$(function(){
  // عند تغيير المحافظة
  $('#courier-gov').change(function(){
    var gov_id = $(this).val();
    var gov_name = $('#courier-gov option:selected').text().trim();
    var gov_price = $('#courier-gov option:selected').data('price');
    // إظهار سعر شحن المحافظة
    if(gov_id && gov_price !== undefined){
      $('#gov-shipping-price').show().text("سعر شحن المحافظة: " + formatPrice(gov_price));
    } else {
      $('#gov-shipping-price').hide();
    }
    // جلب المناطق المرتبطة بالمحافظة
    $('#courier-area').html('<option value="">اختر المنطقة</option>');
    $('#area-shipping-price').hide();
    if(gov_id){
      $.ajax({
        url: 'ajax.php?action=get_areas_by_gov',
        method: 'POST',
        data: { gov_id: gov_id },
        dataType: 'json',
        success: function(res){
          var $area = $('#courier-area');
          $area.empty().append('<option value="">اختر المنطقة</option>');
          if(res && res.status == 1 && Array.isArray(res.data)){
            res.data.forEach(function(a){
              var opt = $('<option>').val(a.id).text(a.name).attr('data-price', a.price || 0);
              $area.append(opt);
            });
          } else {
            // في حال فشل الاسترجاع، أبقِ الاختيار فارغًا مع رسالة
            console.warn('Failed to load areas: ', res && res.message ? res.message : res);
          }
        },
        error: function(xhr){
          console.error('AJAX error loading areas', xhr);
        }
      });
    }
  });

  // عند تغيير المنطقة
  $('#courier-area').on('change', function(){
    var gov_name = $('#courier-gov option:selected').text().trim();
    var area_price = $('#courier-area option:selected').data('price');
    if(gov_name == 'دمياط' && area_price !== undefined && area_price != "" && area_price != "0"){
      $('#area-shipping-price').show().text("سعر شحن المنطقة: " + formatPrice(area_price));
    } else {
      $('#area-shipping-price').hide();
    }
  });

  // تفعيل تلقائي عند تحميل الصفحة في حالة التعديل
  <?php if(isset($governorate_id)): ?>
    $('#courier-gov').trigger('change');
    <?php if(isset($area_id)): ?>
      setTimeout(function(){
        $('#courier-area').val('<?php echo $area_id ?>').trigger('change');
      }, 500);
    <?php endif; ?>
  <?php endif; ?>

  $('#manage-courier').submit(function(e){
    e.preventDefault()
    start_load()
    $.ajax({
      url:'ajax.php?action=save_courier',
      data: new FormData($(this)[0]),
      cache: false,
      contentType: false,
      processData: false,
      method: 'POST',
      type: 'POST',
      dataType: 'text',
      success:function(text){
        var res = null;
        try{ res = JSON.parse(text); }catch(e){ res = { status: (text==1||/تم حفظ/.test(text))?1:0, message: text }; }
        if(res && res.status == 1){
          alert_toast(res.message || 'تم الحفظ بنجاح',"success");
          // إذا كنا داخل صفحة القائمة، حدّث الجدول مباشرة
          try{
            var table = window.parent && window.parent.$ ? window.parent.$('#list').DataTable() : null;
            if(table && res.courier){
              var c = res.courier;
              var statusHtml = (parseInt(c.status) === 1) ? '<span class="badge badge-success">نشط</span>' : '<span class="badge badge-secondary">موقوف</span>';
              var actionHtml = '\
                <div class="btn-group">\
                  <button type="button" class="btn btn-info btn-flat btn-sm view-courier"\
                    data-name="'+(c.name||'')+'"\
                    data-phone="'+(c.phone||'')+'"\
                    data-email="'+(c.email||'')+'"\
                    data-branch="'+(c.branch_code||'')+'"\
                    data-gov="'+(c.gov_name||'')+'"\
                    data-gov-price="'+(c.gov_price?Number(c.gov_price).toFixed(2)+' ج':'-')+'"\
                    data-area="'+(c.area_name||'')+'"\
                    data-area-price="'+(c.gov_name==='دمياط'&&c.area_price?Number(c.area_price).toFixed(2)+' ج':'-')+'">\
                    <i class="fas fa-eye"></i>\
                  </button>\
                  <a href="index.php?page=edit_courier&id='+c.id+'" class="btn btn-primary btn-flat btn-sm"><i class="fas fa-edit"></i></a>\
                  <a href="index.php?page=courier_details&id='+c.id+'" class="btn btn-success btn-flat btn-sm"><i class="fas fa-truck"></i> شحنات</a>\
                  <button type="button" class="btn btn-danger btn-flat btn-sm delete_courier" data-id="'+c.id+'"><i class="fas fa-trash"></i></button>\
                </div>';

              if(res.action === 'create'){
                table.row.add([
                  '',
                  (c.name||''),
                  (c.phone||''),
                  (c.branch_code||''),
                  statusHtml,
                  actionHtml
                ]).draw(false);
                // إعادة ترقيم العمود الأول
                window.parent.$('#list tbody tr').each(function(idx){
                  window.parent.$(this).find('td:first').text(idx+1);
                });
              } else {
                // تحديث صف موجود: ابحث بالـ edit link
                var $rows = window.parent.$('#list tbody tr');
                $rows.each(function(){
                  var $row = window.parent.$(this);
                  var $edit = $row.find('a[href*="index.php?page=edit_courier&id='+c.id+'"]').first();
                  if($edit.length){
                    var rowIdx = table.row($row).index();
                    table.row(rowIdx).data([
                      $row.find('td').eq(0).text(),
                      (c.name||''),
                      (c.phone||''),
                      (c.branch_code||''),
                      statusHtml,
                      actionHtml
                    ]).draw(false);
                  }
                });
              }
            } else {
              // fallback: العودة للقائمة
              setTimeout(function(){ window.location.href = 'index.php?page=courier_list'; }, 800);
            }
          }catch(err){
            // fallback آمن
            setTimeout(function(){ window.location.href = 'index.php?page=courier_list'; }, 800);
          }
          end_load && end_load();
        } else {
          var msg = (res && res.message) ? res.message : (typeof text === 'string' && text ? text : 'حدث خطأ غير متوقع');
          alert_toast(msg, 'error');
          end_load && end_load();
        }
      },
      error: function(xhr){
        alert_toast('فشل الاتصال بالخادم', 'error');
        end_load && end_load();
      }
    })
  })
});
</script>