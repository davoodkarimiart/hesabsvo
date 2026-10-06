(()=>{
  const norm=s=>String(s??'').trim().toLocaleLowerCase('fa-IR').replace(/[يى]/g,'ی').replace(/ك/g,'ک').replace(/[۰-۹]/g,d=>'۰۱۲۳۴۵۶۷۸۹'.indexOf(d)).replace(/[٠-٩]/g,d=>'٠١٢٣٤٥٦٧٨٩'.indexOf(d));
  window.initPickerSearch=function(){document.querySelectorAll('[data-picker-search]').forEach(input=>{if(input.dataset.bound)return;input.dataset.bound='1';const box=document.getElementById(input.dataset.pickerSearch);if(!box)return;const rows=[...box.querySelectorAll('[data-pick-text]')];const apply=()=>{const q=norm(input.value);rows.forEach(r=>r.hidden=!!q&&!norm(r.dataset.pickText||r.textContent).includes(q));};input.addEventListener('input',apply);input.addEventListener('search',apply);apply();});};
  function div(a,b){return ~~(a/b)} function mod(a,b){return a-div(a,b)*b}
  function jalCal(jy,noLeap){const breaks=[-61,9,38,199,426,686,756,818,1111,1181,1210,1635,2060,2097,2192,2262,2324,2394,2456,3178];let bl=breaks.length,gy=jy+621,leapJ=-14,jp=breaks[0],jm=0,jump=0,leap,leapG,march,n,i;if(jy<jp||jy>=breaks[bl-1])throw Error('Jalali year out of range');for(i=1;i<bl;i++){jm=breaks[i];jump=jm-jp;if(jy<jm)break;leapJ+=div(jump,33)*8+div(mod(jump,33),4);jp=jm}n=jy-jp;leapJ+=div(n,33)*8+div(mod(n,33)+3,4);if(mod(jump,33)===4&&jump-n===4)leapJ++;leapG=div(gy,4)-div((div(gy,100)+1)*3,4)-150;march=20+leapJ-leapG;if(noLeap)return{gy,march};if(jump-n<6)n=n-jump+div(jump+4,33)*33;leap=mod(mod(n+1,33)-1,4);if(leap===-1)leap=4;return{leap,gy,march}}
  function g2d(gy,gm,gd){let d=div((gy+div(gm-8,6)+100100)*1461,4)+div(153*mod(gm+9,12)+2,5)+gd-34840408;d=d-div(div(gy+100100+div(gm-8,6),100)*3,4)+752;return d}
  function d2g(jdn){let j=4*jdn+139361631;j=j+div(div(4*jdn+183187720,146097)*3,4)*4-3908;let i=div(mod(j,1461),4)*5+308;let gd=div(mod(i,153),5)+1;let gm=mod(div(i,153),12)+1;let gy=div(j,1461)-100100+div(8-gm,6);return{gy,gm,gd}}
  function j2d(jy,jm,jd){const r=jalCal(jy,true);return g2d(r.gy,3,r.march)+(jm-1)*31-div(jm,7)*(jm-7)+jd-1}
  function d2j(jdn){const g=d2g(jdn);let jy=g.gy-621,r=jalCal(jy,false),jdn1f=g2d(g.gy,3,r.march),jd,jm,k=jdn-jdn1f;if(k>=0){if(k<=185){jm=1+div(k,31);jd=mod(k,31)+1;return{jy,jm,jd}}k-=186}else{jy--;k+=179;if(r.leap===1)k++}jm=7+div(k,30);jd=mod(k,30)+1;return{jy,jm,jd}}
  function toAscii(s){return String(s??'').replace(/[۰-۹]/g,d=>'۰۱۲۳۴۵۶۷۸۹'.indexOf(d)).replace(/[٠-٩]/g,d=>'٠١٢٣٤٥٦٧٨٩'.indexOf(d))}
  function parseJ(s){const m=toAscii(s).match(/(\d{3,4})\D+(\d{1,2})\D+(\d{1,2})/);return m?{jy:+m[1],jm:+m[2],jd:+m[3]}:null}
  function fmtJ(y,m,d){return `${y}/${String(m).padStart(2,'0')}/${String(d).padStart(2,'0')}`}
  const monthNames=['فروردین','اردیبهشت','خرداد','تیر','مرداد','شهریور','مهر','آبان','آذر','دی','بهمن','اسفند'];
  let calTarget=null,viewY=0,viewM=0;
  function todayJ(){const d=new Date();return d2j(g2d(d.getFullYear(),d.getMonth()+1,d.getDate()))}
  function daysInMonth(y,m){if(m<=6)return 31;if(m<=11)return 30;return jalCal(y,false).leap===0?30:29}
  function ensureCalendar(){if(document.getElementById('jalaliCalendarDialog'))return;const d=document.createElement('dialog');d.id='jalaliCalendarDialog';d.className='app-dialog';d.innerHTML=`<div class="dialog-card jalali-calendar-card"><div class="dialog-head"><div><b>انتخاب تاریخ شمسی</b><small>تاریخ را می‌توانی همچنان دستی هم وارد کنی.</small></div><button type="button" class="ghost icon-btn" data-cal-close>×</button></div><div class="jalali-calendar-head"><button type="button" class="ghost" data-cal-next>‹</button><b data-cal-title></b><button type="button" class="ghost" data-cal-prev>›</button></div><div class="jalali-calendar-grid" data-cal-grid></div><button type="button" class="ghost" style="width:100%;margin-top:8px" data-cal-today>امروز</button></div>`;document.body.appendChild(d);d.querySelector('[data-cal-close]').onclick=()=>d.close();d.querySelector('[data-cal-next]').onclick=()=>{viewM++;if(viewM>12){viewM=1;viewY++}renderCal()};d.querySelector('[data-cal-prev]').onclick=()=>{viewM--;if(viewM<1){viewM=12;viewY--}renderCal()};d.querySelector('[data-cal-today]').onclick=()=>{const t=todayJ();selectCal(t.jy,t.jm,t.jd)};}
  function renderCal(){ensureCalendar();const d=document.getElementById('jalaliCalendarDialog'),grid=d.querySelector('[data-cal-grid]'),title=d.querySelector('[data-cal-title]');title.textContent=`${monthNames[viewM-1]} ${viewY}`;const names=['ش','ی','د','س','چ','پ','ج'];let html=names.map(x=>`<span>${x}</span>`).join('');const g=d2g(j2d(viewY,viewM,1));const jsDay=new Date(g.gy,g.gm-1,g.gd).getDay();const offset=(jsDay+1)%7;for(let i=0;i<offset;i++)html+='<span></span>';const sel=parseJ(calTarget?.value||''),today=todayJ();for(let day=1;day<=daysInMonth(viewY,viewM);day++){const cls=(today.jy===viewY&&today.jm===viewM&&today.jd===day?' today':'')+(sel&&sel.jy===viewY&&sel.jm===viewM&&sel.jd===day?' selected':'');html+=`<button type="button" class="${cls.trim()}" data-cal-day="${day}">${day}</button>`}grid.innerHTML=html;grid.querySelectorAll('[data-cal-day]').forEach(b=>b.onclick=()=>selectCal(viewY,viewM,+b.dataset.calDay));}
  function selectCal(y,m,d){if(calTarget){calTarget.value=fmtJ(y,m,d);calTarget.dispatchEvent(new Event('input',{bubbles:true}));calTarget.dispatchEvent(new Event('change',{bubbles:true}))}document.getElementById('jalaliCalendarDialog')?.close();}
  function initCalendars(){document.querySelectorAll('input.jalali-date').forEach(inp=>{if(inp.dataset.calBound)return;inp.dataset.calBound='1';const wrap=document.createElement('div');wrap.className='date-with-picker';inp.parentNode.insertBefore(wrap,inp);wrap.appendChild(inp);const b=document.createElement('button');b.type='button';b.className='ghost calendar-btn';b.textContent='📅';b.setAttribute('aria-label','انتخاب تاریخ شمسی');b.onclick=()=>{calTarget=inp;const p=parseJ(inp.value)||todayJ();viewY=p.jy;viewM=p.jm;renderCal();document.getElementById('jalaliCalendarDialog').showModal()};wrap.appendChild(b);});}
  function initCustomerCombos(){document.querySelectorAll('[data-customer-combo]').forEach(inp=>{if(inp.dataset.comboBound)return;inp.dataset.comboBound='1';const hidden=document.getElementById(inp.dataset.customerCombo);const list=document.getElementById(inp.getAttribute('list'));if(!hidden||!list)return;const opts=[...list.options],base=o=>String(o.value||'').split('• #')[0].trim();const sync=()=>{const needle=norm(inp.value);let hit=opts.find(o=>norm(o.value)===needle)||opts.find(o=>norm(base(o))===needle);if(!hit&&needle){const matches=opts.filter(o=>norm(base(o)).includes(needle)||norm(o.value).includes(needle));if(matches.length===1)hit=matches[0];}hidden.value=hit?hit.dataset.id:'0';inp.dataset.customerResolved=hit?'1':'0';};inp.addEventListener('input',sync);inp.addEventListener('change',sync);sync();});}
  document.addEventListener('DOMContentLoaded',()=>{window.initPickerSearch();initCalendars();initCustomerCombos();});
})();

