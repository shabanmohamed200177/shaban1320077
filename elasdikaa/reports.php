<?php include 'db_connect.php' ?>
<div class="col-md-12">
    <div class="card">
        <div class="card-header">
            <h4><b>تقارير النظام</b></h4>
        </div>
        <div class="card-body">
            <ul class="nav nav-tabs" id="myTab" role="tablist">
                <li class="nav-item">
                    <a class="nav-link active" id="general-tab" data-toggle="tab" href="#general" role="tab" aria-controls="general" aria-selected="true">تقرير عام</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" id="client-tab" data-toggle="tab" href="#client" role="tab" aria-controls="client" aria-selected="false">تقرير العملاء</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" id="agent-tab" data-toggle="tab" href="#agent" role="tab" aria-controls="agent" aria-selected="false">تقرير الوكلاء</a>
                </li>
            </ul>

            <div class="tab-content" id="myTabContent">
                <div class="tab-pane fade show active" id="general" role="tabpanel" aria-labelledby="general-tab">
                    <form id="general-report-filter" class="mt-3">
                        <div class="row align-items-end">
                            <div class="col-md-3">
                                <label for="general_date_from" class="control-label">من تاريخ</label>
                                <input type="date" class="form-control" name="date_from" value="<?php echo isset($_GET['date_from']) ? date("Y-m-d",strtotime($_GET['date_from'])) : '' ?>">
                            </div>
                            <div class="col-md-3">
                                <label for="general_date_to" class="control-label">إلى تاريخ</label>
                                <input type="date" class="form-control" name="date_to" value="<?php echo isset($_GET['date_to']) ? date("Y-m-d",strtotime($_GET['date_to'])) : '' ?>">
                            </div>
                            <div class="col-md-3">
                                <label for="general_status" class="control-label">الحالة</label>
                                <select name="status" class="form-control">
                                    <option value="all" <?php echo isset($_GET['status']) && $_GET['status'] == 'all' ? 'selected' : '' ?>>الكل</option>
                                    <option value="0" <?php echo isset($_GET['status']) && $_GET['status'] == '0' ? 'selected' : '' ?>>تم قبول الشحنة من قبل المندوب</option>
                                    <option value="1" <?php echo isset($_GET['status']) && $_GET['status'] == '1' ? 'selected' : '' ?>>تم جمعها</option>
                                    <option value="2" <?php echo isset($_GET['status']) && $_GET['status'] == '2' ? 'selected' : '' ?>>تم شحنها</option>
                                    <option value="3" <?php echo isset($_GET['status']) && $_GET['status'] == '3' ? 'selected' : '' ?>>في الطريق</option>
                                    <option value="4" <?php echo isset($_GET['status']) && $_GET['status'] == '4' ? 'selected' : '' ?>>وصلت للوجهة</option>
                                    <option value="5" <?php echo isset($_GET['status']) && $_GET['status'] == '5' ? 'selected' : '' ?>>خرجت للتوصيل</option>
                                    <option value="6" <?php echo isset($_GET['status']) && $_GET['status'] == '6' ? 'selected' : '' ?>>جاهزة للاستلام</option>
                                    <option value="7" <?php echo isset($_GET['status']) && $_GET['status'] == '7' ? 'selected' : '' ?>>تم التوصيل</option>
                                    <option value="8" <?php echo isset($_GET['status']) && $_GET['status'] == '8' ? 'selected' : '' ?>>تم استلامها</option>
                                    <option value="9" <?php echo isset($_GET['status']) && $_GET['status'] == '9' ? 'selected' : '' ?>>فشل في التوصيل</option>
                                </select>
                            </div>
                            <div class="col-md-3 align-self-end">
                                <button class="btn btn-primary btn-sm"><i class="fa fa-filter"></i> فلتر</button>
                                <button class="btn btn-sm btn-info" type="button" data-toggle="modal" data-target="#exportModal"><i class="fa fa-file-excel"></i> تصدير</button>
                            </div>
                        </div>
                    </form>
                    <div class="card mt-3" id="general-report-field">
                        <div class="card-body">
                            <div class="row" id="general-report-data">
                                <div class="col-md-12">
                                    <table class="table table-bordered" id="general-report-list">
                                        <thead>
                                            <tr>
                                                <th>#</th>
                                                <th>التاريخ</th>
                                                <th>رقم الشحنة</th>
                                                <th>المرسل</th>
                                                <th>المستلم</th>
                                                <th>المندوب</th>
                                                <th>المحافظة</th>
                                                <th>القيمة</th>
                                                <th>الحالة</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="tab-pane fade" id="client" role="tabpanel" aria-labelledby="client-tab">
                    <form id="client-report-filter" class="mt-3">
                        <div class="row align-items-end">
                            <div class="col-md-4">
                                <label for="client_id" class="control-label">العميل</label>
                                <select name="client_id" class="form-control select2" required>
                                    <option value="">-- اختر عميل --</option>
                                    <?php 
                                    $clients = $conn->query("SELECT * FROM customers ORDER BY name ASC");
                                    while($row = $clients->fetch_assoc()):
                                    ?>
                                        <option value="<?php echo $row['id'] ?>"><?php echo ucwords($row['name']) ?></option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label for="client_status" class="control-label">الحالة</label>
                                <select name="status" class="form-control">
                                    <option value="all">الكل</option>
                                    <?php
                                    $status_arr = array("تم قبول الشحنة من قبل المندوب","تم جمعها","تم شحنها","في الطريق","وصلت للوجهة","خرجت للتوصيل","جاهزة للاستلام","تم التوصيل","تم استلامها","فشل في التوصيل");
                                    foreach($status_arr as $k => $v):
                                    ?>
                                    <option value="<?php echo $k ?>"><?php echo $v ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-4 align-self-end">
                                <button class="btn btn-primary btn-sm"><i class="fa fa-filter"></i> فلتر</button>
                                <button class="btn btn-sm btn-info" type="button" data-toggle="modal" data-target="#exportModal"><i class="fa fa-file-excel"></i> تصدير</button>
                            </div>
                        </div>
                    </form>
                    <div class="card mt-3" id="client-report-field" style="display:none;">
                        <div class="card-body">
                            <div class="row" id="client-report-data">
                                <div class="col-md-12">
                                    <table class="table table-bordered" id="client-report-list">
                                        <thead>
                                            <tr>
                                                <th>#</th>
                                                <th>التاريخ</th>
                                                <th>رقم الشحنة</th>
                                                <th>المستلم</th>
                                                <th>المندوب</th>
                                                <th>القيمة</th>
                                                <th>الحالة</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="tab-pane fade" id="agent" role="tabpanel" aria-labelledby="agent-tab">
                    <form id="agent-report-filter" class="mt-3">
                        <div class="row align-items-end">
                            <div class="col-md-4">
                                <label for="agent_id" class="control-label">الوكيل/المندوب</label>
                                <select name="agent_id" class="form-control select2" required>
                                    <option value="">-- اختر وكيل --</option>
                                    <?php 
                                    $agents = $conn->query("SELECT * FROM couriers ORDER BY name ASC");
                                    while($row = $agents->fetch_assoc()):
                                    ?>
                                        <option value="<?php echo $row['id'] ?>"><?php echo ucwords($row['name']) ?></option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label for="agent_status" class="control-label">الحالة</label>
                                <select name="status" class="form-control">
                                    <option value="all">الكل</option>
                                    <?php
                                    $status_arr = array("تم قبول الشحنة من قبل المندوب","تم جمعها","تم شحنها","في الطريق","وصلت للوجهة","خرجت للتوصيل","جاهزة للاستلام","تم التوصيل","تم استلامها","فشل في التوصيل");
                                    foreach($status_arr as $k => $v):
                                    ?>
                                    <option value="<?php echo $k ?>"><?php echo $v ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-4 align-self-end">
                                <button class="btn btn-primary btn-sm"><i class="fa fa-filter"></i> فلتر</button>
                                <button class="btn btn-sm btn-info" type="button" data-toggle="modal" data-target="#exportModal"><i class="fa fa-file-excel"></i> تصدير</button>
                            </div>
                        </div>
                    </form>
                    <div class="card mt-3" id="agent-report-field" style="display:none;">
                        <div class="card-body">
                            <div class="row" id="agent-report-data">
                                <div class="col-md-12">
                                    <table class="table table-bordered" id="agent-report-list">
                                        <thead>
                                            <tr>
                                                <th>#</th>
                                                <th>التاريخ</th>
                                                <th>رقم الشحنة</th>
                                                <th>المرسل</th>
                                                <th>المستلم</th>
                                                <th>القيمة</th>
                                                <th>الحالة</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="exportModal" tabindex="-1" role="dialog" aria-labelledby="exportModalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="exportModalLabel">تحديد الأعمدة للتصدير</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <form id="export-columns-form">
                    </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">إلغاء</button>
                <button type="button" class="btn btn-primary" id="start_export_csv">تصدير CSV</button>
                <button type="button" class="btn btn-danger" id="start_export_pdf">تصدير PDF</button>
            </div>
        </div>
    </div>
