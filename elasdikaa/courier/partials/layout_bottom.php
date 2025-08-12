  <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>
  <script>
    // Sidebar open/close shared
    function openSidebar(){ $('#sidebar').addClass('open'); $('#sidebarOverlay').show(); }
    function closeSidebar(){ $('#sidebar').removeClass('open'); $('#sidebarOverlay').hide(); }
    $(document).on('click','#btnSidebarToggle', function(){ openSidebar(); });
    $(document).on('click','#btnSidebarClose, #sidebarOverlay', function(){ closeSidebar(); });

    // Load sidebar counters (reuse counters API)
    function buildCountersHtml(list){
      var html='';
      list.forEach(function(x){
        html += '<a href="#" class="list-group-item d-flex justify-content-between align-items-center task-link" data-status="'+x.status+'">'
             + '<span><i class="fa '+x.icon+'"></i> '+x.title+'</span>'
             + '<span class="badge badge-primary badge-pill">'+x.val+'</span>'
             + '</a>';
      });
      return html;
    }
    function loadSidebarCounters(){
      $.getJSON('api/counters.php', function(resp){ if(resp&&resp.success){
        var d = resp.data;
        var html = ''+
          '<a href="status.php?st=9,10" class="list-group-item d-flex justify-content-between align-items-center"><span><i class="fa fa-rotate-left"></i> شحنات مرتجعة</span><span class="badge badge-primary">'+d.returned+'</span></a>'+
          '<a href="status.php?st=3" class="list-group-item d-flex justify-content-between align-items-center"><span><i class="fa fa-truck-fast"></i> شحنات جاري التوصيل</span><span class="badge badge-primary">'+d.in_delivery+'</span></a>'+
          '<a href="status.php?st=6" class="list-group-item d-flex justify-content-between align-items-center"><span><i class="fa fa-money-bill-1-wave"></i> تم الدفع جزئي</span><span class="badge badge-primary">'+d.partial+'</span></a>'+
          '<a href="status.php?st=7" class="list-group-item d-flex justify-content-between align-items-center"><span><i class="fa fa-clock"></i> شحنات مؤجلة</span><span class="badge badge-primary">'+d.postponed+'</span></a>'+
          '<a href="status.php?st=8" class="list-group-item d-flex justify-content-between align-items-center"><span><i class="fa fa-xmark"></i> شحنات مرفوضة</span><span class="badge badge-primary">'+d.rejected+'</span></a>'+
          '<a href="status.php?st=4" class="list-group-item d-flex justify-content-between align-items-center"><span><i class="fa fa-check"></i> شحنات تم التسليم بنجاح</span><span class="badge badge-primary">'+d.delivered+'</span></a>'+
          '<a href="status.php" class="list-group-item d-flex justify-content-between align-items-center"><span><i class="fa fa-list"></i> كل الشحنات</span><span class="badge badge-primary">'+d.all+'</span></a>';
        $('#sidebarCounters').html(html);
      }});
    }
    $(function(){ loadSidebarCounters(); setInterval(loadSidebarCounters, 30000); });

    // Clicking sidebar task navigates via filter param
    $(document).on('click','.task-link', function(e){
      e.preventDefault();
      var st = $(this).data('status');
      window.location.href = 'status.php' + (st? ('?st='+encodeURIComponent(st)) : '');
    });
    
    // نظام الإشعارات
    var lastNotificationCount = 0;
    
    function loadNotifications(isInitialLoad = false){
      $.getJSON('api/notifications.php', function(resp){
        if(resp && resp.success){
          var count = resp.count || 0;
          var $badge = $('#notificationCount');
          
          // تشغيل الصوت فقط عند وجود إشعارات جديدة وليس عند التحميل الأولي
          if(!isInitialLoad && count > lastNotificationCount && lastNotificationCount >= 0){
            playNotificationSound();
          }
          
          if(count > 0){
            $badge.text(count).show();
          } else {
            $badge.hide();
          }
          
          lastNotificationCount = count;
          
          // تحميل قائمة الإشعارات في المودال
          var html = '';
          if(resp.notifications && resp.notifications.length > 0){
            resp.notifications.forEach(function(notif){
              html += '<div class="alert alert-info mb-2">' +
                     '<div class="d-flex justify-content-between align-items-start">' +
                     '<div class="d-flex justify-content-between align-items-start w-100">' +
                     '<div><strong>' + notif.title + '</strong><br>' + notif.message + '</div>' +
                     '<div class="ml-2">' +
                     '<button class="btn btn-sm btn-success mark-read" data-id="'+notif.id+'"><i class="fas fa-check"></i></button>' +
                     '<button class="btn btn-sm btn-danger mark-hide" data-id="'+notif.id+'"><i class="fas fa-times"></i></button>' +
                     '</div>' +
                     '</div>' +
                     '<small class="text-muted">' + (notif.time || '') + '</small>' +
                     '</div></div>';
            });
          } else {
            html = '<div class="text-center text-muted py-3">لا توجد إشعارات جديدة</div>';
          }
          $('#notificationsList').html(html);
        }
      });
    }
    
    // تشغيل صوت الإشعار
    function playNotificationSound(){
      try {
        // استخدام الصوت المدمج مباشرة
        playBeepSound();
      } catch(e) {
        console.log('لا يمكن تشغيل الصوت:', e);
        // محاولة الطريقة التقليدية
        playTraditionalSound();
      }
    }
    
    // تشغيل صوت الإشعار للطلبات الجديدة
    function playNewShipmentSound(){
      try {
        // صوت مختلف للطلبات الجديدة
        if (window.AudioContext || window.webkitAudioContext) {
          var audioContext = new (window.AudioContext || window.webkitAudioContext)();
          
          if (audioContext.state === 'suspended') {
            audioContext.resume();
          }
          
          // صوت مزدوج للطلبات الجديدة
          var oscillator1 = audioContext.createOscillator();
          var gainNode1 = audioContext.createGain();
          
          oscillator1.connect(gainNode1);
          gainNode1.connect(audioContext.destination);
          
          oscillator1.frequency.setValueAtTime(1000, audioContext.currentTime);
          oscillator1.frequency.setValueAtTime(800, audioContext.currentTime + 0.1);
          
          gainNode1.gain.setValueAtTime(0.4, audioContext.currentTime);
          gainNode1.gain.exponentialRampToValueAtTime(0.01, audioContext.currentTime + 0.2);
          
          oscillator1.start(audioContext.currentTime);
          oscillator1.stop(audioContext.currentTime + 0.2);
          
          // صوت ثاني
          setTimeout(function(){
            var oscillator2 = audioContext.createOscillator();
            var gainNode2 = audioContext.createGain();
            
            oscillator2.connect(gainNode2);
            gainNode2.connect(audioContext.destination);
            
            oscillator2.frequency.setValueAtTime(1200, audioContext.currentTime);
            oscillator2.frequency.setValueAtTime(1000, audioContext.currentTime + 0.1);
            
            gainNode2.gain.setValueAtTime(0.3, audioContext.currentTime);
            gainNode2.gain.exponentialRampToValueAtTime(0.01, audioContext.currentTime + 0.2);
            
            oscillator2.start(audioContext.currentTime);
            oscillator2.stop(audioContext.currentTime + 0.2);
          }, 200);
          
        } else {
          playBeepSound();
        }
      } catch(e) {
        console.log('لا يمكن تشغيل صوت الطلبات الجديدة:', e);
        playBeepSound();
      }
    }
    

    
    // تشغيل صوت باستخدام Web Audio API
    function playBeepSound(){
      try {
        // إنشاء AudioContext فقط عند الحاجة
        if (!window.notificationAudioContext) {
          window.notificationAudioContext = new (window.AudioContext || window.webkitAudioContext)();
        }
        
        var audioContext = window.notificationAudioContext;
        
        // استئناف AudioContext إذا كان معلق
        if (audioContext.state === 'suspended') {
          audioContext.resume();
        }
        
        var oscillator = audioContext.createOscillator();
        var gainNode = audioContext.createGain();
        
        oscillator.connect(gainNode);
        gainNode.connect(audioContext.destination);
        
        oscillator.frequency.setValueAtTime(800, audioContext.currentTime);
        oscillator.frequency.setValueAtTime(600, audioContext.currentTime + 0.1);
        oscillator.frequency.setValueAtTime(800, audioContext.currentTime + 0.2);
        
        gainNode.gain.setValueAtTime(0.3, audioContext.currentTime);
        gainNode.gain.exponentialRampToValueAtTime(0.01, audioContext.currentTime + 0.3);
        
        oscillator.start(audioContext.currentTime);
        oscillator.stop(audioContext.currentTime + 0.3);
        
        // تشغيل صوت ثاني بعد 200 مللي ثانية
        setTimeout(function(){
          var oscillator2 = audioContext.createOscillator();
          var gainNode2 = audioContext.createGain();
          
          oscillator2.connect(gainNode2);
          gainNode2.connect(audioContext.destination);
          
          oscillator2.frequency.setValueAtTime(1000, audioContext.currentTime);
          oscillator2.frequency.setValueAtTime(800, audioContext.currentTime + 0.1);
          
          gainNode2.gain.setValueAtTime(0.2, audioContext.currentTime);
          gainNode2.gain.exponentialRampToValueAtTime(0.01, audioContext.currentTime + 0.2);
          
          oscillator2.start(audioContext.currentTime);
          oscillator2.stop(audioContext.currentTime + 0.2);
        }, 200);
        
      } catch(e) {
        console.log('فشل Web Audio API:', e);
        playTraditionalSound();
      }
    }
    
    // الطريقة التقليدية
    function playTraditionalSound(){
      try {
        var audio = new Audio('data:audio/wav;base64,UklGRnoGAABXQVZFZm10IBAAAAABAAEAQB8AAEAfAAABAAgAZGF0YQoGAACBhYqFbF1fdJivrJBhNjVgodDbq2EcBj+a2/LDciUFLIHO8tiJNwgZaLvt559NEAxQp+PwtmMcBjiR1/LMeSwFJHfH8N2QQAoUXrTp66hVFApGn+DyvmwhBSuBzvLZiTYIG2m98OScTgwOUarm7blmGgU7k9n1unEiBC13yO/eizEIHWq+8+OWT');
        audio.volume = 0.7;
        audio.play();
        
        setTimeout(function(){
          var audio2 = new Audio('data:audio/wav;base64,UklGRnoGAABXQVZFZm10IBAAAAABAAEAQB8AAEAfAAABAAgAZGF0YQoGAACBhYqFbF1fdJivrJBhNjVgodDbq2EcBj+a2/LDciUFLIHO8tiJNwgZaLvt559NEAxQp+PwtmMcBjiR1/LMeSwFJHfH8N2QQAoUXrTp66hVFApGn+DyvmwhBSuBzvLZiTYIG2m98OScTgwOUarm7blmGgU7k9n1unEiBC13yO/eizEIHWq+8+OWT');
          audio2.volume = 0.5;
          audio2.play();
        }, 200);
        
      } catch(e) {
        console.log('فشل الطريقة التقليدية:', e);
      }
    }
    
    // فتح مودال الإشعارات
    $(document).on('click', '#btnNotifications', function(){
      $('#notificationsModal').modal('show');
    });
    
    // تحميل الإشعارات عند فتح المودال
    $('#notificationsModal').on('show.bs.modal', function(){
      // إصلاح مشكلة aria-hidden
      $(this).removeAttr('aria-hidden');
      loadNotifications(true); // تحميل بدون صوت
    });
    
    // إصلاح مشكلة aria-hidden عند إغلاق المودال
    $('#notificationsModal').on('hidden.bs.modal', function(){
      // إزالة التركيز من أي عنصر داخل المودال
      $(this).find(':focus').blur();
      // إعادة تعيين aria-hidden
      $(this).attr('aria-hidden', 'true');
    });
    
    // معالجة أزرار الإشعارات
    $(document).on('click', '.mark-read', function(){
      var notifId = $(this).data('id');
      var $notif = $(this).closest('.alert');
      
      // إخفاء الإشعار
      $notif.fadeOut(300, function(){
        $(this).remove();
        // تحديث العداد
        updateNotificationCount();
      });
    });
    
    $(document).on('click', '.mark-hide', function(){
      var notifId = $(this).data('id');
      var $notif = $(this).closest('.alert');
      
      // إخفاء الإشعار
      $notif.fadeOut(300, function(){
        $(this).remove();
        // تحديث العداد
        updateNotificationCount();
      });
    });
    
    // تحديث عداد الإشعارات
    function updateNotificationCount(){
      var visibleCount = $('#notificationsList .alert').length;
      var $badge = $('#notificationCount');
      
      if(visibleCount > 0){
        $badge.text(visibleCount).show();
      } else {
        $badge.hide();
      }
      
      // تحديث المتغير العام
      lastNotificationCount = visibleCount;
    }
    
    // تحميل الإشعارات كل دقيقة
    $(function(){
      // تحميل أولي بدون صوت
      loadNotifications(true);
      // تحديث كل 10 ثواني بدلاً من دقيقة
      setInterval(function(){ loadNotifications(false); }, 10000);
      
      // فحص الطلبات الجديدة كل 30 ثانية
      setInterval(function(){ checkNewShipments(); }, 30000);
    });
    
    // فحص الطلبات الجديدة
    function checkNewShipments(){
      $.getJSON('api/notifications.php?check_new=1', function(resp){
        if(resp && resp.success && resp.new_shipments && resp.new_shipments.length > 0){
          // إظهار إشعار للطلبات الجديدة
          resp.new_shipments.forEach(function(shipment){
            showNewShipmentNotification(shipment);
          });
          
          // تشغيل الصوت الجديد للطلبات الجديدة
          playNewShipmentSound();
          
          // تحديث الإحصائيات
          if(typeof loadStats === 'function') loadStats();
          if(typeof loadShipments === 'function') loadShipments();
        }
      });
    }
    
    // إظهار إشعار للطلب الجديد
    function showNewShipmentNotification(shipment){
      var notification = $('<div class="alert alert-success alert-dismissible fade show position-fixed" style="top:20px; right:20px; z-index:9999; min-width:300px;">' +
        '<button type="button" class="close" data-dismiss="alert">&times;</button>' +
        '<h6><i class="fas fa-truck"></i> طلب جديد!</h6>' +
        '<div>رقم التتبع: ' + (shipment.tracking_number || '—') + '</div>' +
        '<div>المستلم: ' + (shipment.recipient_name || '—') + '</div>' +
        '<div>المبلغ: ' + (shipment.cod_amount || 0) + ' ج.م</div>' +
        '<div>العمولة: ' + (shipment.courier_commission_net || 0) + ' ج.م</div>' +
        '</div>');
      
      $('body').append(notification);
      
      // إخفاء الإشعار تلقائياً بعد 8 ثواني
      setTimeout(function(){
        notification.alert('close');
      }, 8000);
    }
  </script>
</body>
</html>


