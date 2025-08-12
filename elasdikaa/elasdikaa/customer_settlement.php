<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
include 'db_connect.php';
include 'header.php';
include 'topbar.php';
include 'sidebar.php';
?>
<div class="content-wrapper">
  <section class="content-header">
    <div class="container-fluid">
      <div class="row mb-2">
        <div class="col-sm-6">
          <h1>تسوية حساب عميل</h1>
        </div>
      </div>
    </div>
  </section>
  <section class="content">
    <div class="container-fluid">
      <div class="card card-outline card-primary">
        <div class="card-header">
          <div class="row g-2 align-items-end">
            <div class="col-md-5">
              <label class="form-label">اختر العميل</label>
              <select id="customerSelect" class="form-control select2">
                <option value="">-- اختر --</option>
                <?php
                $res = $conn->query("SELECT id, name, phone FROM customers WHERE status = 1 ORDER BY name");
                while ($c = $res->fetch_assoc()) {
                    echo '<option value="'.(int)$c['id'].'">'.htmlspecialchars($c['name']).' - '.htmlspecialchars($c['phone']).'</option>';
                }
                ?>
              </select>
            </div>
            <div class="col-md-3">
              <label class="form-label">ملاحظات</label>
              <input type="text" id="notes" class="form-control" placeholder="ملاحظة اختيارية" />
            </div>
            <div class="col-md-4 text-end">
              <button id="exportExcel" class="btn btn-success"><i class="fas fa-file-excel"></i> تصدير إكسل</button>
              <button id="printReport" class="btn btn-secondary"><i class="fas fa-print"></i> طباعة تقرير</button>
            </div>
          </div>
        </div>
        <div class="card-body">
          <div id="summaryBox" class="alert alert-info d-none"></div>

          <ul class="nav nav-tabs" id="settlementTabs" role="tablist">
            <li class="nav-item" role="presentation">
              <button class="nav-link active" id="delivered-tab" data-bs-toggle="tab" data-bs-target="#delivered" type="button" role="tab">شحنات مسلمة وجاهزة للدفع</button>
            </li>
            <li class="nav-item" role="presentation">
              <button class="nav-link" id="partials-tab" data-bs-toggle="tab" data-bs-target="#partials" type="button" role="tab">شحنات مدفوعة جزئياً</button>
            </li>
          </ul>
          <div class="tab-content p-2">
            <div class="tab-pane fade show active" id="delivered" role="tabpanel">
              <div class="table-responsive">
                <table class="table table-bordered table-hover" id="deliveredTable">
                  <thead>
                    <tr>
                      <th><input type="checkbox" id="selectAllDelivered" /></th>
                      <th>رقم التتبع</th>
                      <th>المرجع</th>
                      <th>المستلم</th>
                      <th>قيمة الشحنة</th>
                      <th>رسوم الشحن</th>
                      <th>الصافي للعميل</th>
                      <th>مدفوع سابقاً</th>
                      <th>المتبقي</th>
                    </tr>
                  </thead>
                  <tbody></tbody>
                </table>
              </div>
            </div>
            <div class="tab-pane fade" id="partials" role="tabpanel">
              <div class="alert alert-warning">افتراضياً لا نقوم بالدفع هنا لأنها مدفوعة جزئياً بالفعل. يمكنك إدخال مبلغ إضافي اختياري لكل شحنة.</div>
              <div class="table-responsive">
                <table class="table table-bordered table-hover" id="partialsTable">
                  <thead>
                    <tr>
                      <th>إدخال دفع جزئي</th>
                      <th>رقم التتبع</th>
                      <th>المرجع</th>
                      <th>المستلم</th>
                      <th>الصافي للعميل</th>
                      <th>مدفوع سابقاً</th>
                      <th>المتبقي</th>
                    </tr>
                  </thead>
                  <tbody></tbody>
                </table>
              </div>
            </div>
          </div>

          <div class="row mt-3">
            <div class="col-md-6">
              <div class="p-3 bg-light border rounded">
                <div class="d-flex justify-content-between"><span>عدد الشحنات المحددة</span><strong id="selectedCount">0</strong></div>
                <div class="d-flex justify-content-between"><span>إجمالي التسوية</span><strong id="totalAmount">0.00</strong></div>
                <div class="small text-muted">الدفع يتم على أساس الصافي لكل شحنة: (COD - رسوم الشحن إن كان الراسل هو الدافع).</div>
              </div>
            </div>
            <div class="col-md-6 text-end">
              <button id="settleBtn" class="btn btn-lg btn-primary" disabled>
                <i class="fas fa-cash-register"></i> تنفيذ التسوية
              </button>
            </div>
          </div>
        </div>
      </div>
    </div>
  </section>
