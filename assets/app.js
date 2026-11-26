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
  window.p20InitControls=()=>{window.initPickerSearch();initCalendars();initCustomerCombos();};document.addEventListener('DOMContentLoaded',window.p20InitControls);
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
    const ok=await appConfirm(form.dataset.confirm,{danger:form.querySelector('.danger')!==null,confirmText:form.querySelector('input[name="action"]')?.value==='clear_report_draft'?'حذف پیش‌نمایش':(form.querySelector('.danger')?'حذف':'تأیید')});
    if(ok){form.dataset.confirmed='1';form.requestSubmit();}
  },true);
})();


/* v0.5.0 mobile keyboard flow */
(()=>{
 const visible=el=>!!(el.offsetWidth||el.offsetHeight||el.getClientRects().length)&&!el.disabled&&el.type!=='hidden';
 window.p20InitKeyboard=()=>document.querySelectorAll('form.keyboard-flow').forEach(form=>{if(form.dataset.p20Keyboard)return;form.dataset.p20Keyboard='1';
   const fields=[...form.querySelectorAll('input:not([type=file]):not([type=hidden]),select,textarea,button[type=submit]')].filter(visible);
   fields.forEach((el,i)=>{if(el.tagName==='INPUT'&&el.type!=='password')el.setAttribute('enterkeyhint',i<fields.length-1?'next':'go');});
   form.addEventListener('keydown',e=>{if(e.key!=='Enter'||e.shiftKey||e.ctrlKey||e.metaKey)return;const el=e.target;if(el.tagName==='TEXTAREA'||el.type==='submit'||el.tagName==='BUTTON')return;const now=fields.indexOf(el);if(now>=0&&now<fields.length-1){e.preventDefault();fields[now+1].focus();} });
 });window.p20InitKeyboard();
})();


/* v0.5.5 customer live-search is bound inline with default alphabetical sorting. */

/* v0.6.54 Non-blocking clipboard success notification. */
(()=>{
  window.svoCopyToast=function(message='تصویر کپی شد'){
    let el=document.getElementById('svoCopyToast');
    if(!el){el=document.createElement('div');el.id='svoCopyToast';el.setAttribute('role','status');el.setAttribute('aria-live','polite');el.style.cssText='position:fixed;top:18px;left:50%;transform:translateX(-50%);background:#166534;color:white;padding:11px 24px;border-radius:12px;box-shadow:0 8px 25px #0003;z-index:2147483647;font:600 14px system-ui,sans-serif;text-align:center;pointer-events:none;opacity:0;transition:opacity .18s';document.body.appendChild(el)}
    // Modal dialogs live in the browser top layer; page-level z-index cannot cover them.
    const openDialogs=[...document.querySelectorAll('dialog[open]')];
    const host=openDialogs.length?openDialogs[openDialogs.length-1]:document.body;
    if(el.parentElement!==host)host.appendChild(el);
    el.textContent=message;el.style.opacity='1';clearTimeout(el._hide);el._hide=setTimeout(()=>{el.style.opacity='0'},2200);
  };
})();


