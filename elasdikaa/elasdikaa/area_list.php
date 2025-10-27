<?php include'db_connect.php'; ?>
<div class="col-lg-12">
  <div class="card card-outline card-primary">
    <div class="card-header">
      <h5 class="card-title">قائمة المناطق</h5>
    </div>
    <div class="card-body">
      <div class="row mb-3">
        <div class="col-md-8">
          <form id="add-area-form" class="form-inline">
            <select name="governorate_id" class="form-control mr-2" required>
              <option value="">اختر المحافظة</option>
              <?php
              $govs = $conn->query("SELECT * FROM governorates ORDER BY name ASC");
              while($g = $govs->fetch_assoc()):
              ?>
              <option value="<?php echo $g['id'] ?>"><?php echo $g['name'] ?></option>
              <?php endwhile; ?>
            </select>
            <input type="text" name="area_name" class="form-control mr-2" placeholder="اسم المنطقة" required>
            <input type="number" min="0" step="any" name="price" id="area-price" class="form-control mr-2" placeholder="سعر المنطقة (لدمياط فقط)" style="display:none;">
            <button class="btn btn-success">إضافة</button>
            <span id="area-msg" class="ml-2"></span>
          </form>
        </div>
        <!-- تم حذف input البحث الإضافي -->
      </div>
      <table class="table table-hover table-bordered" id="area-table">
        <thead>
          <tr>
            <th class="text-center">#</th>
            <th>اسم المنطقة</th>
            <th>المحافظة</th>
            <th>سعر المنطقة</th>
            <th>عدد المناديب</th>
            <th>إجراء</th>
          </tr>
        </thead>
        <tbody>
          <?php
          $i = 1;
          $qry = $conn->query("SELECT a.*, g.name as gov_name, g.id as gov_id, g.price as gov_price, 
            (SELECT COUNT(*) FROM couriers WHERE area_id = a.id) as courier_count 
            FROM areas a LEFT JOIN governorates g ON a.governorate_id = g.id 
            ORDER BY a.name ASC");
          while($row = $qry->fetch_assoc()):
          ?>
          <tr>
            <td class="text-center"><?php echo $i++ ?></td>
            <td class="area-name"><b><?php echo $row['name'] ?></b></td>
            <td class="gov-name"><?php echo $row['gov_name'] ?></td>
            <td>
              <?php
                if($row['gov_name'] == 'دمياط') 
                  echo '<span class="badge badge-warning">'.number_format($row['price'],2).' ج</span>';
                else 
                  echo '<span class="text-muted">---</span>';
              ?>
            </td>
            <td><span class="badge badge-primary"><?php echo $row['courier_count'] ?></span></td>
            <td class="text-center">
              <button class="btn btn-sm btn-info edit-area" 
                data-id="<?php echo $row['id'] ?>" 
                data-name="<?php echo htmlspecialchars($row['name']) ?>"
                data-gov="<?php echo $row['gov_id'] ?>"
                data-price="<?php echo $row['price'] ?>">
                <i class="fas fa-edit"></i>
              </button>
              <button type="button" class="btn btn-danger btn-flat delete_area" data-id="<?php echo $row['id'] ?>">
                <i class="fas fa-trash"></i>
              </button>
            </td>
          </tr>
          <?php endwhile; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<!-- بوب أب تعديل المنطقة -->
<div class="modal fade" id="editAreaModal" tabindex="-1" role="dialog">
  <div class="modal-dialog modal-sm" role="document">
    <div class="modal-content">
      <form id="edit-area-form">
        <div class="modal-header">
          <h5 class="modal-title">تعديل بيانات المنطقة</h5>
          <button type="button" class="close" data-dismiss="modal" aria-label="Close">
            <span aria-hidden="true">&times;</span>
          </button>
        </div>
        <div class="modal-body">
          <input type="hidden" name="id" id="edit-area-id">
          <div class="form-group">
            <label>اسم المنطقة</label>
            <input type="text" name="area_name" id="edit-area-name" class="form-control" required>
          </div>
          <div class="form-group">
            <label>المحافظة</label>
            <select name="governorate_id" id="edit-gov-id" class="form-control" required>
              <option value="">اختر المحافظة</option>
              <?php
              $govs = $conn->query("SELECT * FROM governorates ORDER BY name ASC");
              while($g = $govs->fetch_assoc()):
              ?>
              <option value="<?php echo $g['id'] ?>"><?php echo $g['name'] ?></option>
              <?php endwhile; ?>
            </select>
          </div>
          <div class="form-group" id="edit-area-price-row" style="display: none;">
            <label>سعر المنطقة (لدمياط فقط)</label>
            <input type="number" min="0" step="any" name="price" id="edit-area-price" class="form-control">
          </div>
          <div id="edit-area-msg"></div>
        </div>
        <div class="modal-footer">
          <button class="btn btn-primary">حفظ التعديل</button>
          <button type="button" class="btn btn-secondary" data-dismiss="modal">إلغاء</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
$(document).ready(function(){
  var table = $('#area-table').DataTable({
    language: {
      search: "بحث:",
      lengthMenu: "عرض _MENU_ منطقة",
      info: "عرض _START_ إلى _END_ من أصل _TOTAL_ منطقة",
      paginate: {
        first: "الأول",
        previous: "السابق",
        next: "التالي",
        last: "الأخير"
      },
      zeroRecords: "لا توجد نتائج مطابقة",
      infoEmpty: "لا توجد بيانات",
      infoFiltered: "(تمت التصفية من إجمالي _MAX_ منطقة)"
    }
  });

  // إظهار حقل سعر المنطقة فقط إذا المحافظة دمياط
  $('select[name="governorate_id"]').change(function(){
    var govName = $(this).find('option:selected').text();
    if(govName == 'دمياط'){
      $('#area-price').show();
    } else {
      $('#area-price').hide().val('');
    }
  });

  // حذف منطقة
  $('#area-table').on('click', '.delete_area', function(){
    if(confirm("هل أنت متأكد من حذف هذه المنطقة؟")) {
      var id = $(this).attr('data-id');
      $.ajax({
        url:'ajax.php?action=delete_area',
        method:'POST',
        data:{id:id},
        success:function(resp){
          if(resp==1){
            alert("تم حذف المنطقة بنجاح");
            location.reload();
          } else {
            alert("حدث خطأ أثناء الحذف: "+resp);
          }
        }
      });
    }
  });

  // إضافة منطقة جديدة - تحديث الصف ديناميكيًا
  $('#add-area-form').submit(function(e){
    e.preventDefault();
    var $form = $(this);
    var formData = $form.serialize() + '&return_json=1';
    $.ajax({
      url:'ajax.php?action=add_area',
      method:'POST',
      data: formData,
      dataType: 'json',
      success:function(res){
        if(res && res.status == 1 && res.area){
          $('#area-msg').html('<span class="text-success">تمت الإضافة بنجاح</span>');
          var a = res.area;
          // تحديد اسم المحافظة للعرض
          var govName = a.gov_name || $('#add-area-form select[name="governorate_id"] option:selected').text();
          var priceHtml = (govName === 'دمياط' && a.price) ? ('<span class="badge badge-warning">'+ Number(a.price).toFixed(2) +' ج</span>') : '<span class="text-muted">---</span>';
          var newRow = $(
            '<tr>'+
            '<td class="text-center"></td>'+
            '<td class="area-name"><b>'+ a.name +'</b></td>'+
            '<td class="gov-name">'+ govName +'</td>'+
            '<td>'+ priceHtml +'</td>'+
            '<td><span class="badge badge-primary">0</span></td>'+
            '<td class="text-center">\
              <button class="btn btn-sm btn-info edit-area" data-id="'+a.id+'" data-name="'+a.name+'" data-gov="'+a.governorate_id+'" data-price="'+(a.price||0)+'">\
                <i class="fas fa-edit"></i>\
              </button>\
              <button type="button" class="btn btn-danger btn-flat delete_area" data-id="'+a.id+'">\
                <i class="fas fa-trash"></i>\
              </button>\
            </td>'+
            '</tr>'
          );
          if ($.fn.DataTable.isDataTable('#area-table')) {
            var dt = $('#area-table').DataTable();
            dt.row.add(newRow).draw(false);
          } else {
            $('#area-table tbody').append(newRow);
          }
          // إعادة ترقيم العمود الأول
          $('#area-table tbody tr').each(function(idx){
            $(this).find('td:first').text(idx+1);
          });
          $form[0].reset();
          $('#area-price').hide();
        } else if(res && res.status == 2){
          $('#area-msg').html('<span class="text-danger">المنطقة موجودة بالفعل</span>');
        } else {
          var msg = (res && res.message) ? res.message : 'حدث خطأ غير متوقع';
          $('#area-msg').html('<span class="text-danger">'+msg+'</span>');
        }
      },
      error: function(){
        $('#area-msg').html('<span class="text-danger">فشل الاتصال بالخادم</span>');
      }
    });
  });

  // فتح بوب أب التعديل وتحميل البيانات - باستخدام delegation!
  $('#area-table').on('click', '.edit-area', function(){
    $('#edit-area-id').val($(this).data('id'));
    $('#edit-area-name').val($(this).data('name'));
    $('#edit-gov-id').val($(this).data('gov'));
    $('#edit-area-price').val($(this).data('price'));
    $('#edit-area-msg').html('');
    // إظهار حقل السعر إذا المحافظة دمياط
    var govName = $('#edit-gov-id option:selected').text();
    if(govName == 'دمياط'){
      $('#edit-area-price-row').show();
    } else {
      $('#edit-area-price-row').hide();
    }
    $('#editAreaModal').modal('show');
  });

  // إظهار/إخفاء السعر في بوب أب التعديل عند تغيير المحافظة
  $('#edit-gov-id').change(function(){
    var govName = $(this).find('option:selected').text();
    if(govName == 'دمياط'){
      $('#edit-area-price-row').show();
    } else {
      $('#edit-area-price-row').hide();
      $('#edit-area-price').val('');
    }
  });

  // حفظ التعديل
  $('#edit-area-form').submit(function(e){
    e.preventDefault();
    $.ajax({
      url:'ajax.php?action=update_area',
      method:'POST',
      data: $(this).serialize(),
      success:function(resp){
        if(resp == 1){
          $('#edit-area-msg').html('<span class="text-success">تم التعديل بنجاح</span>');
          setTimeout(function(){ location.reload(); }, 1000);
        } else if(resp == 0){
          $('#edit-area-msg').html('<span class="text-danger">المنطقة موجودة بالفعل</span>');
        } else {
          $('#edit-area-msg').html('<span class="text-danger">'+resp+'</span>');
        }
      }
    });
  });
});
</script>