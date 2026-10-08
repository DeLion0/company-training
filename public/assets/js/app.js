document.addEventListener('DOMContentLoaded',()=>{
  if(window.lucide) lucide.createIcons();
  const sidebar=document.getElementById('sidebar'),overlay=document.getElementById('overlay');
  const close=()=>{sidebar.classList.remove('open');overlay.hidden=true};
  document.getElementById('menuToggle').addEventListener('click',()=>{const opened=sidebar.classList.toggle('open');overlay.hidden=!opened});
  overlay.addEventListener('click',close);
  document.addEventListener('keydown',e=>{if(e.key==='Escape')close()});
  document.getElementById('todayLabel').textContent=new Intl.DateTimeFormat('en-US',{month:'long',day:'numeric',year:'numeric'}).format(new Date());
  document.querySelectorAll('.inactive-link').forEach(a=>a.addEventListener('click',e=>{e.preventDefault();showToast('This module is planned for a later phase.')}));
  document.getElementById('notificationButton').addEventListener('click',()=>showToast('No new notifications.'));
});
let toastTimer;
function showToast(message){const el=document.getElementById('toast');el.textContent=message;el.classList.add('show');clearTimeout(toastTimer);toastTimer=setTimeout(()=>el.classList.remove('show'),3500)}