</div>
<?php include 'footer.php'; ?>
<script>
(function(){
  const customerSelect = document.getElementById('customerSelect');
  const deliveredTableBody = document.querySelector('#deliveredTable tbody');
  const partialsTableBody = document.querySelector('#partialsTable tbody');
  const selectAllDelivered = document.getElementById('selectAllDelivered');
  const selectedCount = document.getElementById('selectedCount');
  const totalAmount = document.getElementById('totalAmount');
  const settleBtn = document.getElementById('settleBtn');
  const summaryBox = document.getElementById('summaryBox');
  const notesInput = document.getElementById('notes');
  const exportBtn = document.getElementById('exportExcel');
  const printBtn = document.getElementById('printReport');

  let deliveredRows = [];
  let partialRows = [];
  let selectedDelivered = new Set();
  let partialAmounts = {}; // parcel_id => amount

  function fmt(n){ return (Number(n)||0).toFixed(2); }

  function renderTables(data){
    deliveredRows = data.delivered || [];
    partialRows = data.partials || [];

    deliveredTableBody.innerHTML = '';
    deliveredRows.forEach(r => {
      const tr = document.createElement('tr');
      tr.innerHTML = `
        <td><input type="checkbox" class="sel-delivered" data-id="${r.id}" data-rem="${r.remaining_due}"></td>
        <td>${r.tracking_number || ''}</td>
        <td>${r.reference_number || ''}</td>
        <td>${r.recipient_name || ''}</td>
        <td>${fmt(r.cod_amount)}</td>
        <td>${fmt(r.shipping_fees)}</td>
        <td>${fmt(r.net_due)}</td>
        <td>${fmt(r.paid_to_customer)}</td>
        <td>${fmt(r.remaining_due)}</td>
      `;
      deliveredTableBody.appendChild(tr);
    });

    partialsTableBody.innerHTML = '';
    partialRows.forEach(r => {
      const tr = document.createElement('tr');
      tr.innerHTML = `
        <td>
          <div class="input-group input-group-sm">
            <input type="number" min="0" step="0.01" class="form-control partial-input" data-id="${r.id}" max="${r.remaining_due}" placeholder="0.00">
            <span class="input-group-text">/${fmt(r.remaining_due)}</span>
          </div>
        </td>
        <td>${r.tracking_number || ''}</td>
        <td>${r.reference_number || ''}</td>
        <td>${r.recipient_name || ''}</td>
        <td>${fmt(r.net_due)}</td>
        <td>${fmt(r.paid_to_customer)}</td>
        <td>${fmt(r.remaining_due)}</td>
      `;
      partialsTableBody.appendChild(tr);
    });

    bindEvents();
    recalc();

    // Summary
    const stats = data.stats || {current_balance:0, delivered_count:0, partial_count:0};
    summaryBox.classList.remove('d-none');
    summaryBox.innerHTML = `
      <div class="d-flex flex-wrap gap-3">
        <div><strong>الرصيد الحالي:</strong> ${fmt(stats.current_balance)} ج.م</div>
        <div><strong>مسلمة وجاهزة:</strong> ${stats.delivered_count}</div>
        <div><strong>مدفوعة جزئياً:</strong> ${stats.partial_count}</div>
      </div>`;
  }

  function bindEvents(){
    document.querySelectorAll('.sel-delivered').forEach(cb => {
      cb.addEventListener('change', () => { if(cb.checked){ selectedDelivered.add(cb.dataset.id); } else { selectedDelivered.delete(cb.dataset.id); } recalc(); });
    });
    document.querySelectorAll('.partial-input').forEach(inp => {
      inp.addEventListener('input', () => {
        const id = inp.dataset.id;
        let val = parseFloat(inp.value||'0');
        const max = parseFloat(inp.getAttribute('max')||'0');
        if (val > max) { val = max; inp.value = fmt(max); }
        partialAmounts[id] = val > 0 ? val : undefined;
        if (!partialAmounts[id]) delete partialAmounts[id];
        recalc();
      });
    });
    selectAllDelivered.addEventListener('change', () => {
      selectedDelivered.clear();
      if (selectAllDelivered.checked) {
        document.querySelectorAll('.sel-delivered').forEach(cb => { cb.checked = true; selectedDelivered.add(cb.dataset.id); });
      } else {
        document.querySelectorAll('.sel-delivered').forEach(cb => { cb.checked = false; });
      }
      recalc();
    });
  }

  function recalc(){
    let count = selectedDelivered.size;
    let total = 0;
    document.querySelectorAll('.sel-delivered:checked').forEach(cb => { total += parseFloat(cb.dataset.rem||'0'); });
    Object.values(partialAmounts).forEach(v => { total += parseFloat(v||0); });

    selectedCount.textContent = count;
    totalAmount.textContent = fmt(total);
    settleBtn.disabled = total <= 0 || !customerSelect.value;
  }

  function loadData(){
    deliveredTableBody.innerHTML = '<tr><td colspan="9" class="text-center">جاري التحميل...</td></tr>';
    partialsTableBody.innerHTML = '';
    fetch('ajax.php?action=get_customer_settlement', {
      method: 'POST', headers: {'Content-Type':'application/x-www-form-urlencoded'},
      body: new URLSearchParams({customer_id: customerSelect.value})
    }).then(r => r.json()).then(json => {
      if (json.status === 'success') {
        selectedDelivered.clear();
        partialAmounts = {};
        renderTables(json.data);
      } else {
        deliveredTableBody.innerHTML = '<tr><td colspan="9" class="text-center text-danger">'+(json.message||'فشل التحميل')+'</td></tr>';
      }
    }).catch(() => {
      deliveredTableBody.innerHTML = '<tr><td colspan="9" class="text-center text-danger">تعذر الاتصال</td></tr>';
    });
  }

  customerSelect.addEventListener('change', () => { if(customerSelect.value){ loadData(); } });

  settleBtn.addEventListener('click', () => {
    const parcel_ids = Array.from(selectedDelivered).map(Number);
    const partial_amounts = partialAmounts;
    const fd = new URLSearchParams();
    fd.set('customer_id', customerSelect.value);
    fd.set('notes', notesInput.value||'');
    parcel_ids.forEach(id => fd.append('parcel_ids[]', id));
    Object.entries(partial_amounts).forEach(([pid, amt]) => { fd.append('partial_amounts['+pid+']', amt); });

    settleBtn.disabled = true;
    settleBtn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> جاري التنفيذ...';

    fetch('ajax.php?action=settle_customer_payout', { method: 'POST', headers: {'Content-Type':'application/x-www-form-urlencoded'}, body: fd })
      .then(r => r.json())
      .then(json => {
        if (json.status === 'success') {
          alert('تمت التسوية. الإجمالي: ' + fmt(json.total));
          loadData();
        } else {
          alert(json.message||'فشل التسوية');
        }
      })
      .catch(() => alert('تعذر الاتصال'))
      .finally(() => { settleBtn.disabled = false; settleBtn.innerHTML = '<i class="fas fa-cash-register"></i> تنفيذ التسوية'; });
  });

  exportBtn.addEventListener('click', () => {
    // تصدير بسيط إلى CSV يمكن فتحه في Excel
    const rows = [['نوع','رقم التتبع','المرجع','المستلم','الصافي','مدفوع','متبقي']];
    deliveredRows.forEach(r => rows.push(['مسلم', r.tracking_number||'', r.reference_number||'', r.recipient_name||'', fmt(r.net_due), fmt(r.paid_to_customer), fmt(r.remaining_due)]));
    partialRows.forEach(r => rows.push(['جزئي', r.tracking_number||'', r.reference_number||'', r.recipient_name||'', fmt(r.net_due), fmt(r.paid_to_customer), fmt(r.remaining_due)]));
    const csv = rows.map(a => a.map(v => '"'+String(v).replaceAll('"','""')+'"').join(',')).join('\n');
    const blob = new Blob(["\ufeff"+csv], {type: 'text/csv;charset=utf-8;'});
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a'); a.href = url; a.download = 'customer_settlement.csv'; a.click(); URL.revokeObjectURL(url);
  });

  printBtn.addEventListener('click', () => {
    const w = window.open('', '_blank');
    w.document.write('<html><head><title>تقرير تسوية عميل</title>');
    w.document.write('<style>table{width:100%;border-collapse:collapse}td,th{border:1px solid #ccc;padding:6px;text-align:right}</style>');
    w.document.write('</head><body>');
    w.document.write('<h3>تقرير تسوية عميل</h3>');
    w.document.write('<p>العميل: ' + (customerSelect.options[customerSelect.selectedIndex]?.text||'') + '</p>');
    w.document.write(document.getElementById('summaryBox').outerHTML);
    const tbl = document.createElement('table');
    const head = '<tr><th>نوع</th><th>رقم التتبع</th><th>المرجع</th><th>المستلم</th><th>الصافي</th><th>مدفوع</th><th>متبقي</th></tr>';
    let body = '';
    deliveredRows.forEach(r => { body += `<tr><td>مسلم</td><td>${r.tracking_number||''}</td><td>${r.reference_number||''}</td><td>${r.recipient_name||''}</td><td>${fmt(r.net_due)}</td><td>${fmt(r.paid_to_customer)}</td><td>${fmt(r.remaining_due)}</td></tr>`; });
    partialRows.forEach(r => { body += `<tr><td>جزئي</td><td>${r.tracking_number||''}</td><td>${r.reference_number||''}</td><td>${r.recipient_name||''}</td><td>${fmt(r.net_due)}</td><td>${fmt(r.paid_to_customer)}</td><td>${fmt(r.remaining_due)}</td></tr>`; });
    w.document.write('<table>'+head+body+'</table>');
    w.document.write('</body></html>');
    w.document.close();
    w.focus();
    w.print();
    w.close();
  });
})();
</script>