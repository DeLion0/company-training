document.addEventListener('DOMContentLoaded',()=>{
 const $=id=>document.getElementById(id), form=$('trainingForm'), dialog=$('reviewDialog');
 const fields=['title','purpose','objectives','startDate','endDate','weekday','meetingCount','startTime','endTime','instructors','venue','capacity'];
 const weekdays=['Sunday','Monday','Tuesday','Wednesday','Thursday','Friday','Saturday'];
 const localDate=(s)=>{const [y,m,d]=s.split('-').map(Number);return new Date(y,m-1,d,12)};
 const iso=(d)=>[d.getFullYear(),String(d.getMonth()+1).padStart(2,'0'),String(d.getDate()).padStart(2,'0')].join('-');
 const pretty=(s)=>localDate(s).toLocaleDateString('en-US',{month:'short',day:'numeric',year:'numeric'});
 const clock=(t)=>{if(!/^\d{2}:\d{2}$/.test(t))return t;let [h,m]=t.split(':').map(Number);return `${h%12||12}:${String(m).padStart(2,'0')} ${h>=12?'PM':'AM'}`};
 const escapeHTML=(s)=>String(s).replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
 let sessions=[];
 function calculate(){
   const s=$('startDate').value,e=$('endDate').value,day=Number($('weekday').value),n=Number($('meetingCount').value),t1=$('startTime').value,t2=$('endTime').value;
   if(!s||!e)return {error:'Select the start and end dates to generate sessions.'};
   if(localDate(e)<localDate(s))return {error:'End date must be on or after the start date.'};
   const totalDays=Math.round((localDate(e)-localDate(s))/86400000);
   if(totalDays>366)return {error:'Select a date range no longer than 366 days.'};
   if(!Number.isInteger(n)||n<1||n>100)return {error:'Number of meetings must be between 1 and 100.'};
   if(!t1||!t2||t1>=t2)return {error:'End time must be later than start time.'};
   const dates=[];let d=localDate(s);for(let i=0;i<=totalDays;i++){if(d.getDay()===day)dates.push(iso(d));d.setDate(d.getDate()+1)}
   if(n>dates.length)return {error:`Only ${dates.length} ${weekdays[day]} meeting date(s) are available in the selected range.`};
   return {dates:dates.slice(0,n),available:dates.length};
 }
 function render(){const result=calculate(),list=$('sessionsList');sessions=result.dates||[];
   if(result.error){list.innerHTML=`<div class="session-error">${escapeHTML(result.error)}</div>`;$('dateHint').textContent='Adjust the settings above to see your sessions.'}
   else{list.innerHTML=sessions.map((date,i)=>`<div class="session-row"><strong><span class="session-number">${i+1}</span> ${escapeHTML(pretty(date))}</strong><span>${escapeHTML(clock($('startTime').value))} – ${escapeHTML(clock($('endTime').value))}</span></div>`).join('');$('dateHint').textContent=`${result.available} matching weekdays in this date range. Showing the first ${sessions.length}.`}
   $('summaryFrequency').textContent=`Every ${weekdays[Number($('weekday').value)]}`;$('summaryMeetings').textContent=`${sessions.length} session${sessions.length===1?'':'s'}`;
   const start=$('startTime').value.split(':').map(Number),end=$('endTime').value.split(':').map(Number),mins=(end[0]*60+end[1])-(start[0]*60+start[1]);$('summaryDuration').textContent=mins>0?`${Math.floor(mins/60)}h${mins%60?` ${mins%60}m`:''} per session`:'Invalid time range';return result}
 fields.forEach(id=>$(id).addEventListener('input',()=>{$('formMessage').textContent='';render()}));
 $('newProgram').addEventListener('click',()=>{$('formPanel').scrollIntoView({behavior:'smooth',block:'start'});$('title').focus({preventScroll:true})});
 $('resetForm').addEventListener('click',()=>{form.reset();$('startDate').value='2026-11-01';$('endDate').value='2026-11-30';$('formMessage').textContent='';render()});
 function value(id){return $(id).value.trim()}
 form.addEventListener('submit',e=>{e.preventDefault();const result=render();if(result.error){$('formMessage').textContent=result.error;return}if(!form.reportValidity())return;
 const values=[['PROGRAM TITLE',value('title')],['PURPOSE',value('purpose')],['OBJECTIVES',value('objectives')],['SCHEDULE',`${pretty(sessions[0])} to ${pretty(sessions[sessions.length-1])} · Every ${weekdays[Number(value('weekday'))]} · ${clock(value('startTime'))}–${clock(value('endTime'))}`],['INSTRUCTORS',value('instructors')],['LOCATION',value('venue')],['CAPACITY',`${value('capacity')} participants`],['MEETINGS',sessions.map((date,i)=>`Meeting ${i+1}: ${pretty(date)}`).join('\n')]];
 $('reviewContents').innerHTML=values.map(([label,v])=>`<div class="review-section"><span>${label}</span><p>${escapeHTML(v)}</p></div>`).join('');dialog.showModal();});
 $('closeReview').addEventListener('click',()=>dialog.close());$('backToEdit').addEventListener('click',()=>dialog.close());
 $('publishButton').addEventListener('click',()=>{
  const result=calculate();if(result.error){dialog.close();$('formMessage').textContent=result.error;return}
  // Local demo only: storage stays in the current browser, not in PHP/MySQL.
  let data=[];try{data=JSON.parse(localStorage.getItem('skillspring_demo_programs')||'[]');if(!Array.isArray(data))data=[]}catch{}
  data.push({title:value('title'),purpose:value('purpose'),objectives:value('objectives'),startDate:value('startDate'),endDate:value('endDate'),weekday:value('weekday'),startTime:value('startTime'),endTime:value('endTime'),instructors:value('instructors'),venue:value('venue'),capacity:Number(value('capacity')),sessions:result.dates,createdAt:new Date().toISOString()});
  try{localStorage.setItem('skillspring_demo_programs',JSON.stringify(data))}catch{showToast('Local browser storage is unavailable. Demo was not saved.');dialog.close();return}
  dialog.close();updateMetrics();showToast('Program published in this browser demo!');form.reset();$('startDate').value='2026-11-01';$('endDate').value='2026-11-30';render();
 });
 function updateMetrics(){let data=[];try{data=JSON.parse(localStorage.getItem('skillspring_demo_programs')||'[]')}catch{}if(!Array.isArray(data))data=[];$('activeMetric').textContent=data.length;$('capacityMetric').textContent=data.reduce((a,p)=>a+(Number(p.capacity)||0),0);$('sessionMetric').textContent=data.reduce((a,p)=>a+(Array.isArray(p.sessions)?p.sessions.length:0),0)}
 $('startDate').value='2026-11-01';$('endDate').value='2026-11-30';render();updateMetrics();
});