/* P20: progressively enhanced same-origin GET navigation; financial POSTs remain native. */
(()=>{
 'use strict';
 const shell=document.querySelector('.app-shell'),dock=shell?.querySelector('.p16-dock');
 if(!shell||!dock||!window.fetch||!window.DOMParser||!window.history?.pushState||!window.AbortController)return;
 const allowed=new Set(['dashboard','entry','customers','analytics','transfers','companies']);
 const localScripts=new Set(['svo-customer-live612.js','svo-patch059.js','svo-mother617.js','transfer619.js','transfer-history620.js','transfer-receipt619.js']);
 let flight=null,dirty=false,activeUrl=location.href,scope=new AbortController(),seq=0;
 window.p20PageSignal=scope.signal;
 const content=()=>shell.querySelector('main.content[data-p20-view]');
 function route(url){if(url.origin!==location.origin||url.pathname!==new URL('index.php',location.href).pathname||url.hash)return null;const keys=[...url.searchParams.keys()];return keys.length===1&&keys[0]==='page'&&allowed.has(url.searchParams.get('page'))?url.searchParams.get('page'):null}
 function unsafe(main){
   if(!main)return true;
   return [...main.querySelectorAll('script')].some(sc=>{
    if(sc.src){const u=new URL(sc.getAttribute('src'),location.href),n=u.pathname.split('/').pop();return u.origin!==location.origin||(!localScripts.has(n)&&n!=='transfer-calendar620.js')}
    // Legacy report result templates have top-level lexical declarations and are intentionally native.
    return /^\s*(?:const|let|var|async\s+function|function)\s+/m.test(sc.textContent)&&!sc.textContent.trim().startsWith('(()=>')&&!sc.textContent.trim().startsWith('(function')&&!sc.textContent.trim().startsWith('window.');
   });
 }
 function eligible(){return !!content()&&allowed.has(content().dataset.p20View)&&!dirty&&!document.querySelector('dialog[open]')&&!unsafe(content())}
 function busy(on,page){
  let el=shell.querySelector('#p20Skeleton');
  if(on){if(!el){el=document.createElement('div');el.id='p20Skeleton';el.className='p20-skeleton';el.setAttribute('role','status');el.setAttribute('aria-label','در حال دریافت صفحه');content()?.before(el)}
   el.innerHTML='<div class="p20-sk-heading"></div><div class="p20-sk-grid"><i></i><i></i><i></i></div><div class="p20-sk-panel">'+Array.from({length:page==='dashboard'?4:7},()=>'<i></i>').join('')+'</div>';shell.classList.add('p20-loading');const m=content();if(m){m.setAttribute('aria-busy','true');m.inert=true;}
  }else{el?.remove();shell.classList.remove('p20-loading');const m=content();if(m){m.removeAttribute('aria-busy');m.inert=false;}}
 }
 function native(url){location.assign(url.href)}
 function executeScript(old){
   return new Promise((resolve,reject)=>{
    const sc=document.createElement('script');
    if(old.hasAttribute('src')){
     const u=new URL(old.getAttribute('src'),location.href),n=u.pathname.split('/').pop();
     if(n==='transfer-calendar620.js'){resolve();return}
     if(u.origin!==location.origin||!localScripts.has(n)){reject(Error('unsupported page script'));return}
     sc.src=u.href;sc.async=false;sc.onload=()=>{sc.remove();resolve()};sc.onerror=()=>{sc.remove();reject(Error('page script failed'))};
    }else{sc.textContent=old.textContent;}
    document.body.appendChild(sc);if(!old.hasAttribute('src')){sc.remove();resolve()}
   })
 }
 function pageStyleLinks(main){return [...main.querySelectorAll('link[rel=stylesheet]')].map(l=>new URL(l.getAttribute('href'),location.href).href).sort().join('|')}
 async function navigate(url,push){
   const page=route(url);if(!page||!eligible()){native(url);return}
   if(flight)flight.abort();const ctl=new AbortController();flight=ctl;const token=++seq;busy(true,page);
   let timer=setTimeout(()=>ctl.abort(),15000);
   try{
    const response=await fetch(url.href,{credentials:'same-origin',cache:'no-store',headers:{'X-Soltan-PJAX':'1','Accept':'text/html'},signal:ctl.signal});
    if(!response.ok||response.redirected||!String(response.headers.get('content-type')||'').includes('text/html'))throw Error('native response required');
    const html=await response.text();if(token!==seq)return;
    const doc=new DOMParser().parseFromString(html,'text/html'),next=doc.querySelector('.app-shell main.content[data-p20-view]'),nextDock=doc.querySelector('.app-shell .p16-dock');
    if(!next||next.dataset.p20View!==page||!nextDock||doc.querySelector('.p20-required')||unsafe(next)||!eligible())throw Error('native route required');
    const old=content();
    // Head/inline styles and settings are not patched across incompatible page structures.
    const headStyles=d=>[...d.head.querySelectorAll('link[rel=stylesheet],style')].map(x=>x.outerHTML).join('|');
    if(headStyles(doc)!==headStyles(document))throw Error('style context changed');
    const scripts=[...next.querySelectorAll('script')];scripts.forEach(sc=>sc.remove());
    scope.abort();scope=new AbortController();window.p20PageSignal=scope.signal;
    document.getElementById('transferCalendar620')?.close();window.TransferCalendar620?.resetCache();
    for(const cls of [...document.body.classList])if(cls.startsWith('page-'))document.body.classList.remove(cls);
    document.body.classList.add('page-'+page);
    old.replaceWith(document.importNode(next,true));shell.classList.remove('p20-loading');
    dock.querySelectorAll('a').forEach(a=>{const selected=[...nextDock.querySelectorAll('a')].find(n=>n.getAttribute('href')===a.getAttribute('href'))?.classList.contains('active');a.classList.toggle('active',!!selected);if(selected)a.setAttribute('aria-current','page');else a.removeAttribute('aria-current')});
    if(push)history.pushState({p20:true},'',url.href);
    activeUrl=url.href;document.title=doc.title;dirty=false;
    // Inline scripts run in document order, followed by the original deferred local libraries.
    let initFailed=false;const onError=()=>{initFailed=true};window.addEventListener('error',onError);
    try{
     for(const sc of scripts.filter(sc=>!sc.hasAttribute('src')))await executeScript(sc);
     for(const sc of scripts.filter(sc=>sc.hasAttribute('src')))await executeScript(sc);
     window.p20InitControls?.();window.p20InitKeyboard?.();window.TransferCalendar620?.init();window.p20InitPageEnd?.();
     if(initFailed)throw Error('page initialization failed');
    }finally{window.removeEventListener('error',onError)}
    if(token!==seq)return;flight=null;clearTimeout(timer);busy(false,page);window.scrollTo(0,0);
    content()?.dispatchEvent(new CustomEvent('soltan:page-ready',{bubbles:true,detail:{page}}));
   }catch(e){if(token===seq)native(url);}
   finally{clearTimeout(timer);if(token===seq){flight=null;busy(false,page)}}
 }
 // Do not intercept POST, downloads, external links, deep links or editing flows.
 dock.addEventListener('click',e=>{
  const a=e.target.closest('a');if(!a||e.defaultPrevented||e.button!==0||e.ctrlKey||e.metaKey||e.shiftKey||e.altKey||a.hasAttribute('download')||a.target)return;
  const url=new URL(a.href);if(!route(url)||!eligible())return;
  if(flight){e.preventDefault();return}
  if(url.href===location.href){e.preventDefault();return}
  e.preventDefault();navigate(url,true);
 });
 document.addEventListener('input',e=>{if(e.target.closest?.('main.content form[method=post],main.content form:not([method])')&&!e.target.matches('[type=search]'))dirty=true});
 document.addEventListener('change',e=>{if(e.target.closest?.('main.content form[method=post],main.content form:not([method])'))dirty=true});
 document.addEventListener('submit',()=>{if(flight){++seq;flight.abort();flight=null;busy(false,'')}},true);
 history.replaceState({...history.state,p20:true},'',location.href);
 window.addEventListener('popstate',e=>{const url=new URL(location.href);if(url.href!==activeUrl){if(!e.state?.p20){native(url);return}if(flight){++seq;flight.abort();flight=null;busy(false,'')}navigate(url,false)}});
})();
