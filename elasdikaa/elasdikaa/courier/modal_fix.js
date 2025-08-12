// إصلاح مشكلة عرض البيانات في المودال
$(document).ready(function() {
    // حفظ البيانات في الذاكرة
    window.shipmentsData = [];
    
    // تحديث دالة loadShipments لحفظ البيانات
    var originalLoadShipments = window.loadShipments;
    window.loadShipments = function() {
        var params = { page:1, page_size:50 };
        $.getJSON('api/shipments.php', params, function(resp){
            var wrap = $('#cards-container');
            wrap.empty();
            if (resp && resp.success){
                var items = resp.data.filter(function(r){ return r.status==2 || r.status==3; });
                
                // حفظ البيانات في الذاكرة للاستخدام في المودال
                window.shipmentsData = items;
                console.log('Shipments data saved:', window.shipmentsData);
                
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
    };
    
    // فتح مودال التفاصيل
    $(document).on('click','.btn-show-detail', function(){
        var card = $(this).closest('.shipment-card');
        var currentParcelId = card.data('id');
        
        console.log('Button clicked for parcel ID:', currentParcelId);
        console.log('Available shipments data:', window.shipmentsData);
        
        // البحث عن البيانات الأصلية في الذاكرة
        var originalData = null;
        
        if (window.shipmentsData && window.shipmentsData.length > 0) {
            originalData = window.shipmentsData.find(function(item) {
                return item.id == currentParcelId;
            });
            console.log('Found original data:', originalData);
        }
        
        // إذا لم نجد البيانات، نجلبها من API
        if (!originalData) {
            console.log('Fetching data from API for ID:', currentParcelId);
            $.getJSON('api/shipments.php?id=' + currentParcelId, function(resp) {
                if (resp && resp.success && resp.data) {
                    displayModalData(resp.data);
                } else {
                    console.log('API call failed, using fallback');
                    // استخدام البيانات من البطاقة كبديل
                    displayModalDataFromCard(card);
                }
            });
            return;
        }
        
        // عرض البيانات الأصلية
        displayModalData(originalData);
    });
    
    // دالة عرض البيانات في المودال
    function displayModalData(data) {
        console.log('Displaying data in modal:', data);
        
        // تنظيف وتأمين البيانات
        var trackingNumber = data.tracking_number || 'غير محدد';
        var recipientName = data.recipient_name || 'غير محدد';
        var location = (data.governorate_name || '') + (data.area_name ? ' - ' + data.area_name : '') || 'غير محدد';
        var amount = data.total_to_collect || data.cod_amount || '0';
        var commission = data.courier_commission_net || data.courier_commission || '0';
        var date = data.date_created || 'غير محدد';
        var status = data.status_name || data.status || 'غير محدد';
        var phone = data.recipient_phone || 'غير محدد';
        
        console.log('Processed data:', {
            trackingNumber: trackingNumber,
            recipientName: recipientName,
            location: location,
            amount: amount,
            commission: commission,
            date: date,
            status: status,
            phone: phone
        });
        
        var body = ''+
            '<div class="row">'+
                '<div class="col-md-6">'+
                    '<div class="mb-2"><strong>رقم التتبع:</strong> ' + trackingNumber + '</div>'+
                    '<div class="mb-2"><strong>المستلم:</strong> ' + recipientName + '</div>'+
                    '<div class="mb-2"><strong>العنوان:</strong> ' + location + '</div>'+
                    '<div class="mb-2"><strong>رقم الهاتف:</strong> ' + phone + '</div>'+
                '</div>'+
                '<div class="col-md-6">'+
                    '<div class="mb-2"><strong>المبلغ:</strong> ' + amount + ' ج.م</div>'+
                    '<div class="mb-2"><strong>الحالة:</strong> ' + status + '</div>'+
                    '<div class="mb-2"><strong>التاريخ:</strong> ' + date + '</div>'+
                    '<div class="mb-2"><strong>العمولة:</strong> ' + commission + ' ج.م</div>'+
                '</div>'+
            '</div>';
        
        console.log('HTML body:', body);
        
        $('#detailTitle').text('تفاصيل الطلب ' + trackingNumber);
        $('#detailBody').html(body);
        $('#detailModal').modal('show');
    }
    
    // دالة عرض البيانات من البطاقة (كبديل)
    function displayModalDataFromCard(card) {
        console.log('Using fallback data from card');
        
        var trackingNumber = card.find('.tracking-number').text().replace('رقم البوليصة ', '');
        var recipientName = card.find('.info-text').first().text().replace('المستلم ', '');
        var location = card.find('.info-text').eq(1).text();
        var amount = card.find('.info-text').eq(2).text().replace('المطلوب تحصيله ', '');
        var commission = card.find('.info-text').eq(3).text().replace('عمولة المندوب: ', '').replace(' ج.م', '');
        var date = card.find('.text-muted').last().text();
        var status = card.find('.badge-status').text();
        var phone = card.find('.btn-contact').first().attr('href') ? card.find('.btn-contact').first().attr('href').replace('tel:', '') : '';
        
        var body = ''+
            '<div class="row">'+
                '<div class="col-md-6">'+
                    '<div class="mb-2"><strong>رقم التتبع:</strong> ' + trackingNumber + '</div>'+
                    '<div class="mb-2"><strong>المستلم:</strong> ' + recipientName + '</div>'+
                    '<div class="mb-2"><strong>العنوان:</strong> ' + location + '</div>'+
                    '<div class="mb-2"><strong>رقم الهاتف:</strong> ' + phone + '</div>'+
                '</div>'+
                '<div class="col-md-6">'+
                    '<div class="mb-2"><strong>المبلغ:</strong> ' + amount + ' ج.م</div>'+
                    '<div class="mb-2"><strong>الحالة:</strong> ' + status + '</div>'+
                    '<div class="mb-2"><strong>التاريخ:</strong> ' + date + '</div>'+
                    '<div class="mb-2"><strong>العمولة:</strong> ' + commission + ' ج.م</div>'+
                '</div>'+
            '</div>';
        
        $('#detailTitle').text('تفاصيل الطلب ' + trackingNumber);
        $('#detailBody').html(body);
        $('#detailModal').modal('show');
    }
    
    console.log('Modal fix script loaded successfully');
});



