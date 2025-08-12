<?php
require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'middleware.php';
require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'error_handler.php';
require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'db_connect.php';

SecurityMiddleware::checkSession();

// السماح فقط للمندوبين
if (!isset($_SESSION['courier_id'])) {
    header('Location: login.php');
    exit;
}

$courierName = $_SESSION['courier_name'] ?? 'المندوب';
$pageTitle = 'لوحة المندوب';
include __DIR__.'/partials/layout_top.php';
?>
  <link rel="stylesheet" href="assets/css/dashboard.css">
  <script src="https://code.jquery.com/jquery-3.6.4.min.js"></script>
</head>
<body>
  

  <div class="container py-4">
    <div class="row align-items-stretch">
      <div class="col-4 col-md-2"><div class="kpi-badge kpi-red mb-2"><div class="small">غير مُحصّل</div><div class="kpi-value" id="kpi-uncollected">--</div></div></div>
      <div class="col-4 col-md-2"><div class="kpi-badge kpi-green mb-2"><div class="small">مُحصّل</div><div class="kpi-value" id="kpi-collected">--</div></div></div>
      <div class="col-4 col-md-2"><div class="kpi-badge kpi-blue mb-2"><div class="small">العمولة</div><div class="kpi-value" id="kpi-commission-net">--</div></div></div>
      <div class="col-12 col-md-3"><div class="kpi-badge kpi-gray mb-2"><div class="small">إجمالي الشحنات</div><div class="kpi-value" id="kpi-total-active">--</div></div></div>
    </div>

    <!-- إزالة الفلاتر من الصفحة الرئيسية -->

    <!-- إزالة قائمة counters أسفل البطاقات حسب طلبك -->
    <div id="cards-container"></div>
  </div>

  <!-- Modal: تفاصيل الطلب مع شريط أزرار سفلي -->
  <div class="modal fade detail-modal" id="detailModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-sm">
      <div class="modal-content">
        <div class="modal-header py-2">
          <h6 class="modal-title" id="detailTitle">تفاصيل الطلب</h6>
          <button type="button" class="close" data-dismiss="modal" aria-label="إغلاق"><span aria-hidden="true">&times;</span></button>
        </div>
        <div class="modal-body py-2" id="detailBody"></div>
        <div class="px-2 pb-2">
          <div id="actionArea" class="modal-footer modal-footer-fixed d-flex w-100 action-area">
            <button class="btn btn-success flex-fill" id="btnDelivered"><i class="fa fa-check"></i> تم التسليم</button>
            <button class="btn btn-secondary flex-fill" data-toggle="modal" data-target="#partialModal"><i class="fa fa-check-double"></i> دفع جزئي</button>
            <button class="btn btn-warning flex-fill" data-toggle="modal" data-target="#postponeModal"><i class="fa fa-clock"></i> تأجيل</button>
            <button class="btn btn-danger flex-fill" data-toggle="modal" data-target="#rejectModal"><i class="fa fa-times"></i> رفض</button>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- Modal: دفع جزئي -->
  <div class="modal fade" id="partialModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
      <div class="modal-content">
        <div class="modal-header">
          <h6 class="modal-title">تسجيل دفعة جزئية</h6>
          <button type="button" class="close" data-dismiss="modal" aria-label="إغلاق"><span aria-hidden="true">&times;</span></button>
        </div>
        <div class="modal-body">
          <!-- معلومات الشحنة الحالية -->
          <div class="row mb-3">
            <div class="col-md-6">
              <div class="card bg-light">
                <div class="card-body">
                  <h6 class="card-title">معلومات الشحنة</h6>
                  <div class="row">
                    <div class="col-6">
                      <small class="text-muted">إجمالي المطلوب:</small>
                      <div class="font-weight-bold" id="payment-total-amount">0 ج.م</div>
                    </div>
                    <div class="col-6">
                      <small class="text-muted">المدفوع سابقاً:</small>
                      <div class="font-weight-bold" id="payment-paid-amount">0 ج.م</div>
                    </div>
                  </div>
                  <div class="row mt-2">
                    <div class="col-6">
                      <small class="text-muted">المتبقي:</small>
                      <div class="font-weight-bold text-danger" id="payment-remaining">0 ج.م</div>
                    </div>
                    <div class="col-6">
                      <small class="text-muted">حالة الدفع:</small>
                      <div class="font-weight-bold" id="payment-status">غير محدد</div>
                    </div>
                  </div>
                </div>
              </div>
            </div>
            <div class="col-md-6">
              <div class="card bg-info text-white">
                <div class="card-body">
                  <h6 class="card-title">إدخال الدفعة الجديدة</h6>
                  <div class="form-group mb-2">
                    <label for="partial_paid" class="text-white">المبلغ المدفوع</label>
                    <input type="number" step="0.01" min="0.01" id="partial_paid" class="form-control" placeholder="أدخل المبلغ" />
                  </div>
                  <div class="form-group mb-2">
                    <label for="returned_pieces" class="text-white">عدد القطع المرتجعة</label>
                    <input type="number" min="0" step="1" id="returned_pieces" class="form-control" placeholder="0" value="0" />
                  </div>
                </div>
              </div>
            </div>
          </div>
          
          <!-- ملاحظة الدفع -->
          <div class="form-group">
            <label for="partial_note">ملاحظة الدفع</label>
            <textarea id="partial_note" class="form-control" rows="3" placeholder="ملاحظات إضافية حول الدفعة..."></textarea>
          </div>
          
          <!-- ملخص الدفعة -->
          <div class="alert alert-info" id="payment-summary" style="display: none;">
            <h6>ملخص الدفعة:</h6>
            <div class="row">
              <div class="col-md-4">
                <strong>المبلغ الجديد:</strong> <span id="summary-new-amount">0 ج.م</span>
              </div>
              <div class="col-md-4">
                <strong>إجمالي المدفوع:</strong> <span id="summary-total-paid">0 ج.م</span>
              </div>
              <div class="col-md-4">
                <strong>المتبقي بعد الدفع:</strong> <span id="summary-final-remaining">0 ج.م</span>
              </div>
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button class="btn btn-secondary" data-dismiss="modal">إلغاء</button>
          <button class="btn btn-primary" id="doPartial" disabled>حفظ الدفعة</button>
        </div>
      </div>
    </div>
  </div>

  <!-- Modal: تأجيل -->
  <div class="modal fade" id="postponeModal" tabindex="-1">
    <div class="modal-dialog">
      <div class="modal-content">
        <div class="modal-header">
          <h6 class="modal-title">تأجيل الشحنة</h6>
          <button type="button" class="close" data-dismiss="modal" aria-label="إغلاق"><span aria-hidden="true">&times;</span></button>
        </div>
        <div class="modal-body">
          <div class="form-group">
            <label for="postpone_reason">السبب</label>
            <select id="postpone_reason" class="form-control"></select>
          </div>
          <div class="form-group">
            <label for="postpone_date">تاريخ التأجيل</label>
            <input type="date" id="postpone_date" class="form-control" value="" />
          </div>
          <div class="form-group">
            <label for="postpone_note">ملاحظة</label>
            <textarea id="postpone_note" class="form-control"></textarea>
          </div>
        </div>
        <div class="modal-footer"><button class="btn btn-secondary" data-dismiss="modal">إلغاء</button><button class="btn btn-primary" id="doPostpone">حفظ</button></div>
      </div>
    </div>
  </div>

  <!-- Modal: رفض -->
  <div class="modal fade" id="rejectModal" tabindex="-1">
    <div class="modal-dialog">
      <div class="modal-content">
        <div class="modal-header">
          <h6 class="modal-title">رفض الشحنة</h6>
          <button type="button" class="close" data-dismiss="modal" aria-label="إغلاق"><span aria-hidden="true">&times;</span></button>
        </div>
        <div class="modal-body">
          <div class="form-group">
            <label for="reject_reason">السبب</label>
            <select id="reject_reason" class="form-control"></select>
          </div>
          <div class="form-group">
            <label for="reject_note">ملاحظة</label>
            <textarea id="reject_note" class="form-control"></textarea>
          </div>
        </div>
        <div class="modal-footer"><button class="btn btn-secondary" data-dismiss="modal">إلغاء</button><button class="btn btn-danger" id="doReject">رفض</button></div>
      </div>
    </div>
  </div>

  <script>
    // متغير عام لتخزين معرف الشحنة الحالية
    var currentParcelId = null;
    
    function formatMoney(v){
      v = parseFloat(v || 0);
      return v.toLocaleString('ar-EG', {minimumFractionDigits: 2, maximumFractionDigits: 2}) + ' ج.م';
    }
    
    // دالة جديدة لاستخراج القيمة الرقمية من النص المنسق
    function parseLocaleNumber(input){
      // يدعم الأرقام العربية والإنجليزية وعلامات الفصل العربية
      let s = String(input == null ? '' : input);
      if (!s) return 0;
      // إزالة العملة والمسافات
      s = s.replace(/ج\.م/g,'').replace(/\s+/g,'');
      // خرائط الأرقام العربية
      const map = {'٠':'0','١':'1','٢':'2','٣':'3','٤':'4','٥':'5','٦':'6','٧':'7','٨':'8','٩':'9','۰':'0','۱':'1','۲':'2','۳':'3','۴':'4','۵':'5','۶':'6','۷':'7','۸':'8','۹':'9'};
      s = s.replace(/[٠-٩۰-۹]/g, d => map[d]);
      // إزالة فواصل الآلاف العربية والإنجليزية
      s = s.replace(/[٬,،]/g,'');
      // تحويل العلامة العشرية العربية "٫" إلى نقطة
      s = s.replace(/٫/g,'.');
      const n = Number(s);
      return Number.isFinite(n) ? n : 0;
    }
    
    function formatMoneyLatn(v){ 
      return (parseFloat(v||0)).toLocaleString('ar-EG',{minimumFractionDigits:2,maximumFractionDigits:2}) + ' ج.م'; 
    }
    
    function loadStats(){
      $.getJSON('api/stats.php', function(resp){
        if(resp && resp.success){
          var d = resp.data || {};
          $('#kpi-total-active').text(d.total_active || 0);
          $('#kpi-collected').text(formatMoney(d.cod_collected_active || 0));
          $('#kpi-uncollected').text(formatMoney(d.cod_uncollected_active || 0));
          var commissionNet = d.net_courier_commission_active !== undefined ? d.net_courier_commission_active : ((d.sum_commission_active || 0) - (d.sum_company_profit_active || 0));
          $('#kpi-commission-net').text(formatMoney(commissionNet));
        }
      });
    }
    
    $(function(){
      loadStats();
      // تحديث كل 30 ثانية
      setInterval(loadStats, 30000);
    });

    // تحميل قائمة الحالات للفلاتر
    function loadStatuses(){}

    // تحميل أسباب التأجيل والرفض
    function loadStatusReasons(){
      // تحميل أسباب التأجيل (الحالة 7)
      $.getJSON('api/reasons.php', {status_id: 7}, function(resp){
        if(resp && resp.success){
          var postponeSelect = $('#postpone_reason');
          postponeSelect.empty();
          postponeSelect.append('<option value="">اختر سبب التأجيل</option>');
          resp.data.forEach(function(reason){
            postponeSelect.append('<option value="' + reason.id + '">' + reason.reason_text + '</option>');
          });
          console.log('تم تحميل أسباب التأجيل:', resp.data);
        } else {
          console.log('فشل في تحميل أسباب التأجيل:', resp);
        }
      }).fail(function(xhr, status, error) {
        console.log('خطأ في تحميل أسباب التأجيل:', error);
        // استخدام أسباب افتراضية في حالة الفشل
        var postponeSelect = $('#postpone_reason');
        postponeSelect.empty();
        postponeSelect.append('<option value="">اختر سبب التأجيل</option>');
        postponeSelect.append('<option value="1">المستلم غير متاح</option>');
        postponeSelect.append('<option value="2">العنوان غير صحيح</option>');
        postponeSelect.append('<option value="3">المستلم رفض الاستلام</option>');
        postponeSelect.append('<option value="4">مشكلة في الدفع</option>');
        postponeSelect.append('<option value="5">أسباب أخرى</option>');
      });
      
      // تحميل أسباب الرفض (الحالة 8)
      $.getJSON('api/reasons.php', {status_id: 8}, function(resp){
        if(resp && resp.success){
          var rejectSelect = $('#reject_reason');
          rejectSelect.empty();
          rejectSelect.append('<option value="">اختر سبب الرفض</option>');
          resp.data.forEach(function(reason){
            rejectSelect.append('<option value="' + reason.id + '">' + reason.reason_text + '</option>');
          });
          console.log('تم تحميل أسباب الرفض:', resp.data);
        } else {
          console.log('فشل في تحميل أسباب الرفض:', resp);
        }
      }).fail(function(xhr, status, error) {
        console.log('خطأ في تحميل أسباب الرفض:', error);
        // استخدام أسباب افتراضية في حالة الفشل
        var rejectSelect = $('#reject_reason');
        rejectSelect.empty();
        rejectSelect.append('<option value="">اختر سبب الرفض</option>');
        rejectSelect.append('<option value="1">المستلم رفض الاستلام</option>');
        rejectSelect.append('<option value="2">العنوان غير صحيح</option>');
        rejectSelect.append('<option value="3">المستلم غير موجود</option>');
        rejectSelect.append('<option value="4">مشكلة في الدفع</option>');
        rejectSelect.append('<option value="5">أسباب أخرى</option>');
      });
    }

    // تحميل الشحنات
    function renderStatusBadge(name){
      var cls = 'badge-secondary';
      if (name && name.indexOf('التسليم') !== -1) cls = 'badge-success';
      else if (name && name.indexOf('تأجيل') !== -1) cls = 'badge-warning';
      else if (name && name.indexOf('رفض') !== -1) cls = 'badge-danger';
      return '<span class="badge '+cls+' badge-status">'+(name||'')+'</span>';
    }

    function buildCard(item){
      var html = ''+
      '<div class="card shipment-card" data-id="'+item.id+'">\n'+
        '<div class="card-body">\n'+
          '<div class="d-flex align-items-center justify-content-between mb-2">\n'+
            '<div class="badge-status badge-secondary">'+(item.status_name||item.status)+'</div>\n'+
            '<div class="tracking-number">رقم البوليصة '+(item.tracking_number||'—')+'</div>\n'+
          '</div>\n'+
          '<div class="info-section">\n'+
            '<div class="info-item">\n'+
              '<i class="fas fa-user info-icon"></i>\n'+
              '<span class="info-text">المستلم '+(item.recipient_name||'—')+'</span>\n'+
              '<div class="contact-buttons">\n'+
                '<a class="btn btn-sm btn-contact" href="tel:'+((item.recipient_phone||'').replace(/[^0-9]/g,''))+'"><i class="fas fa-phone"></i> اتصال</a>\n'+
                '<a class="btn btn-sm btn-contact" target="_blank" href="https://wa.me/'+( (item.recipient_phone||'').replace(/[^0-9]/g,'') )+'?text='+encodeURIComponent('مرحباً، شحنتك رقم '+(item.tracking_number||'')+' سيتم التنسيق للتسليم.')+'"><i class="fab fa-whatsapp"></i></a>\n'+
              '</div>\n'+
            '</div>\n'+
            '<div class="info-item">\n'+
              '<i class="fas fa-location-dot info-icon"></i>\n'+
              '<span class="info-text">'+(item.governorate_name||'—')+'</span>\n'+
            '</div>\n'+
            '<div class="info-item">\n'+
              '<i class="fas fa-sack-dollar info-icon"></i>\n'+
              '<span class="info-text">المطلوب تحصيله '+(item.total_to_collect||item.cod_amount)+'</span>\n'+
            '</div>\n'+
            '<div class="info-item">\n'+
              '<i class="fas fa-coins info-icon"></i>\n'+
              '<span class="info-text">عمولة المندوب: '+(item.courier_commission_net||item.courier_commission||0)+' ج.م</span>\n'+
            '</div>\n'+
          '</div>\n'+
          '<div class="text-muted text-center mt-2" style="font-size:11px;">'+(item.date_created||'')+'</div>\n'+
          '<div class="shipment-actions mt-2 d-flex justify-content-center">\n'+
            '<button class="btn btn-sm btn-primary btn-show-detail">تفاصيل</button>\n'+
          '</div>\n'+
        '</div>\n'+
      '</div>';
      return html;
    }

    function loadShipments(){
      var params = { page:1, page_size:50 };
      $.getJSON('api/shipments.php', params, function(resp){
        var wrap = $('#cards-container');
        wrap.empty();
        if (resp && resp.success){
          var items = resp.data.filter(function(r){ return r.status==2 || r.status==3; });
           if (items.length===0) {
             wrap.append('<div class="text-center text-muted py-5"><i class="fa fa-truck"></i> لا يوجد شحنات</div>');
           } else {
             var added = 0;
             items.forEach(function(r){
               var cardHtml = buildCard(r);
               if (cardHtml && cardHtml.trim().length>0) { wrap.append(cardHtml); added++; }
             });
             if (added===0) { wrap.append('<div class="text-center text-muted py-5">لا يوجد شحنات صالحة للعرض</div>'); }
           }
        }
      });
    }

    // تحميل قائمة الحالات للفلاتر
    function buildCountersHtml(d){
      var html = '<div class="list-group mb-3">';
      d.forEach(function(x){
        html += '<a href="#" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center task-link" data-status="'+x.status+'">'
             + '<span><i class="fa '+x.icon+'"></i> '+x.title+'</span>'
             + '<span class="badge badge-primary badge-pill">'+x.val+'</span>'
             + '</a>';
      });
      return html + '</div>';
    }

    // تحميل العدادات
    function loadCounters(){
      $.getJSON('api/counters.php', function(resp){
        if(resp && resp.success){
          var d = resp.data;
          console.log('بيانات العدادات:', d);
          
          // تحويل البيانات من كائن إلى مصفوفة
          var countersArray = [
            {title:'شحنات مرتجعة', val:d.returned || 0, icon:'fa-rotate-left', status:'returned'},
            {title:'شحنات جاري التوصيل', val:d.in_delivery || 0, icon:'fa-truck-fast', status:3},
            {title:'تم الدفع جزئي', val:d.partial || 0, icon:'fa-money-bill-1-wave', status:6},
            {title:'شحنات مؤجلة', val:d.postponed || 0, icon:'fa-clock', status:7},
            {title:'شحنات مرفوضة', val:d.rejected || 0, icon:'fa-xmark', status:8},
            {title:'شحنات تم التسليم بنجاح', val:d.delivered || 0, icon:'fa-check', status:4},
            {title:'كل الشحنات', val:d.all || 0, icon:'fa-list', status:''}
          ];
          
          var html = buildCountersHtml(countersArray);
          $('#counters-container').html(html);
        }
      }).fail(function(){
        console.log('فشل في تحميل العدادات، استخدام قيم افتراضية');
        var defaultCounters = [
          {title:'شحنات مرتجعة', val:0, icon:'fa-rotate-left', status:'returned'},
          {title:'شحنات جاري التوصيل', val:0, icon:'fa-truck-fast', status:3},
          {title:'تم الدفع جزئي', val:0, icon:'fa-money-bill-1-wave', status:6},
          {title:'شحنات مؤجلة', val:0, icon:'fa-clock', status:7},
          {title:'شحنات مرفوضة', val:0, icon:'fa-xmark', status:8},
          {title:'شحنات تم التسليم بنجاح', val:0, icon:'fa-check', status:4},
          {title:'كل الشحنات', val:0, icon:'fa-list', status:''}
        ];
        var html = buildCountersHtml(defaultCounters);
        $('#counters-container').html(html);
      });
    }

    // تحميل البيانات عند تحميل الصفحة
    $(function(){
      loadStats();
      loadShipments();
      loadCounters();
      loadStatusReasons();
      
      // تحميل البيانات كل 30 ثانية
      setInterval(function(){
        loadStats();
        loadShipments();
        loadCounters();
      }, 30000);
    });

    // فتح مودال التفاصيل
    $(document).on('click','.btn-show-detail', function(){
      var card = $(this).closest('.shipment-card');
      currentParcelId = card.data('id');
      
      console.log('تم النقر على تفاصيل الشحنة رقم:', currentParcelId);
      
      // جلب تفاصيل الشحنة
      $.getJSON('api/shipments.php?id=' + currentParcelId, function(resp){
      console.log('استجابة API:', resp);
        if(resp && resp.success && resp.data){
          var item = resp.data;
          console.log('بيانات الشحنة:', item);
          
          // تحديث عنوان المودال
          $('#detailTitle').text('تفاصيل الطلب ' + (item.tracking_number || ''));
          
          // تحديث محتوى المودال
          var detailHtml = ''+
            '<div class="row">'+
              '<div class="col-md-6">'+
                '<div class="mb-3"><strong>رقم التتبع:</strong> ' + (item.tracking_number || '—') + '</div>'+
                '<div class="mb-3"><strong>المستلم:</strong> ' + (item.recipient_name || '—') + '</div>'+
                '<div class="mb-3"><strong>رقم الهاتف:</strong> ' + (item.recipient_phone || '—') + '</div>'+
                '<div class="mb-3"><strong>العنوان:</strong> ' + (item.governorate_name || '—') + ' - ' + (item.area_name || '—') + '</div>'+
              '</div>'+
              '<div class="col-md-6">'+
                '<div class="mb-3"><strong>المبلغ المطلوب تحصيله:</strong> ' + formatMoneyLatn(item.total_to_collect || item.cod_amount) + '</div>'+
                '<div class="mb-3"><strong>عمولة المندوب:</strong> ' + formatMoneyLatn(item.courier_commission_net || 0) + '</div>'+
                '<div class="mb-3"><strong>الحالة:</strong> ' + (item.status_name || item.status) + '</div>'+
                '<div class="mb-3"><strong>تاريخ الإنشاء:</strong> ' + (item.date_created || '—') + '</div>'+
              '</div>'+
            '</div>';
          
          console.log('محتوى المودال:', detailHtml);
          
          $('#detailBody').html(detailHtml);
          
          // فتح المودال
          $('#detailModal').modal('show');
          
          console.log('تم فتح المودال');
        } else {
          console.error('فشل في جلب بيانات الشحنة:', resp);
          alert('فشل في جلب بيانات الشحنة');
        }
      }).fail(function(xhr, status, error){
        console.error('خطأ في API:', error);
        alert('خطأ في الاتصال');
      });
    });

    function postUpdate(data, cb){
      $.post('api/update_status.php', data, function(resp){
        if(resp && resp.success){ cb(true); } else { alert(resp.message||'فشل التحديث'); cb(false); }
      }, 'json').fail(function(xhr){ alert('خطأ في الاتصال'); cb(false); });
    }

    function removeCard(id){ $('.shipment-card[data-id="'+id+'"]').remove(); }

    // تم التسليم
    $('#btnDelivered').on('click', function(){
      if(!currentParcelId) return;
      postUpdate({parcel_id: currentParcelId, new_status_id: 4}, function(ok){ if(ok){ removeCard(currentParcelId); $('#detailModal').modal('hide'); loadStats(); loadShipments(); }});
    });

    // تعبئة معلومات الدفع الجزئي عند فتح المودال
    $('#partialModal').on('show.bs.modal', function(){
      if (!currentParcelId) {
        alert('يرجى اختيار شحنة أولاً');
        return;
      }
      
      // إعادة تعيين الحقول
      $('#partial_paid').val('');
      $('#returned_pieces').val('0');
      $('#partial_note').val('');
      $('#payment-summary').hide();
      $('#doPartial').prop('disabled', true);
      
      // تحميل معلومات الدفع للشحنة
      $.post('api/partial_payment.php', {
        action: 'get_payment_info',
        parcel_id: currentParcelId
      }, function(resp) {
        if (resp && resp.success) {
          var data = resp.data;
          console.log('معلومات الدفع:', data);
          
          // التحقق من صحة البيانات
          if (data.total_to_collect <= 0) {
            console.error('المبلغ الإجمالي غير صحيح:', data.total_to_collect);
            alert('خطأ: المبلغ الإجمالي غير صحيح');
            return;
          }
          
          if (data.remaining_balance < 0) {
            console.error('المبلغ المتبقي غير صحيح:', data.remaining_balance);
            alert('خطأ: المبلغ المتبقي غير صحيح');
            return;
          }
          
          console.log('تحديث معلومات الشحنة:', {
            total_to_collect: data.total_to_collect,
            total_paid_so_far: data.total_paid_so_far,
            remaining_balance: data.remaining_balance,
            payment_status: data.payment_status
          });
          
          // تحديث معلومات الشحنة
          $('#payment-total-amount').text(formatMoney(data.total_to_collect));
          $('#payment-paid-amount').text(formatMoney(data.total_paid_so_far));
          $('#payment-remaining').text(formatMoney(data.remaining_balance));
          $('#payment-status').text(data.payment_status === 'paid' ? 'مدفوع كامل' : 
                                  data.payment_status === 'partial_paid' ? 'مدفوع جزئي' : 'غير مدفوع');

          // خزّن القيم الرقمية لتجنب أخطاء تحويل النصوص + سياق الحساب لعرض الصافي قبل الحفظ
          window.__currentPaymentInfo = {
            total: Number(data.total_to_collect || 0),
            paid: Number((data.total_paid_so_far != null ? data.total_paid_so_far : data.paid_amount) || 0),
            remaining: Number(data.remaining_balance || 0),
            context: data.shipment_direction || 'to_agent',
            shipping_payer: data.shipping_payer || 'sender',
            delivery_agent_fee: Number(data.delivery_agent_fee || 0),
            shipping_fees: Number(data.shipping_fees || 0)
          };
          console.log('ثبت القيم الرقمية:', window.__currentPaymentInfo);
          
          // إذا كانت الشحنة مدفوعة بالكامل، منع إدخال دفعة جديدة
          if (data.remaining_balance <= 0) {
            $('#partial_paid').prop('disabled', true);
            $('#doPartial').prop('disabled', true);
            alert('هذه الشحنة مدفوعة بالكامل');
          } else {
            $('#partial_paid').prop('disabled', false);
            $('#doPartial').prop('disabled', false);
          }
        } else {
          console.error('فشل في تحميل معلومات الدفع:', resp);
          alert('فشل في تحميل معلومات الدفع: ' + (resp.message || 'خطأ غير معروف'));
        }
      }, 'json').fail(function() {
        console.error('فشل في الاتصال بالسيرفر');
        alert('فشل في الاتصال بالسيرفر');
      });
    });
    
    // معالج إدخال المبلغ المدفوع
    $('#partial_paid').on('input', function() {
      var paymentAmount = parseLocaleNumber($(this).val());
      var remainingBalance = window.__currentPaymentInfo ? window.__currentPaymentInfo.remaining : parseLocaleNumber($('#payment-remaining').text());
      
      console.log('إدخال المبلغ:', {
        paymentAmount: paymentAmount,
        remainingBalance: remainingBalance,
        inputValue: $(this).val()
      });
      
      // التحقق من أن المتبقي صحيح
      if (remainingBalance <= 0) {
        console.log('المتبقي غير صحيح:', remainingBalance);
        alert('خطأ في حساب المبلغ المتبقي. يرجى إعادة فتح المودال.');
        $(this).val('');
        $('#payment-summary').hide();
        $('#doPartial').prop('disabled', true);
        return;
      }
      
      // التحقق من أن المبلغ صحيح
      if (paymentAmount <= 0) {
        $('#payment-summary').hide();
        $('#doPartial').prop('disabled', true);
        return;
      }
      
      if (paymentAmount <= remainingBalance) {
        // حساب القيم الجديدة
        var currentPaid = window.__currentPaymentInfo ? window.__currentPaymentInfo.paid : parseLocaleNumber($('#payment-paid-amount').text());
        var newTotalPaid = currentPaid + paymentAmount;
        var newRemaining = remainingBalance - paymentAmount;
        
        console.log('حساب القيم الجديدة:', {
          currentPaid: currentPaid,
          newTotalPaid: newTotalPaid,
          newRemaining: newRemaining
        });
        
        // عرض ملخص الدفعة
          // في الواجهة نعرض الصافي المتوقع حسب نفس القواعد
          var paidDisplay = paymentAmount;
          var ctx = window.__currentPaymentInfo || {};
          if (ctx.context === 'from_agent') {
            paidDisplay = Math.max(0, paymentAmount - (ctx.delivery_agent_fee || 0));
          } else if (ctx.context === 'to_agent' && ctx.shipping_payer === 'sender') {
            paidDisplay = Math.max(0, paymentAmount - (ctx.shipping_fees || 0));
          }
          $('#summary-new-amount').text(formatMoney(paidDisplay));
        $('#summary-total-paid').text(formatMoney(newTotalPaid));
        $('#summary-final-remaining').text(formatMoney(newRemaining));
        $('#payment-summary').show();
        
        // تفعيل زر الحفظ
        $('#doPartial').prop('disabled', false);
      } else {
        // المبلغ يتجاوز المتبقي
        alert('المبلغ المدفوع يتجاوز المبلغ المتبقي. المتبقي: ' + formatMoney(remainingBalance));
        $(this).val('');
        $('#payment-summary').hide();
        $('#doPartial').prop('disabled', true);
      }
    });
    
    // معالج إضافي للتأكد من صحة القيمة عند تغيير الحقل
    $('#partial_paid').on('change', function() {
      var paymentAmount = parseLocaleNumber($(this).val());
      var remainingBalance = window.__currentPaymentInfo ? window.__currentPaymentInfo.remaining : parseLocaleNumber($('#payment-remaining').text());
      
      console.log('تغيير المبلغ:', {
        paymentAmount: paymentAmount,
        remainingBalance: remainingBalance
      });
      
      // التحقق من صحة المبلغ
      if (paymentAmount > remainingBalance) {
        alert('المبلغ المدفوع يتجاوز المبلغ المتبقي. المتبقي: ' + formatMoney(remainingBalance));
        $(this).val('');
        $('#payment-summary').hide();
        $('#doPartial').prop('disabled', true);
      }
    });
    
    // تعبئة أسباب التأجيل/الرفض عند فتح مودالاتهما
    $('#postponeModal').on('show.bs.modal', function(){
      $('#postpone_reason').empty();
      $('#postpone_reason').append('<option value="">اختر السبب</option>');
      
      // إضافة أسباب افتراضية مباشرة
      var defaultReasons = [
        {id: 1, text: 'العميل غير متاح'},
        {id: 2, text: 'العنوان غير صحيح'},
        {id: 3, text: 'العميل يريد تأجيل التسليم'},
        {id: 4, text: 'مشكلة في الطريق'},
        {id: 5, text: 'أسباب أخرى'}
      ];
      defaultReasons.forEach(function(x){
        $('#postpone_reason').append($('<option>').val(x.id).text(x.text));
      });
      
      // محاولة تحميل الأسباب من السيرفر
      $.getJSON('api/reasons.php?status_id=7', function(r){ 
        if(r && r.success && r.data && r.data.length > 0){ 
          $('#postpone_reason').empty();
          $('#postpone_reason').append('<option value="">اختر السبب</option>');
          r.data.forEach(function(x){ 
            $('#postpone_reason').append($('<option>').val(x.id).text(x.reason_text)); 
          }); 
        }
      }).fail(function(){
        console.log('فشل في تحميل أسباب التأجيل من السيرفر، استخدام الأسباب الافتراضية');
      });
    });
    
    $('#rejectModal').on('show.bs.modal', function(){
      $('#reject_reason').empty();
      $('#reject_reason').append('<option value="">اختر السبب</option>');
      
      // إضافة أسباب افتراضية مباشرة
      var defaultReasons = [
        {id: 1, text: 'العميل رفض استلام الشحنة'},
        {id: 2, text: 'العنوان غير صحيح'},
        {id: 3, text: 'العميل غير موجود'},
        {id: 4, text: 'الشحنة تالفة'},
        {id: 5, text: 'أسباب أخرى'}
      ];
      defaultReasons.forEach(function(x){
        $('#reject_reason').append($('<option>').val(x.id).text(x.text));
      });
      
      // محاولة تحميل الأسباب من السيرفر
      $.getJSON('api/reasons.php?status_id=8', function(r){ 
        if(r && r.success && r.data && r.data.length > 0){ 
          $('#reject_reason').empty();
          $('#reject_reason').append('<option value="">اختر السبب</option>');
          r.data.forEach(function(x){ 
            $('#reject_reason').append($('<option>').val(x.id).text(x.reason_text)); 
          }); 
        }
      }).fail(function(){
        console.log('فشل في تحميل أسباب الرفض من السيرفر، استخدام الأسباب الافتراضية');
      });
    });

    // تنفيذ الدفع الجزئي
    $('#doPartial').on('click', function(){
      if(!currentParcelId) return;
      
      var paymentAmount = parseLocaleNumber($('#partial_paid').val());
      var returnedPieces = parseInt($('#returned_pieces').val()) || 0;
      var paymentNote = $('#partial_note').val() || '';
      
      if (paymentAmount <= 0) {
        alert('يرجى إدخال مبلغ صحيح');
        return;
      }
      
      // تعطيل الزر لمنع النقر المتكرر
      $('#doPartial').prop('disabled', true).text('جاري الحفظ...');
      
      // إرسال طلب الدفع الجزئي
      $.post('api/partial_payment.php', {
        action: 'process_payment',
        parcel_id: currentParcelId,
        payment_amount: paymentAmount,
        payment_note: paymentNote,
        returned_pieces: returnedPieces
      }, function(resp) {
        if (resp && resp.success) {
          // نجح الدفع
          alert('تم تسجيل الدفعة بنجاح!\nالمبلغ: ' + formatMoney(paymentAmount) + '\nالمتبقي: ' + formatMoney(resp.data.remaining_balance));
          
          // إغلاق المودالات وتحديث البيانات
          $('#partialModal').modal('hide');
          $('#detailModal').modal('hide');
          
          // تحديث الإحصائيات والشحنات
          loadStats();
          loadShipments();
          loadCounters();
          
          // إعادة تعيين الزر
          $('#doPartial').prop('disabled', false).text('حفظ الدفعة');
        } else {
          // فشل الدفع
          alert('فشل في تسجيل الدفعة: ' + (resp.message || 'خطأ غير معروف'));
          $('#doPartial').prop('disabled', false).text('حفظ الدفعة');
        }
      }, 'json').fail(function() {
        alert('فشل في الاتصال بالسيرفر');
        $('#doPartial').prop('disabled', false).text('حفظ الدفعة');
      });
    });

    // تم التأجيل: اختيار سبب + تاريخ
    $('#doPostpone').on('click', function(){
      if(!currentParcelId) return;
      var data = {
        parcel_id: currentParcelId,
        new_status_id: 7,
        reason_id: $('#postpone_reason').val(),
        reason_note: $('#postpone_note').val()||'',
        postpone_date: $('#postpone_date').val()
      };
      postUpdate(data, function(ok){ if(ok){ $('#postponeModal').modal('hide'); $('#detailModal').modal('hide'); loadStats(); loadShipments(); }});
    });

    // تم الرفض: اختيار سبب
    $('#doReject').on('click', function(){
      if(!currentParcelId) return;
      var data = {
        parcel_id: currentParcelId,
        new_status_id: 8,
        reason_id: $('#reject_reason').val(),
        reason_note: $('#reject_note').val()||''
      };
      postUpdate(data, function(ok){ if(ok){ $('#rejectModal').modal('hide'); $('#detailModal').modal('hide'); loadStats(); loadShipments(); }});
    });
    
    // إدارة aria-hidden للمودالات
    $('.modal').on('show.bs.modal', function(){
      $(this).removeAttr('aria-hidden');
    });
    
    $('.modal').on('hidden.bs.modal', function(){
      $(this).attr('aria-hidden', 'true');
      // إزالة التركيز من أي عنصر داخل المودال
      $(this).find(':focus').blur();
    });
  </script>
<?php include __DIR__.'/partials/layout_bottom.php'; ?>


