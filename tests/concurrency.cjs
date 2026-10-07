const {spawn}=require('node:child_process');
const fs=require('node:fs');
const assert=require('node:assert/strict');
const fixture=JSON.parse(fs.readFileSync(__dirname+'/.runtime/fixture.json','utf8'));
const php=process.env.PHP_BIN||'C:/xampp/php/php.exe';
function worker(input) {
  return new Promise((resolve,reject)=>{
    const child=spawn(php,[__dirname+'/concurrency-worker.php',JSON.stringify(input)]);
    let out='',err=''; child.stdout.on('data',data=>out+=data); child.stderr.on('data',data=>err+=data);
    child.on('error',reject); child.on('close',code=>{if(code)reject(new Error(err));else {try{resolve(JSON.parse(out));}catch(e){reject(e);}}});
  });
}
(async()=>{
  const year=3000+Math.floor(Math.random()*5000);
  const data={hotel_id:fixture.ids.hotel,room_category_id:fixture.ids.room,guest_name:'Concurrent QA',rooms_count:2,checkin_date:year+'-06-10',checkout_date:year+'-06-12',meal_plan:'EP'};
  const start=Date.now()/1000+1;
  const results=await Promise.all(Array.from({length:4},()=>worker({action:'create',data,start})));
  assert.equal(results.filter(r=>r.ok).length,1,'Only one request may reserve the last two rooms');
  assert.equal(results.filter(r=>r.conflict).length,3,'Other requests must return availability conflicts');
  const id=results.find(r=>r.ok).data.booking_id;
  let stock=await worker({action:'inspect',data,start:0});
  assert(stock.data.every(r=>Number(r.available_rooms)===0&&Number(r.booked_rooms)===2));
  const cancelStart=Date.now()/1000+1;
  const cancellations=await Promise.all(Array.from({length:4},()=>worker({action:'cancel',id,start:cancelStart})));
  assert(cancellations.every(r=>r.ok));
  stock=await worker({action:'inspect',data,start:0});
  assert(stock.data.every(r=>Number(r.available_rooms)===2&&Number(r.booked_rooms)===0));
  console.log('PASS: four simultaneous booking attempts reserve inventory once; four cancellations restore it once.');
})().catch(error=>{console.error(error.message);process.exitCode=1;});
