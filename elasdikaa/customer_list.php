<?php include 'db_connect.php'; ?>
<div class="col-lg-12">
  <div class="card card-outline card-primary">
    <div class="card-header">
      <div class="card-tools">
        <a class="btn btn-block btn-sm btn-default btn-flat border-primary" href="./index.php?page=new_customer"><i class="fa fa-plus"></i> إضافة عميل جديد</a>
      </div>
    </div>
    <div class="card-body">
      <div class="row mb-3">
          <div class="col-md-4">
              <label for="customerSearchInput">البحث بالاسم أو رقم الجوال:</label>
              <input type="text" id="customerSearchInput" class="form-control" placeholder="ابحث...">
          </div>
          <div class="col-md-4">
              <label for="governorateFilter">تصفية حسب المحافظة:</label>
              <select id="governorateFilter" class="form-control">
                  <option value="">كل المحافظات</option>
              </select>
          </div>
          <div class="col-md-4 d-flex align-items-end">
              <button type="button" class="btn btn-primary w-100" id="applyCustomerFilterBtn">تطبيق الفلتر</button>
          </div>
      </div>
      <div id="customerTableContainer">
          <table class="table table-hover table-bordered" id="customer-list">
            <thead>
              <tr>
                <th class="text-center">#</th>
                <th>اسم العميل</th>
                <th>رقم الجوال</th>
                <th>البريد الإلكتروني</th>
                <th>العنوان</th>
                <th>الحالة</th>
                <th>إجراء</th>
              </tr>
            </thead>
            <tbody>
              <!-- Customer data will be loaded dynamically here -->
            </tbody>
          </table>
          <div id="customerTableLoading" class="text-center py-5" style="display: none;">
              <div class="spinner-border text-primary" role="status">
                  <span class="visually-hidden">Loading...</span>
              </div>
              <p class="mt-2">جارٍ تحميل العملاء...</p>
          </div>
      </div>
    </div>
  </div>
</div>

<script>
$(document).ready(function(){
  // Initialize DataTable
  let customerTable = $('#customer-list').DataTable({
    "language": {
        "url": "./js/Arabic.json" 
    },
    "paging": true,
    "searching": false,
    "info": true,
    "order": []
  });

  // Function to load customers
  function loadCustomers(searchQuery = '', governorateId = '') {
      $('#customerTableLoading').show();
      customerTable.clear().draw();

      $.ajax({
          url: 'ajax.php?action=get_all_customers_filtered',
          method: 'POST',
          data: { 
              search_query: searchQuery, 
              governorate_id: governorateId 
          },
          dataType: 'json',
          success: function(response) {
              $('#customerTableLoading').hide();
              if (response.status === 'success' || Array.isArray(response)) {
                  let data = response.data || response;
                  let i = 1;
                  data.forEach(customer => {
                      let statusBadge = customer.status == 1 ? '<span class="badge badge-success">نشط</span>' : '<span class="badge badge-secondary">موقوف</span>';
                      
                      customerTable.row.add([
                          i++,
                          customer.name,
                          customer.phone,
                          customer.email || '',
                          `${customer.address || ''}<br><small class="text-muted">${customer.governorate_name || ''} - ${customer.area_name || ''}</small>`,
                          statusBadge,
                          `
                          <div class="btn-group">
                            <a href="./index.php?page=customer_profile&id=${customer.id}" class="btn btn-info btn-flat btn-sm" title="ملف العميل">
                              <i class="fas fa-user"></i>
                            </a>
                            <a href="./index.php?page=edit_customer&id=${customer.id}" class="btn btn-primary btn-flat btn-sm" title="تعديل">
                              <i class="fas fa-edit"></i>
                            </a>
                            <button type="button" class="btn btn-danger btn-flat btn-sm btn-delete-customer" data-id="${customer.id}" title="حذف">
                              <i class="fas fa-trash"></i>
                            </button>
                          </div>
                          `
                      ]).draw(false);
                  });
              } else {
                  console.log('No customers found or invalid response format');
              }
          },
          error: function(jqXHR, textStatus, errorThrown) {
              $('#customerTableLoading').hide();
              alert('حدث خطأ أثناء تحميل بيانات العملاء');
              console.error("AJAX Error: ", textStatus, errorThrown);
          }
      });
  }

  // Load Governorates
  function loadGovernorates() {
      $.ajax({
          url: 'ajax.php?action=get_all_governorates',
          method: 'GET',
          dataType: 'json',
          success: function(response) {
              if (response.status === 1 || Array.isArray(response)) {
                  const select = $('#governorateFilter');
                  select.find('option:not(:first)').remove();
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

  // Initial load
  loadGovernorates();
  loadCustomers();

  // Apply Filter Button
  $('#applyCustomerFilterBtn').on('click', function() {
      const searchQuery = $('#customerSearchInput').val();
      const governorateId = $('#governorateFilter').val();
      loadCustomers(searchQuery, governorateId);
  });

  // Filter when governorate changes
  $('#governorateFilter').on('change', function() {
      const searchQuery = $('#customerSearchInput').val();
      const governorateId = $(this).val();
      loadCustomers(searchQuery, governorateId);
  });

  // Delete customer functionality
  var currentCustomerId = null;
  $('body').on('click', '.btn-delete-customer', function(){
    currentCustomerId = $(this).data('id');
    if(confirm('هل أنت متأكد من حذف هذا العميل؟')) {
      $.post('ajax.php?action=delete_customer', {id: currentCustomerId}, function(resp){
        try {
      var response = JSON.parse(resp);
      if(response.status === 'success'){
            alert('تم حذف العميل بنجاح');
            loadCustomers();
                } else {
            alert('خطأ: ' + response.message);
          }
        } catch(e) {
          alert('تم حذف العميل بنجاح');
          loadCustomers();
        }
      });
    }
  });
});
</script>