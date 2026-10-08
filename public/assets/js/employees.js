(()=>{'use strict';const modal=document.getElementById('employee-modal');if(!modal)return;
const open=document.getElementById('open-employee-form'),close=document.getElementById('close-employee-form'),cancel=document.getElementById('cancel-employee-form');let previous=null;
function show(){previous=document.activeElement;modal.hidden=false;document.body.classList.add('emp-modal-open');document.getElementById('emp-first')?.focus();}
function hide(){modal.hidden=true;document.body.classList.remove('emp-modal-open');previous?.focus();}
open?.addEventListener('click',show);close?.addEventListener('click',hide);cancel?.addEventListener('click',hide);modal.addEventListener('click',e=>{if(e.target===modal)hide()});document.addEventListener('keydown',e=>{if(modal.hidden)return;if(e.key==='Escape')hide();});
const first=document.getElementById('emp-first'),last=document.getElementById('emp-last'),preview=document.getElementById('emp-email-preview');
function slug(s){return (s||'').normalize('NFD').replace(/[\u0300-\u036f]/g,'').toLowerCase().replace(/[^a-z0-9]/g,'');}
function updateEmail(){const a=slug(first.value),b=slug(last.value);preview.value=a&&b?`${a}.${b}@${window.GROWFLOW_EMPLOYEE_DOMAIN||'company.com'}`:'';}
first.addEventListener('input',updateEmail);last.addEventListener('input',updateEmail);
const dep=document.getElementById('emp-department'),manager=document.getElementById('emp-manager');function updateManagers(){const val=dep.value;manager.value='';[...manager.options].forEach((o,i)=>{if(i>0)o.hidden=o.disabled=o.dataset.department!==val});}dep.addEventListener('change',updateManagers);updateManagers();
const search=document.getElementById('employee-search'),filter=document.getElementById('employee-department-filter'),rows=[...document.querySelectorAll('#employee-table-body tr')],empty=document.getElementById('emp-empty');function apply(){const s=search.value.trim().toLowerCase(),d=filter.value;let n=0;rows.forEach(r=>{const visible=r.dataset.search.includes(s)&&(!d||r.dataset.department===d);r.hidden=!visible;if(visible)n++;});empty.hidden=n!==0;}search?.addEventListener('input',apply);filter?.addEventListener('change',apply);
})();