/* v0.4.12: mobile-first app dialogs for confirms/alerts */
(()=>{
  function ensureSystemDialog(){
    let d=document.getElementById('systemAppDialog');
    if(d) return d;
    d=document.createElement('dialog');
    d.id='systemAppDialog';
    d.className='app-dialog system-dialog';
    d.innerHTML=`<div class="dialog-card"><div class="dialog-head"><div><b id="systemDialogTitle">تأیید عملیات</b><small>برای ادامه یکی از گزینه‌ها را انتخاب کن.</small></div><button type="button" class="ghost icon-btn" id="systemDialogClose" aria-label="بستن">×</button></div><div id="systemDialogMessage" class="system-dialog-message"></div><div id="systemDialogActions" class="system-dialog-actions"><button type="button" class="ghost" id="systemDialogCancel">انصراف</button><button type="button" class="primary" id="systemDialogConfirm">تأیید</button></div></div>`;
    document.body.appendChild(d);
    return d;
  }
  window.appConfirm=function(message,opts={}){
    const d=ensureSystemDialog(),msg=d.querySelector('#systemDialogMessage'),title=d.querySelector('#systemDialogTitle'),actions=d.querySelector('#systemDialogActions'),yes=d.querySelector('#systemDialogConfirm'),no=d.querySelector('#systemDialogCancel'),close=d.querySelector('#systemDialogClose');
    title.textContent=opts.title||'تأیید عملیات'; msg.textContent=String(message||''); actions.classList.remove('single'); no.hidden=false; yes.textContent=opts.confirmText||'تأیید'; no.textContent=opts.cancelText||'انصراف'; yes.classList.toggle('danger-confirm',!!opts.danger);
    return new Promise(resolve=>{let done=false;const finish=v=>{if(done)return;done=true;try{d.close()}catch(e){}resolve(v)};yes.onclick=()=>finish(true);no.onclick=()=>finish(false);close.onclick=()=>finish(false);d.oncancel=e=>{e.preventDefault();finish(false)};d.showModal();});
  };
  window.appAlert=function(message,opts={}){
    const d=ensureSystemDialog(),msg=d.querySelector('#systemDialogMessage'),title=d.querySelector('#systemDialogTitle'),actions=d.querySelector('#systemDialogActions'),yes=d.querySelector('#systemDialogConfirm'),no=d.querySelector('#systemDialogCancel'),close=d.querySelector('#systemDialogClose');
    title.textContent=opts.title||'پیام';msg.textContent=String(message||'');actions.classList.add('single');no.hidden=true;yes.textContent='باشه';yes.classList.remove('danger-confirm');
    return new Promise(resolve=>{let done=false;const finish=()=>{if(done)return;done=true;try{d.close()}catch(e){}resolve(true)};yes.onclick=finish;close.onclick=finish;d.oncancel=e=>{e.preventDefault();finish()};d.showModal();});
  };
  document.addEventListener('submit',async e=>{
    const form=e.target.closest?.('form[data-confirm]');
    if(!form || form.dataset.confirmed==='1') return;
    e.preventDefault();
    const ok=await appConfirm(form.dataset.confirm,{danger:form.querySelector('.danger')!==null,confirmText:form.querySelector('.danger')?'حذف':'تأیید'});
    if(ok){form.dataset.confirmed='1';form.requestSubmit();}
  },true);
})();


/* v0.5.0 mobile keyboard flow */
(()=>{
 const visible=el=>!!(el.offsetWidth||el.offsetHeight||el.getClientRects().length)&&!el.disabled&&el.type!=='hidden';
 document.querySelectorAll('form.keyboard-flow').forEach(form=>{
   const fields=[...form.querySelectorAll('input:not([type=file]):not([type=hidden]),select,textarea,button[type=submit]')].filter(visible);
   fields.forEach((el,i)=>{if(el.tagName==='INPUT'&&el.type!=='password')el.setAttribute('enterkeyhint',i<fields.length-1?'next':'go');});
   form.addEventListener('keydown',e=>{if(e.key!=='Enter'||e.shiftKey||e.ctrlKey||e.metaKey)return;const el=e.target;if(el.tagName==='TEXTAREA'||el.type==='submit'||el.tagName==='BUTTON')return;const now=fields.indexOf(el);if(now>=0&&now<fields.length-1){e.preventDefault();fields[now+1].focus();} });
 });
})();


/* v0.5.5 customer live-search is bound inline with default alphabetical sorting. */
