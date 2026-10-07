const fs=require('fs'),assert=require('assert'),vm=require('vm');
const fixture=JSON.parse(fs.readFileSync(__dirname+'/.runtime/fixture.json','utf8'));
const base='http://127.0.0.1:8091/';
let checks=0;
function check(condition,label){assert(condition,label); console.log('PASS '+label); checks++;}
async function session(role){
 const login=await fetch(base); const cookie=login.headers.getSetCookie().map(x=>x.split(';')[0]).join('; ');
 const html=await login.text(); const token=html.match(/name="_csrf_token" value="([^"]+)/)?.[1];
 check(!!token,'Login CSRF token present');
 const response=await fetch(base+'process_login.php',{method:'POST',redirect:'manual',headers:{cookie,'Content-Type':'application/x-www-form-urlencoded'},body:new URLSearchParams({_csrf_token:token,username:'qa_'+role,password:fixture.password,login_type:role==='admin'?'admin':'employee'})});
 check(response.status===302,'Login '+role);
 const cookies=response.headers.getSetCookie();
 return {cookie:cookies.length?cookies.map(x=>x.split(';')[0]).join('; '):cookie,token};
}
async function request(s,path,data,token=true){const r=await fetch(base+path,{redirect:'manual',method:data?'POST':'GET',headers:{cookie:s?.cookie||'',...(data?{'Content-Type':'application/x-www-form-urlencoded',...(token?{'X-CSRF-Token':s.token}:{})}:{})},body:data?new URLSearchParams(data):undefined}); const text=await r.text();let json;try{json=JSON.parse(text);}catch{}return {status:r.status,text,json};}
(async()=>{
 const admin=await session('admin'),employee=await session('employee'),other=await session('other');
 for(const page of ['dashboard.php','booking-details.php','agents-details.php','employees-detail.php','accounts-detail.php','bookingquery.php','query-history.php','listing.php']){
  const r=await request(admin,page); check(r.status===200&&!/Fatal error|Warning:|Parse error/.test(r.text),'Admin page '+page);
  check(r.text.includes('request-security.js'),'CSRF client '+page);
  fs.writeFileSync(__dirname+'/.runtime/'+page+'.html',r.text);
  let scripts=[...r.text.matchAll(/<script\b([^>]*)>([\s\S]*?)<\/script>/gi)];
  for(const [,attrs,code] of scripts) if(!attrs.includes('src=')&&code.trim()) {try{new vm.Script(code);}catch(e){throw Error(page+' inline JavaScript: '+e.message);}}
 }
 for(const page of ['employee-dashboard.php','employee-listings.php']) {const r=await request(employee,page);check(r.status===200&&!/Fatal error|Warning:/.test(r.text),'Employee page '+page);fs.writeFileSync(__dirname+'/.runtime/'+page+'.html',r.text); for(const [,attrs,code] of r.text.matchAll(/<script\b([^>]*)>([\s\S]*?)<\/script>/gi)) if(!attrs.includes('src=')&&code.trim()) new vm.Script(code);}
 check((await request(employee,'booking-details.php')).status===403,'Employee denied admin booking page');
 check((await request(null,'ajax/get_booking.php?hotel_id=1')).status===401,'Anonymous reservation access denied');
 check((await request(admin,'ajax/create_booking.php',{},false)).status===403,'Missing CSRF rejected');
 check((await request(employee,'ajax/create_booking.php',{})).status===403,'Employee cannot call admin reservation writer');
 check((await request(admin,'hotel-manager.php')).status===302,'Room Manager routes to persisted listing');
 for(const page of ['agents-details.php','employees-detail.php','accounts-detail.php','booking-details.php'])check((await request(admin,page+'?q=QA')).status===200,'Search '+page);
 const ids=fixture.ids; const year=2100+Math.floor(Math.random()*800); const from=year+'-04-01',to=year+'-04-03';
 const data={action:'create',clientName:'QA Customer',clientPhone:'9000000000',hotelId:ids.hotel,agentId:ids.agent,room_category_id:ids.room,checkIn:from,checkOut:to,amount:1,paidAmount:0,guestCount:2,roomCount:1,assigned_user_id:ids.employee,request_key:crypto.randomUUID()};
 const created=await request(admin,'ajax/crm_booking.php',data);check(created.json?.success,'CRM booking creation '+JSON.stringify(created.json));
 const id=created.json.data.booking_id;
 const linked=(await request(admin,'ajax/crm_booking.php?booking_id='+id)).json.data.booking.canonical_booking_id;
 check((await request(other,'ajax/get_booking.php?booking_id='+linked)).status===404,'Reservation guest details scoped to owner');
 check((await request(employee,'ajax/get_booking.php?booking_id='+linked)).status===200,'Assignee can view linked reservation');
 check((await request(admin,'ajax/delete_room.php',{room_id:ids.room})).status===409,'Active reservation prevents room deletion');
 check((await request(admin,'ajax/delete_hotel.php',{hotel_id:ids.hotel})).status===409,'Active reservation prevents hotel deletion');
 check(created.json.data.total_amount===2000,'Nightly pricing excludes checkout');
 const retry=await request(admin,'ajax/crm_booking.php',data);check(retry.json?.data.booking_id===id,'Duplicate request returns same booking');
 check((await request(employee,'ajax/crm_booking.php?booking_id='+id)).status===200,'Assignee reads booking');
 check((await request(other,'ajax/crm_booking.php?booking_id='+id)).status===404,'Unrelated employee cannot read booking');
 check((await request(other,'ajax/crm_booking.php',{action:'update',booking_id:id,stage:'Cancelled'})).status===422,'Unrelated employee cannot modify booking');
 for(const stage of ['Assigned','Processing','Confirmed']) check((await request(admin,'ajax/crm_booking.php',{action:'update',booking_id:id,stage})).json?.success,'Workflow '+stage);
 check((await request(admin,'ajax/crm_booking.php',{action:'update',booking_id:id,paid_amount:3000})).status===422,'Overpayment rejected');
 check((await request(employee,'ajax/crm_booking.php',{action:'update',booking_id:id,paid_amount:500})).json?.success,'Assignee records payment');
 const over=await request(admin,'ajax/create_booking.php',{hotel_id:ids.hotel,room_category_id:ids.room,guest_name:'Overbooking',checkin_date:from,checkout_date:to,rooms_count:2});check(over.status===409,'Overbooking rejected');
 const invalid=await request(admin,'ajax/create_booking.php',{hotel_id:ids.hotel,room_category_id:ids.room,guest_name:'Invalid',checkin_date:year+'-02-30',checkout_date:year+'-03-03'});check(invalid.status===422,'Impossible date rejected');
 check((await request(admin,'ajax/crm_booking.php',{action:'update',booking_id:id,stage:'Cancelled'})).json?.success,'Cancel CRM booking');
 check((await request(admin,'ajax/crm_booking.php',{action:'update',booking_id:id,stage:'Cancelled'})).json?.success,'Repeated cancellation succeeds without restoring twice');
 const final=(await request(admin,'ajax/crm_booking.php?booking_id='+id)).json.data;
 check(Number(final.booking.paid_amount)===500,'Cancellation preserves payments');
 check(Number(final.booking.due_amount)===0 && final.booking.payment_status==='Cancelled','Cancellation remains consistent after reload');
 check(final.history.length>=6,'Workflow and payments recorded in history');
 check((await request(admin,'ajax/crm_booking.php',{action:'update',booking_id:id,stage:'Confirmed'})).status===422,'Cancelled booking cannot reopen without inventory');
 const all=await request(admin,'ajax/create_booking.php',{hotel_id:ids.hotel,room_category_id:ids.room,guest_name:'Restored',checkin_date:from,checkout_date:to,rooms_count:2});check(all.json?.status==='success','Cancellation restored all rooms');
 const reserveId=all.json.data.booking_id;
 for(const status of ['checked_in','checked_out']) check((await request(admin,'ajax/update_booking.php',{booking_id:reserveId,status})).json?.status==='success','Reservation '+status);
 check((await request(admin,'ajax/cancel_booking.php',{booking_id:reserveId})).status===409,'Completed stay cannot be cancelled');
 async function jsonRequest(path,body) {
  const response=await fetch(base+path,{method:'POST',headers:{cookie:admin.cookie,'Content-Type':'application/json','X-CSRF-Token':admin.token},body:JSON.stringify(body)});
  return {status:response.status,json:await response.json()};
 }
 for(let repeat=0;repeat<2;repeat++) {
  const updated=await jsonRequest('ajax/update_room.php',{room_id:ids.room,name:'QA Room',total_rooms:2,available_rooms:2,prices:{EP:1000}});
  check(updated.json.status==='success','Room/base-price save '+(repeat+1));
 }
 const bulkMismatch=await jsonRequest('ajax/bulk_rate_update.php',{hotel_id:999999,room_id:ids.room,meal_plan:'EP',from_date:'2030-01-01',to_date:'2030-01-02',price:100,days_of_week:[0,1,2,3,4,5,6]});
 check(bulkMismatch.status===422,'Bulk rates reject a room from another hotel');
 const invalidMonth=await request(admin,'ajax/get_listing_data.php?type=rates&room_id='+ids.room+'&year=2030&month=13');
 check(invalidMonth.status===422,'Rate calendar rejects invalid month');
 const rateFrom=year+'-09-10',rateTo=year+'-09-12';
 const rates=await jsonRequest('ajax/save_room_price.php',{rates:[{room_id:ids.room,meal_plan:'EP',date:rateFrom,price:1500},{room_id:ids.room,meal_plan:'EP',date:rateTo,price:9000}]});
 check(rates.json.status==='success','Save nightly rate overrides');
 const priced=await request(admin,'ajax/crm_booking.php',{...data,checkIn:rateFrom,checkOut:rateTo,paidAmount:300,request_key:crypto.randomUUID()});
 check(priced.json?.success&&priced.json.data.total_amount===2500,'Mixed override/base pricing excludes checkout and accepts deposit');
 const pricedId=priced.json.data.booking_id;
 const pricedDetails=(await request(admin,'ajax/crm_booking.php?booking_id='+pricedId)).json.data.booking;
 const invalidCalendar=await jsonRequest('ajax/save_availability.php',{updates:[{room_id:ids.room,hotel_id:ids.hotel,date:rateFrom,available_rooms:2,booked_rooms:0}]});
 check(invalidCalendar.status===422,'Calendar cannot overwrite an existing reservation');
 const blockedCalendar=await jsonRequest('ajax/save_availability.php',{updates:[{room_id:ids.room,hotel_id:ids.hotel,date:rateFrom,available_rooms:0}]});
 check(blockedCalendar.json.status==='success','Block remaining inventory without losing reservation');
 const linkedCancel=await request(admin,'ajax/cancel_booking.php',{booking_id:pricedDetails.canonical_booking_id});
 check(linkedCancel.json?.status==='success','Room Manager cancels linked CRM reservation');
 const linkedAfter=(await request(admin,'ajax/crm_booking.php?booking_id='+pricedId)).json.data.booking;
 check(linkedAfter.booking_status==='Cancelled'&&Number(linkedAfter.paid_amount)===300&&Number(linkedAfter.due_amount)===0,'Linked cancellation synchronizes CRM amounts and workflow');
 const alerts=await request(employee,'ajax/notifications.php');
 check(alerts.json?.success&&alerts.json.items.some(item=>Number(item.booking_id)===pricedId),'Assignee receives booking activity');
 const otherAlerts=await request(other,'ajax/notifications.php');
 check(otherAlerts.json?.success&&!otherAlerts.json.items.some(item=>Number(item.booking_id)===pricedId),'Alerts do not leak another employee booking');
 const logout=await request(employee,'logout.php');check(logout.status===302,'Logout redirects');check((await request(employee,'ajax/crm_booking.php?booking_id='+id)).status===401,'Session invalid after logout');
 console.log('TOTAL '+checks+' checks passed');
})().catch(e=>{console.error(e.message);process.exitCode=1});