</div>

<style>
    /* ... (Your existing CSS) ... */
</style>

<noscript>
    </noscript>

<script>
    function start_load() {
        console.log("start_load function is called");
        // Your existing loader code
    }

    function end_load() {
        console.log("end_load function is called");
        // Your existing loader code
    }

    // Mapping for column titles
    var columnMappings = {
        'general': {
            'date_created': 'التاريخ',
            'reference_number': 'رقم الشحنة',
            'sender_name': 'المرسل',
            'recipient_name': 'المستلم',
            'courier_name': 'المندوب',
            'governorate_name': 'المحافظة',
            'price': 'القيمة',
            'status': 'الحالة'
        },
        'client': {
            'date_created': 'التاريخ',
            'reference_number': 'رقم الشحنة',
            'recipient_name': 'المستلم',
            'courier_name': 'المندوب',
            'price': 'القيمة',
            'status': 'الحالة'
        },
        'agent': {
            'date_created': 'التاريخ',
            'reference_number': 'رقم الشحنة',
            'sender_name': 'المرسل',
            'recipient_name': 'المستلم',
            'price': 'القيمة',
            'status': 'الحالة'
        }
    };
    
    // Function to generate checkboxes based on the active tab
    $('#exportModal').on('show.bs.modal', function (e) {
        var activeTab = $('.nav-link.active').attr('href').substring(1);
        var form = $('#export-columns-form');
        form.empty();
        var columns = columnMappings[activeTab];
        $.each(columns, function(key, value) {
            form.append(
                '<div class="form-check">' +
                '<input class="form-check-input export-column" type="checkbox" value="' + key + '" id="check-' + key + '" checked>' +
                '<label class="form-check-label" for="check-' + key + '">' + value + '</label>' +
                '</div>'
            );
        });
    });

    $('#general-report-filter').submit(function(e){
        e.preventDefault();
        var data = $(this).serialize() + '&action_type=general';
        fetchReportData('general', 'get_report', data, 'general-report-list');
    });

    $('#client-report-filter').submit(function(e){
        e.preventDefault();
        var data = $(this).serialize() + '&action_type=client';
        fetchReportData('client', 'get_client_report', data, 'client-report-list');
    });

    $('#agent-report-filter').submit(function(e){
        e.preventDefault();
        var data = $(this).serialize() + '&action_type=agent';
        fetchReportData('agent', 'get_agent_report', data, 'agent-report-list');
    });

    function fetchReportData(tabId, action, data, tableId) {
        start_load();
        $.ajax({
            url: 'ajax.php?action=' + action,
            method: 'POST',
            data: data,
            error: err => {
                console.log(err);
                alert("حدث خطأ");
                end_load();
            },
            success: function(resp) {
                if(typeof resp === 'string'){
                    resp = JSON.parse(resp);
                }
                
                var tableBody = $('#' + tableId + ' tbody');
                tableBody.empty();
                
                if (Object.keys(resp).length > 0) {
                    var i = 1;
                    Object.keys(resp).map(function(k){
                        var row = resp[k];
                        var tr = $('<tr></tr>');
                        tr.append('<td>' + (i++) + '</td>');
                        // Add columns based on the table's specific needs
                        if (tabId === 'general') {
                            tr.append('<td>' + row.date_created + '</td>');
                            tr.append('<td>' + row.reference_number + '</td>');
                            tr.append('<td>' + row.sender_name + '</td>');
                            tr.append('<td>' + row.recipient_name + '</td>');
                            tr.append('<td>' + (row.courier_name || 'N/A') + '</td>');
                            tr.append('<td>' + (row.governorate_name || 'N/A') + '</td>');
                            tr.append('<td>' + row.price + '</td>');
                            tr.append('<td>' + row.status + '</td>');
                        } else if (tabId === 'client') {
                            tr.append('<td>' + row.date_created + '</td>');
                            tr.append('<td>' + row.reference_number + '</td>');
                            tr.append('<td>' + row.recipient_name + '</td>');
                            tr.append('<td>' + (row.courier_name || 'N/A') + '</td>');
                            tr.append('<td>' + row.price + '</td>');
                            tr.append('<td>' + row.status + '</td>');
                        } else if (tabId === 'agent') {
                            tr.append('<td>' + row.date_created + '</td>');
                            tr.append('<td>' + row.reference_number + '</td>');
                            tr.append('<td>' + row.sender_name + '</td>');
                            tr.append('<td>' + row.recipient_name + '</td>');
                            tr.append('<td>' + row.price + '</td>');
                            tr.append('<td>' + row.status + '</td>');
                        }
                        
                        tableBody.append(tr);
                    });
                } else {
                    tableBody.html('<tr><td colspan="9" class="text-center">لا توجد بيانات للعرض.</td></tr>');
                }
                $('#' + tabId + '-report-field').show();
                end_load();
            }
        });
    }

    // Export to CSV functionality
    $('#start_export_csv').click(function(){
        start_load();
        var activeTab = $('.nav-link.active').attr('href').substring(1);
        var form = $('#' + activeTab + '-report-filter');
        var data = form.serialize() + '&action=' + activeTab;

        var selectedColumns = [];
        $('.export-column:checked').each(function() {
            selectedColumns.push($(this).val());
        });
        
        var allData = JSON.parse($('#' + activeTab + '-report-field').find('pre').text());
        
        if (selectedColumns.length === 0) {
            alert("يرجى اختيار عمود واحد على الأقل.");
            end_load();
            return;
        }

        // Generate CSV file
        var csv = [];
        var bom = '\uFEFF';
        var headers = selectedColumns.map(function(col) {
            return columnMappings[activeTab][col];
        });
        csv.push(headers.join(','));

        var i = 1;
        Object.keys(allData).map(function(k){
            var row = [];
            row.push(i++);
            selectedColumns.map(function(col){
                var cellValue = allData[k][col] || '';
                row.push('"' + (cellValue.trim() || 'N/A') + '"');
            });
            csv.push(row.join(','));
        });

        var csv_string = bom + csv.join('\n');
        var a = document.createElement('a');
        a.href = 'data:text/csv;charset=utf-8,' + encodeURIComponent(csv_string);
        a.target = '_blank';
        a.download = activeTab + '_report_<?php echo date("Y-m-d_H-i") ?>.csv';
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        
        end_load();
    });

    // Export to PDF functionality
    $('#start_export_pdf').click(function(){
        var activeTab = $('.nav-link.active').attr('href').substring(1);
        var selectedColumns = [];
        $('.export-column:checked').each(function() {
            selectedColumns.push($(this).val());
        });
        
        if (selectedColumns.length === 0) {
            alert("يرجى اختيار عمود واحد على الأقل.");
            return;
        }
        
        var form = $('#' + activeTab + '-report-filter');
        var data = form.serialize();
        
        window.open('export_pdf.php?columns=' + selectedColumns.join(',') + '&' + data + '&type=' + activeTab, '_blank');
        $('#exportModal').modal('hide');
    });

    // Run the general report by default on page load
    $(document).ready(function(){
        $('#general-report-filter').submit();
    });
</script>