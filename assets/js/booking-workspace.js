(function () {
  'use strict';
  var endpoint = new URL('../../ajax/crm_booking.php', document.currentScript.src).href;
  var byId = function (id) { return document.getElementById(id); };
  ['crmCreateModal','crmManageModal'].forEach(function(id) { document.body.appendChild(byId(id)); });
  function message(id, text, success) { var el=byId(id); el.className='alert '+(success?'alert-success':'alert-danger'); el.textContent=text; }
  async function request(url, options) {
    var response=await fetch(url,options); var data=await response.json();
    if (!response.ok || !data.success) throw new Error(data.message || 'Request failed.');
    return data;
  }
  byId('crmRequestKey').value=crypto.randomUUID ? crypto.randomUUID() : Date.now()+'-'+Math.random().toString(36).slice(2);
  byId('crmRoom').addEventListener('change',function () { byId('crmHotelId').value=this.selectedOptions[0].dataset.hotel || ''; });
  ['crmCreateForm','crmManageForm'].forEach(function (id) {
    byId(id).addEventListener('submit',async function (event) {
      event.preventDefault();
      var button=this.querySelector('[type="submit"]'); var msg=id==='crmCreateForm'?'crmCreateMessage':'crmManageMessage';
      button.disabled=true;
      try { var result=await request(endpoint,{method:'POST',body:new FormData(this)}); message(msg,result.message,true); if (document.getElementById('my-bookings-view')) history.replaceState(null,'','#my-bookings-view'); window.location.reload(); }
      catch(error) { message(msg,error.message,false); button.disabled=false; }
    });
  });
  window.manageCrmBooking=async function (id) {
    var modal=bootstrap.Modal.getOrCreateInstance(byId('crmManageModal'));
    byId('crmManageMessage').className='alert d-none'; byId('crmHistory').replaceChildren();
    byId('crmManageSummary').textContent='Loading booking…';
    var save=byId('crmManageForm').querySelector('[type="submit"]'); save.disabled=true; modal.show();
    try {
      var result=await request(endpoint+'?booking_id='+encodeURIComponent(id)); var b=result.data.booking;
      byId('crmManageId').value=b.id; byId('crmStage').value=b.stage||b.booking_status;
      if(byId('crmManageAssign')) byId('crmManageAssign').value=b.assigned_user_id||'0';
      byId('crmManagePaid').value=b.paid_amount; byId('crmManagePaid').max=b.amount;
      byId('crmManageNote').value=b.payment_note||'';
      byId('crmManageSummary').textContent=b.booking_code+' · '+b.client_name+' · '+b.check_in+' → '+b.check_out+' · Total ₹'+b.amount+' · Due ₹'+b.due_amount;
      var history=result.data.history;
      if(!history.length) { var empty=document.createElement('li'); empty.className='list-group-item'; empty.textContent='No recorded changes for this older booking.'; byId('crmHistory').appendChild(empty); }
      history.forEach(function (entry) { var row=document.createElement('li'); row.className='list-group-item small'; row.textContent=entry.action_at+' · '+entry.performed_by_username+' · '+entry.action+' · '+entry.details; byId('crmHistory').appendChild(row); });
      save.disabled=false;
    } catch(error) { message('crmManageMessage',error.message,false); }
  };
}());
