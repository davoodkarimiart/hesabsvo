/* Dual Jalali/Gregorian calendar, mobile-first and server-backed transfer occupancy. */
(()=>{'use strict';
const pMonth=['فروردین','اردیبهشت','خرداد','تیر','مرداد','شهریور','مهر','آبان','آذر','دی','بهمن','اسفند'];
const pWeek=['ش','ی','د','س','چ','پ','ج'];
const fa=n=>String(n).replace(/\d/g,d=>'۰۱۲۳۴۵۶۷۸۹'[Number(d)]);
const parse=s=>new Date(String(s)+'T12:00:00');
const iso=d=>[d.getFullYear(),String(d.getMonth()+1).padStart(2,'0'),String(d.getDate()).padStart(2,'0')].join('-');
const add=(d,days)=>{const x=new Date(d);x.setDate(x.getDate()+days);return x};
const jp=new Intl.DateTimeFormat('en-US-u-ca-persian',{year:'numeric',month:'numeric',day:'numeric'});
const pers=d=>{const obj={};jp.formatToParts(d).forEach(t=>{if(['year','month','day'].includes(t.type))obj[t.type]=Number(t.value)});return obj};
const jalali=d=>{if(!(d instanceof Date)||Number.isNaN(d.getTime()))return '—';const p=pers(d);return fa(p.year+'/'+String(p.month).padStart(2,'0')+'/'+String(p.day).padStart(2,'0'))};
const greg=d=>iso(d);
const startOfMonth=(anchor,mode)=>{const d=new Date(anchor);if(mode==='gregorian')return new Date(d.getFullYear(),d.getMonth(),1,12);const n=pers(d).day;return add(d,1-n)};
const nextMonth=(first,mode,delta)=>{if(mode==='gregorian')return new Date(first.getFullYear(),first.getMonth()+delta,1,12);return startOfMonth(add(first,delta>0?35:-1),mode)};
const sameMonth=(a,b,mode)=>mode==='gregorian'?(a.getFullYear()===b.getFullYear()&&a.getMonth()===b.getMonth()):(()=>{const x=pers(a),y=pers(b);return x.year===y.year&&x.month===y.month})();
function describe(start){const p=pers(start);return `${pMonth[p.month-1]} ${fa(p.year)}  •  ${new Intl.DateTimeFormat('en-US',{month:'short',year:'numeric'}).format(start)}`;}
let dialog=null,active=null,monthAnchor=null,calendarMode='jalali',seq=0,renderVersion=0,states={},cacheKey='';
const el=(tag,cls,txt)=>{const n=document.createElement(tag);if(cls)n.className=cls;if(txt!==undefined)n.textContent=txt;return n};
function ensure(){if(dialog)return;
 dialog=document.createElement('dialog');dialog.id='transferCalendar620';dialog.className='transfer-calendar-modal';dialog.setAttribute('aria-label','تقویم دوگانه ترانسفر');
 dialog.innerHTML=`<div class="transfer-calendar-shell"><div class="tc-top"><div><strong>انتخاب تاریخ ترانسفر</strong><small>شمسی و میلادی، وضعیت تسویه هر روز</small></div><button type="button" class="tc-close" aria-label="بستن">×</button></div><div class="tc-mode" role="group" aria-label="نوع تقویم"><button type="button" data-mode="jalali" aria-pressed="true">شمسی</button><button type="button" data-mode="gregorian" aria-pressed="false">میلادی</button></div><div class="tc-heading"><button class="tc-prev" type="button" aria-label="ماه قبل">‹</button><div class="tc-month"></div><button class="tc-next" type="button" aria-label="ماه بعد">›</button></div><div class="tc-grid" role="grid" aria-label="روزهای ماه"></div><div class="tc-legend"><span class="tc-dot tc-settled">تسویه‌شده</span><span class="tc-dot tc-ready">آزاد، گزارش موجود</span><span class="tc-dot tc-missing">بدون گزارش</span><span class="tc-dot tc-chosen">انتخاب‌شده</span></div><div class="tc-message" aria-live="polite"></div><div class="tc-selected"></div><button class="tc-done" type="button">بستن تقویم</button></div>`;
 document.body.append(dialog);
 dialog.querySelector('.tc-close').addEventListener('click',()=>dialog.close());dialog.querySelector('.tc-done').addEventListener('click',()=>dialog.close());
 dialog.querySelector('.tc-prev').addEventListener('click',()=>{monthAnchor=nextMonth(startOfMonth(monthAnchor,calendarMode),calendarMode,-1);render()});
 dialog.querySelector('.tc-next').addEventListener('click',()=>{monthAnchor=nextMonth(startOfMonth(monthAnchor,calendarMode),calendarMode,1);render()});
 dialog.querySelectorAll('[data-mode]').forEach(btn=>btn.addEventListener('click',()=>{calendarMode=btn.dataset.mode;render()}));
 dialog.addEventListener('click',e=>{if(e.target===dialog)dialog.close()});
}
const selectedInfo=()=>{const field=active?.field;const d=field?.value&&parse(field.value);return d&&!Number.isNaN(d.getTime())?`انتخاب فعلی: ${jalali(d)}  |  ${greg(d)}`:'تاریخی انتخاب نشده است';};
function select(d,entry){const msg=dialog.querySelector('.tc-message');if(entry?.state==='settled'&&active.mode==='wizard'){
  const a=document.createElement('a');a.href='index.php?page=transfers&tab=list&view='+entry.transfer_id;a.textContent='مشاهده رسید '+(entry.transfer_code||'');msg.replaceChildren(document.createTextNode('این روز قبلاً تسویه شده و قابل انتخاب نیست. '),a);return;
 }
 active.field.value=iso(d);active.field.dispatchEvent(new Event('change',{bubbles:true}));refresh();dialog.querySelector('.tc-selected').textContent=selectedInfo();dialog.close();
}
function gridDay(d,within,selected){const val=iso(d),info=states[val]||{},state=info.state||'unknown',button=el('button','tc-day tc-'+state+(within?'':' tc-outside')+(selected?' tc-picked':''));button.type='button';button.setAttribute('role','gridcell');button.setAttribute('aria-label',jalali(d)+'، '+greg(d)+'، '+(state==='settled'?'تسویه شده':state==='ready'?'گزارش موجود':state==='missing'?'بدون گزارش':'وضعیت نامشخص'));
 if(state==='settled'&&active.mode==='wizard')button.setAttribute('aria-disabled','true');
 button.append(el('b',null,fa(calendarMode==='jalali'?pers(d).day:d.getDate())));
 button.append(el('small',null,calendarMode==='jalali'?String(d.getDate()):fa(pers(d).day)));
 if(state==='settled')button.append(el('i',null,'🔒'));else if(state==='ready')button.append(el('i',null,'•'));else if(state==='missing')button.append(el('i',null,'·'));
 button.addEventListener('click',()=>select(d,info));return button;
}
async function load(start,end){const company=Number(active?.company?.value||0),editing=active?.edit||0,msg=dialog.querySelector('.tc-message');
 if(company<1){states={};msg.textContent='برای دیدن روزهای تسویه‌شده، شرکت را در فرم ثبت انتخاب کن.';return;}
 const key=company+'|'+iso(start)+'|'+iso(end)+'|'+editing;
 if(key===cacheKey)return;
 msg.textContent='وضعیت روزهای این ماه در حال بررسی است…';const idx=++seq;
 try{
 const q=new URLSearchParams({company_id:String(company),from:iso(start),to:iso(end)});if(editing)q.set('editing',editing);
 const r=await fetch('transfer_calendar620.php?'+q.toString(),{credentials:'same-origin',cache:'no-store'});const j=await r.json();if(!r.ok||!j.ok)throw Error(j.error||'خطای دریافت تقویم');if(idx!==seq)return;
 states=j.data.days||{};cacheKey=key;msg.textContent='روی روز سبز یا خاکستری بزن؛ روزهای قرمز قبلاً تسویه شده‌اند.';
 }catch(e){if(idx!==seq)return;states={};cacheKey='';msg.textContent='وضعیت قابل دریافت نیست: '+e.message+'؛ تأیید قطعی ثبت در سرور انجام می‌شود.';}
}
async function render(){ensure();const thisRender=++renderVersion;dialog.querySelectorAll('[data-mode]').forEach(b=>b.setAttribute('aria-pressed',String(b.dataset.mode===calendarMode)));
 const first=startOfMonth(monthAnchor||new Date(),calendarMode);monthAnchor=first;
 dialog.querySelector('.tc-month').textContent=calendarMode==='jalali'?describe(first):`${new Intl.DateTimeFormat('fa-IR-u-ca-gregory',{month:'long',year:'numeric'}).format(first)} • ${jalali(first)}`;
 const start=add(first,-(first.getDay()+1)%7),grid=dialog.querySelector('.tc-grid');grid.replaceChildren();
 for(const day of pWeek)grid.append(el('span','tc-weekday',day));
 const arr=Array.from({length:42},(_,i)=>add(start,i));
 const selected=active.field.value;
 for(const date of arr)grid.append(gridDay(date,sameMonth(date,first,calendarMode),selected===iso(date)));
 dialog.querySelector('.tc-selected').textContent=selectedInfo();
 await load(start,arr[arr.length-1]);
 if(!dialog.open||thisRender!==renderVersion)return;grid.replaceChildren();for(const day of pWeek)grid.append(el('span','tc-weekday',day));for(const date of arr)grid.append(gridDay(date,sameMonth(date,first,calendarMode),selected===iso(date)));
}
function open(button){ensure();const field=document.getElementById(button.dataset.transferDate);if(!field)return;active={field,mode:button.dataset.mode||'wizard',company:document.getElementById(button.dataset.companyInput||'transferCompany'),edit:Number(button.dataset.edit||0)};monthAnchor=field.value?parse(field.value):new Date();states={};cacheKey='';dialog.showModal();render()}
function refresh(){document.querySelectorAll('[data-transfer-date]').forEach(button=>{const field=document.getElementById(button.dataset.transferDate);if(!field)return;const dt=field.value?parse(field.value):null;const title=button.querySelector('.tc-date-main');const secondary=button.querySelector('.tc-date-secondary');if(title)title.textContent=dt?jalali(dt):'انتخاب تاریخ';if(secondary)secondary.textContent=dt?greg(dt):'شمسی / میلادی';});}
function init(){document.querySelectorAll('[data-transfer-date]').forEach(button=>button.addEventListener('click',()=>open(button)));document.querySelectorAll('[data-transfer-iso]').forEach(field=>field.addEventListener('change',refresh));refresh()}
window.TransferCalendar620={init,refresh,iso,parse,add,jalali,greg,resetCache:()=>{states={};cacheKey=''},open};
if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',init);else init();
})();
