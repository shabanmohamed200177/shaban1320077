<?php include'db_connect.php' ?>
<div class="col-lg-12">
  <div class="card card-outline card-primary">
    <div class="card-header">
      <div class="card-tools">
        <a class="btn btn-block btn-sm btn-default btn-flat border-primary" href="./index.php?page=new_courier"><i class="fa fa-plus"></i> إضافة مندوب جديد</a>
      </div>
    </div>
    <div class="card-body">
      <table class="table tabe-hover table-bordered" id="list">
        <thead>
          <tr>
            <th class="text-center">#</th>
            <th>اسم المندوب</th>
            <th>رقم الجوال</th>
            <th>الفرع</th>
            <th>الحالة</th>
            <th class="text-center">إجراء</th>
          </tr>
        </thead>
        <tbody>
          <?php
          $i = 1;
          $qry = $conn->query("SELECT c.*, b.branch_code, g.name as gov_name, g.price as gov_price, a.name as area_name, a.price as area_price, c.email
            FROM couriers c 
            LEFT JOIN branches b ON c.branch_id = b.id 
            LEFT JOIN governorates g ON c.governorate_id = g.id
            LEFT JOIN areas a ON c.area_id = a.id
            ORDER BY c.name ASC");
          while($row= $qry->fetch_assoc()):
          ?>
          <tr>
            <td class="text-center"><?php echo $i++ ?></td>
            <td><?php echo ucwords($row['name']) ?></td>
            <td><?php echo $row['phone'] ?></td>
            <td><?php echo $row['branch_code'] ?></td>
            <td>
              <?php echo $row['status'] == 1 ? '<span class="badge badge-success">نشط</span>' : '<span class="badge badge-secondary">موقوف</span>'; ?>
            </td>
            <td class="text-center">
              <div class="btn-group">
                <!-- زر عرض تفاصيل المندوب -->
                <button type="button" class="btn btn-info btn-flat btn-sm view-courier" 
                  data-name="<?php echo htmlspecialchars(ucwords($row['name'])) ?>"
                  data-phone="<?php echo htmlspecialchars($row['phone']) ?>"
                  data-email="<?php echo htmlspecialchars($row['email']) ?>"
                  data-branch="<?php echo htmlspecialchars($row['branch_code']) ?>"
                  data-gov="<?php echo htmlspecialchars($row['gov_name']) ?>"
                  data-gov-price="<?php echo isset($row['gov_price']) ? number_format($row['gov_price'],2) . ' ج' : '-' ?>"
                  data-area="<?php echo htmlspecialchars($row['area_name']) ?>"
                  data-area-price="<?php echo ($row['gov_name'] === 'دمياط' && isset($row['area_price']) && $row['area_price'] > 0) ? number_format($row['area_price'],2) . ' ج' : '-' ?>"
                >
                  <i class="fas fa-eye"></i>
                </button>

                <!-- زر تعديل -->
                <a href="index.php?page=edit_courier&id=<?php echo $row['id'] ?>" class="btn btn-primary btn-flat btn-sm">
                  <i class="fas fa-edit"></i>
                </a>

                <!-- زر شحنات المندوب -->
                <a href="index.php?page=courier_details&id=<?php echo $row['id'] ?>" class="btn btn-success btn-flat btn-sm">
                  <i class="fas fa-truck"></i> شحنات
                </a>

                <!-- زر حذف -->
                <button type="button" class="btn btn-danger btn-flat btn-sm delete_courier" data-id="<?php echo $row['id'] ?>">
                  <i class="fas fa-trash"></i>
                </button>
              </div>
            </td>
          </tr>
        <?php endwhile; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<!-- بوب اب التفاصيل -->
<div class="modal fade" id="courierDetailsModal" tabindex="-1" role="dialog">
  <div class="modal-dialog modal-md" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">تفاصيل المندوب</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="إغلاق">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <div class="modal-body">
        <table class="table table-bordered table-striped">
          <tbody>
            <tr>
              <th>اسم المندوب</th>
              <td id="m_name"></td>
            </tr>
            <tr>
              <th>رقم الجوال</th>
              <td id="m_phone"></td>
            </tr>
            <tr>
              <th>البريد الإلكتروني</th>
              <td id="m_email"></td>
            </tr>
            <tr>
              <th>الفرع</th>
              <td id="m_branch"></td>
            </tr>
            <tr>
              <th>المحافظة</th>
              <td id="m_gov"></td>
            </tr>
            <tr>
              <th>سعر شحن المحافظة</th>
              <td id="m_gov_price"></td>
            </tr>
            <tr>
              <th>المنطقة</th>
              <td id="m_area"></td>
            </tr>
            <tr>
              <th>سعر شحن المنطقة</th>
              <td id="m_area_price"></td>
            </tr>
          </tbody>
        </table>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-dismiss="modal">إغلاق</button>
      </div>
    </div>
  </div>
</div>

<script>
  $(document).ready(function(){
    $('#list').dataTable();

    $('.delete_courier').click(function(){
      _conf("هل أنت متأكد من حذف هذا المندوب؟","delete_courier",[$(this).attr('data-id')])
    });

    // بوب اب التفاصيل
    $('.view-courier').click(function(){
      $('#m_name').text($(this).data('name'));
      $('#m_phone').text($(this).data('phone'));
      $('#m_email').text($(this).data('email'));
      $('#m_branch').text($(this).data('branch'));
      $('#m_gov').text($(this).data('gov'));
      $('#m_gov_price').text($(this).data('gov-price'));
      $('#m_area').text($(this).data('area'));
      $('#m_area_price').text($(this).data('area-price'));
      $('#courierDetailsModal').modal('show');
    });
  });

  function delete_courier($id){
    start_load()
    $.ajax({
      url:'ajax.php?action=delete_courier',
      method:'POST',
      data:{id:$id},
      success:function(resp){
        if(resp==1){
          alert_toast("تم حذف المندوب بنجاح",'success')
          setTimeout(function(){
            location.reload()
          },1500)
        }
      }
    })
  }
</script>
