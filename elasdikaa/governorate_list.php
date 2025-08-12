<?php include'db_connect.php'; ?>
<div class="col-lg-12">
  <div class="card card-outline card-primary">
    <div class="card-header">
      <h5 class="card-title">قائمة المحافظات</h5>
    </div>
    <div class="card-body">
      <div class="row mb-3">
        <div class="col-md-6">
          <form id="add-governorate-form" class="form-inline">
            <input type="text" name="gov_name" class="form-control mr-2" placeholder="اسم المحافظة" required>
            <input type="number" min="0" step="any" name="price" class="form-control mr-2" placeholder="سعر الشحن" required>
            <button class="btn btn-primary">إضافة</button>
            <span id="gov-msg" class="ml-2"></span>
          </form>
        </div>
      </div>
      <table class="table table-hover table-bordered" id="governorate-table">
        <thead>
          <tr>
            <th class="text-center">#</th>
            <th>اسم المحافظة</th>
            <th>سعر الشحن</th>
            <th>عدد المناطق</th>
            <th>عدد المناديب</th>
            <th>إجراء</th>
          </tr>
        </thead>
        <tbody>
          <?php
          $i = 1;
          $qry = $conn->query("SELECT g.*, 
            (SELECT COUNT(*) FROM areas WHERE governorate_id = g.id) as area_count, 
            (SELECT COUNT(*) FROM couriers WHERE governorate_id = g.id) as courier_count 
            FROM governorates g ORDER BY g.name ASC");
          while($row = $qry->fetch_assoc()):
          ?>
          <tr>
            <td class="text-center"><?php echo $i++ ?></td>
            <td class="gov-name"><b><?php echo $row['name'] ?></b></td>
            <td><span class="badge badge-warning"><?php echo number_format($row['price'],2) ?> ج</span></td>
            <td><span class="badge badge-info"><?php echo $row['area_count'] ?></span></td>
            <td><span class="badge badge-primary"><?php echo $row['courier_count'] ?></span></td>
            <td class="text-center">
              <button class="btn btn-sm btn-info edit-governorate" 
                data-id="<?php echo $row['id'] ?>" 
                data-name="<?php echo htmlspecialchars($row['name']) ?>"
                data-price="<?php echo $row['price'] ?>">
                <i class="fas fa-edit"></i>
              </button>
              <button type="button" class="btn btn-danger btn-flat delete_governorate" data-id="<?php echo $row['id'] ?>">
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

<!-- بوب أب تعديل المحافظة -->
<div class="modal fade" id="editGovModal" tabindex="-1" role="dialog">
  <div class="modal-dialog modal-sm" role="document">
    <div class="modal-content">
      <form id="edit-governorate-form">
        <div class="modal-header">
          <h5 class="modal-title">تعديل بيانات المحافظة</h5>
          <button type="button" class="close" data-dismiss="modal" aria-label="Close">
            <span aria-hidden="true">&times;</span>
          </button>
        </div>
        <div class="modal-body">
          <input type="hidden" name="id" id="edit-gov-id">
          <div class="form-group">
            <label>اسم المحافظة</label>
            <input type="text" name="gov_name" id="edit-gov-name" class="form-control" required>
          </div>
          <div class="form-group">
            <label>سعر الشحن</label>
            <input type="number" min="0" step="any" name="price" id="edit-gov-price" class="form-control" required>
          </div>
          <div id="edit-gov-msg"></div>
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
  var table = $('#governorate-table').DataTable({
    language: {
      search: "بحث:",
      lengthMenu: "عرض _MENU_ محافظة",
      info: "عرض _START_ إلى _END_ من أصل _TOTAL_ محافظة",
      paginate: {
        first: "الأول",
        previous: "السابق",
        next: "التالي",
        last: "الأخير"
      },
      zeroRecords: "لا توجد نتائج مطابقة",
      infoEmpty: "لا توجد بيانات",
      infoFiltered: "(تمت التصفية من إجمالي _MAX_ محافظة)"
    }
  });

  // حذف محافظة
  $('#governorate-table').on('click', '.delete_governorate', function(){
    if(confirm("هل أنت متأكد من حذف هذه المحافظة؟")) {
      var id = $(this).attr('data-id');
      $.ajax({
        url:'ajax.php?action=delete_governorate',
        method:'POST',
        data:{id:id},
        success:function(resp){
          if(resp==1){
            alert("تم حذف المحافظة بنجاح");
            location.reload();
          } else if(resp == 'has_areas'){
            alert("لا يمكن حذف المحافظة لوجود مناطق تابعة لها.");
          } else {
            alert("حدث خطأ أثناء الحذف: "+resp);
          }
        }
      });
    }
  });

  // إضافة محافظة جديدة - تحديث الصف ديناميكيًا
  $('#add-governorate-form').submit(function(e){
    e.preventDefault();
    var $form = $(this);
    var formData = $form.serialize() + '&return_json=1';
    $.ajax({
      url:'ajax.php?action=add_governorate',
      method:'POST',
      data: formData,
      dataType: 'json',
      success:function(res){
        if(res && res.status == 1 && res.governorate){
          $('#gov-msg').html('<span class="text-success">تمت الإضافة بنجاح</span>');
          // إضافة الصف الجديد إلى الجدول بدون إعادة تحميل
          var g = res.governorate;
          var newRow = $(
            '<tr>'+
            '<td class="text-center"></td>'+
            '<td class="gov-name"><b>'+ g.name +'</b></td>'+
            '<td><span class="badge badge-warning">'+ Number(g.price).toFixed(2) +' ج</span></td>'+
            '<td><span class="badge badge-info">0</span></td>'+
            '<td><span class="badge badge-primary">0</span></td>'+
            '<td class="text-center">\
              <button class="btn btn-sm btn-info edit-governorate" data-id="'+g.id+'" data-name="'+g.name+'" data-price="'+g.price+'">\
                <i class="fas fa-edit"></i>\
              </button>\
              <button type="button" class="btn btn-danger btn-flat delete_governorate" data-id="'+g.id+'">\
                <i class="fas fa-trash"></i>\
              </button>\
            </td>'+
            '</tr>'
          );
          // إضافة للـ DataTable إن كان مفعّلاً
          if ($.fn.DataTable.isDataTable('#governorate-table')) {
            var dt = $('#governorate-table').DataTable();
            dt.row.add(newRow).draw(false);
          } else {
            $('#governorate-table tbody').append(newRow);
          }
          // إعادة ترقيم العمود الأول
          $('#governorate-table tbody tr').each(function(idx){
            $(this).find('td:first').text(idx+1);
          });
          // تصفية الحقول
          $form[0].reset();
        } else if(res && res.status == 2){
          $('#gov-msg').html('<span class="text-danger">المحافظة موجودة بالفعل</span>');
        } else {
          var msg = (res && res.message) ? res.message : 'حدث خطأ غير متوقع';
          $('#gov-msg').html('<span class="text-danger">'+msg+'</span>');
        }
      },
      error: function(xhr){
        $('#gov-msg').html('<span class="text-danger">فشل الاتصال بالخادم</span>');
      }
    });
  });

  // فتح بوب أب التعديل وتحميل البيانات - باستخدام delegation!
  $('#governorate-table').on('click', '.edit-governorate', function(){
    $('#edit-gov-id').val($(this).data('id'));
    $('#edit-gov-name').val($(this).data('name'));
    $('#edit-gov-price').val($(this).data('price'));
    $('#edit-gov-msg').html('');
    $('#editGovModal').modal('show');
  });

  // حفظ التعديل
  $('#edit-governorate-form').submit(function(e){
    e.preventDefault();
    $.ajax({
      url:'ajax.php?action=update_governorate',
      method:'POST',
      data: $(this).serialize(),
      success:function(resp){
        if(resp == 1){
          $('#edit-gov-msg').html('<span class="text-success">تم التعديل بنجاح</span>');
          setTimeout(function(){ location.reload(); }, 1000);
        } else if(resp == 0){
          $('#edit-gov-msg').html('<span class="text-danger">اسم المحافظة مستخدم بالفعل</span>');
        } else {
          $('#edit-gov-msg').html('<span class="text-danger">'+resp+'</span>');
        }
      }
    });
  });
});

</script>