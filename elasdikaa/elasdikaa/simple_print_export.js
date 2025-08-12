// simple_print_export.js - دوال مبسطة للطباعة والتصدير

// دالة التصدير المبسطة
function exportData(type) {
    const selectedParcels = $('.parcel-checkbox:checked').map(function() {
        return $(this).val();
    }).get();
    
    let exportUrl = 'export_parcels.php?type=' + type;
    
    if (selectedParcels.length > 0) {
        exportUrl += '&selected=' + selectedParcels.join(',');
    }
    
    // إضافة معايير الفلترة الحالية
    const currentUrl = new URL(window.location);
    const filterParams = currentUrl.searchParams;
    if (filterParams.toString()) {
        exportUrl += '&' + filterParams.toString();
    }
    
    // تحميل الملف
    window.open(exportUrl, '_blank');
    showMessage('نجاح', 'تم بدء عملية التصدير', 'success');
}

// دالة الطباعة المبسطة
function printData(type) {
    const selectedParcels = $('.parcel-checkbox:checked').map(function() {
        return $(this).val();
    }).get();
    
    if (type === 'label') {
        // طباعة ملصق واحد
        if (selectedParcels.length === 1) {
            const printWindow = window.open('print_label.php?id=' + selectedParcels[0], '_blank');
            printWindow.onload = function() {
                printWindow.print();
            };
        } else {
            showMessage('تنبيه', 'يرجى تحديد شحنة واحدة لطباعة الملصق', 'warning');
        }
    } else {
        let printUrl = 'print_parcels.php?type=' + type;
        
        if (selectedParcels.length > 0) {
            printUrl += '&selected=' + selectedParcels.join(',');
        }
        
        // إضافة معايير الفلترة الحالية
        const currentUrl = new URL(window.location);
        const filterParams = currentUrl.searchParams;
        if (filterParams.toString()) {
            printUrl += '&' + filterParams.toString();
        }
        
        // فتح نافذة الطباعة
        const printWindow = window.open(printUrl, '_blank');
        printWindow.onload = function() {
            printWindow.print();
        };
    }
    
    showMessage('نجاح', 'تم فتح نافذة الطباعة', 'success');
}

// دالة عرض الرسائل المبسطة
function showMessage(title, message, type) {
    const alertClass = type === 'success' ? 'alert-success' : 
                      type === 'warning' ? 'alert-warning' : 
                      type === 'error' ? 'alert-danger' : 'alert-info';
    
    const alertHtml = `
        <div class="alert ${alertClass} alert-dismissible fade show" role="alert">
            <strong>${title}:</strong> ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    `;
    
    // إضافة الرسالة في أعلى الصفحة
    $('.container-fluid').first().prepend(alertHtml);
    
    // إزالة الرسالة بعد 3 ثواني
    setTimeout(function() {
        $('.alert').fadeOut();
    }, 3000);
}

// إضافة مستمعي الأحداث للأزرار
$(document).ready(function() {
    // أزرار التصدير
    $('#exportExcelBtn').on('click', function() {
        exportData('excel');
    });
    
    $('#exportCsvBtn').on('click', function() {
        exportData('csv');
    });
    
    $('#exportPdfBtn').on('click', function() {
        exportData('pdf');
    });
    
    // أزرار الطباعة
    $('#printWaybillBtn').on('click', function() {
        printData('waybill');
    });
    
    $('#printLabelBtn').on('click', function() {
        printData('label');
    });
    
    $('#printSummaryBtn').on('click', function() {
        printData('summary');
    });
    
    // زر طباعة الملصق من التفاصيل
    $('#printLabelBtn').on('click', function() {
        if (currentParcelId) {
            const printWindow = window.open('print_label.php?id=' + currentParcelId, '_blank');
            printWindow.onload = function() {
                printWindow.print();
            };
        }
    });
    
    // زر طباعة البوليصة من التفاصيل
    $('#printFullWaybillBtn').on('click', function() {
        if (currentParcelId) {
            const printWindow = window.open('parcel_details.php?id=' + currentParcelId + '&view_type=full', '_blank');
            printWindow.onload = function() {
                printWindow.print();
            };
        }
    });
    
    // زر طباعة ملصق QR من التفاصيل
    $('#printQrLabelBtn').on('click', function() {
        if (currentParcelId) {
            const printWindow = window.open('parcel_details.php?id=' + currentParcelId + '&view_type=label', '_blank');
            printWindow.onload = function() {
                printWindow.print();
            };
        }
    });
});
