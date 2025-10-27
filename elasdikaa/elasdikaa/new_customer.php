<?php include 'db_connect.php'; ?>
<div class="col-lg-12">
  <div class="card card-outline card-primary">
    <div class="card-header">
      <h3 class="card-title">إضافة عميل جديد</h3>
    </div>
    <div class="card-body">
      <form id="customer-form">
        <div class="row">
          <div class="col-md-6">
            <div class="form-group">
              <label for="name">اسم العميل <span class="text-danger">*</span></label>
              <input type="text" class="form-control" id="name" name="name" required>
            </div>
          </div>
          <div class="col-md-6">
            <div class="form-group">
              <label for="phone">رقم الجوال <span class="text-danger">*</span></label>
              <input type="text" class="form-control" id="phone" name="phone" required>
            </div>
          </div>
        </div>
        <div class="row">
          <div class="col-md-6">
            <div class="form-group">
              <label for="email">البريد الإلكتروني</label>
              <input type="email" class="form-control" id="email" name="email">
            </div>
          </div>
          <div class="col-md-6">
            <div class="form-group">
              <label for="status">الحالة</label>
              <select class="form-control" id="status" name="status">
                <option value="1">نشط</option>
                <option value="0">موقوف</option>
              </select>
            </div>
          </div>
        </div>
        <div class="row">
          <div class="col-md-6">
            <div class="form-group">
              <label for="governorate_id">المحافظة</label>
              <select class="form-control" id="governorate_id" name="governorate_id">
                <option value="">اختر المحافظة</option>
              </select>
            </div>
          </div>
          <div class="col-md-6">
            <div class="form-group">
              <label for="area_id">المنطقة</label>
              <select class="form-control" id="area_id" name="area_id">
                <option value="">اختر المنطقة</option>
              </select>
            </div>
          </div>
        </div>
        <div class="row">
          <div class="col-md-12">
            <div class="form-group">
              <label for="address">العنوان التفصيلي</label>
              <textarea class="form-control" id="address" name="address" rows="3"></textarea>
            </div>
          </div>
        </div>
        <div class="row">
          <div class="col-md-12">
            <button type="submit" class="btn btn-primary">حفظ العميل</button>
            <a href="./index.php?page=customer_list" class="btn btn-secondary">إلغاء</a>
          </div>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
$(document).ready(function(){
  // تحميل المحافظات
  loadGovernorates();
  
  // تحميل المناطق عند تغيير المحافظة
  $('#governorate_id').on('change', function(){
    const governorateId = $(this).val();
    if (governorateId) {
      loadAreas(governorateId);
    } else {
      $('#area_id').html('<option value="">اختر المنطقة</option>');
    }
  });
  
  // معالجة تقديم النموذج
  $('#customer-form').on('submit', function(e){
    e.preventDefault();
    
    const formData = {
      name: $('#name').val(),
      phone: $('#phone').val(),
      email: $('#email').val(),
      address: $('#address').val(),
      governorate_id: $('#governorate_id').val() || null,
      area_id: $('#area_id').val() || null,
      status: $('#status').val()
    };
    
    // التحقق من البيانات
    if (!formData.name || !formData.phone) {
      alert('يرجى ملء الحقول المطلوبة');
      return;
    }
    
    // إرسال البيانات
    $.ajax({
      url: 'ajax.php?action=save_customer',
      method: 'POST',
      data: formData,
      dataType: 'json',
      success: function(response){
        if (response == 1) {
          alert('تم حفظ العميل بنجاح');
          window.location.href = './index.php?page=customer_list';
        } else if (response == 2) {
          alert('العميل موجود بالفعل');
        } else {
          alert('خطأ في حفظ العميل: ' + response);
        }
      },
      error: function(){
        alert('حدث خطأ أثناء حفظ العميل');
      }
    });
  });
  
  // دالة تحميل المحافظات
  function loadGovernorates() {
    $.ajax({
      url: 'ajax.php?action=get_all_governorates',
      method: 'POST',
      dataType: 'json',
      success: function(response) {
        if (response.status === 1 || Array.isArray(response)) {
          const select = $('#governorate_id');
          let data = response.data || response;
          data.forEach(gov => {
            select.append(`<option value="${gov.id}">${gov.name}</option>`);
          });
        }
      },
      error: function() {
        console.error('Error loading governorates');
      }
    });
  }
  
  // دالة تحميل المناطق
  function loadAreas(governorateId) {
    $.ajax({
      url: 'ajax.php?action=get_areas_by_gov',
      method: 'POST',
      data: { gov_id: governorateId },
      dataType: 'json',
      success: function(response) {
        if (response.status === 1 || Array.isArray(response)) {
          const select = $('#area_id');
          select.html('<option value="">اختر المنطقة</option>');
          let data = response.data || response;
          data.forEach(area => {
            select.append(`<option value="${area.id}">${area.name}</option>`);
          });
        }
      },
      error: function() {
        console.error('Error loading areas');
      }
    });
  }
});
</script>