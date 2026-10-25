/* v0.6.13: native cumulative summary, normalized totals, and high-resolution export */
(()=>{'use strict';
let latest=null,imageBlob=null,imageUrl=null;
const q=id=>document.getElementById(id);
const el=(tag,cls,text)=>{const n=document.createElement(tag);if(cls)n.className=cls;if(text!==undefined)n.textContent=String(text);return n};
const fmt=(n)=>{if(!latest)return String(n);return (Number(n||0)/Number(latest.factor||1)).toLocaleString(latest.locale||'en-US',{minimumFractionDigits:0,maximumFractionDigits:0})};
const sumItems=list=>(Array.isArray(list)?list:[]).reduce((sum,item)=>sum+(Number(item&&item.amount)||0),0);
function normalize(data){const recv=Array.isArray(data?.recv)?data.recv:[];const pay=Array.isArray(data?.pay)?data.pay:[];const received=sumItems(recv);const paid=sumItems(pay);const net=Number.isFinite(Number(data.net))?Number(data.net):received-paid;data.recv=recv;data.pay=pay;data.received=received;data.paid=paid;data.total_received=received;data.total_paid=paid;data.net=net;data.colors=data.colors||{};data.colors.recv=data.colors.recv||'#ff5d60';data.colors.pay=data.colors.pay||'#92cdd9';data.colors.win=data.colors.win||data.colors.pay;data.colors.loss=data.colors.loss||data.colors.recv;data.colors.result=net>0?data.colors.win:net<0?data.colors.loss:(data.colors.comm||data.colors.pay);return data}
function cleanup(){if(imageUrl){URL.revokeObjectURL(imageUrl);imageUrl=null;}imageBlob=null;}
function close(){q('svoCumulativeDialog')?.close();cleanup()}
function drawRows(host,items,cls){if(!items.length){const tr=el('tr'),td=el('td','summary-empty','موردی نیست');td.colSpan=2;tr.append(td);host.append(tr);return}for(const item of items){const tr=el('tr');tr.append(el('td',cls+' summary-name',item.name),el('td',cls+' summary-amount',fmt(item.amount)));host.append(tr)}}
function panel(cls,title,items){const p=el('section','summary-panel summary-'+cls);p.append(el('h3',cls==='recv'?'recv-cell':'pay-cell',title));const t=el('table'),head=el('thead'),tr=el('tr');tr.append(el('th','', 'نام مشتری'),el('th','', 'مبلغ'));head.append(tr);const body=el('tbody');drawRows(body,items,cls==='recv'?'recv-cell':'pay-cell');t.append(head,body);p.append(t);return p}
function button(title,handler,cls='ghost'){const b=el('button',cls,title);b.type='button';b.addEventListener('click',handler);return b}
function render(input){const data=normalize(input);latest=data;cleanup();const dialog=q('svoCumulativeDialog'),root=q('svoCumulativeContent');if(!dialog||!root)return;
root.replaceChildren();dialog.style.setProperty('--summary-recv',data.colors.recv);dialog.style.setProperty('--summary-pay',data.colors.pay);
const header=el('div','dialog-head'),heading=el('div');heading.append(el('b','',data.title),el('small','',data.date+' • مبالغ به '+data.unit));header.append(heading,button('×',close,'ghost icon-btn'));root.append(header);
const cols=el('div','summary-columns');cols.append(panel('pay','پرداختی',data.pay),panel('recv','دریافتی',data.recv));root.append(cols);
const totals=el('div','summary-totals');for(const [cls,label,value] of [['recv-cell','جمع دریافتی',data.received],['pay-cell','جمع پرداختی',data.paid],[data.net>0?'site-win':data.net<0?'site-loss':'comm-cell',data.net>0?'برد سایت':data.net<0?'باخت سایت':'تسویه',Math.abs(data.net)]]){const x=el('div',cls);x.append(el('span','',label),el('b','',fmt(value)));totals.append(x)}root.append(totals);
const actions=el('div','share-box report-summary-actions');actions.append(button('اشتراک عکس',()=>share('any'),'primary'),button('ذخیره عکس',download),button('کپی عکس',copyImage),button('چاپ / PDF',print),button('بستن',close));root.append(actions);
}
function paintText(ctx,t,x,y,max,font,weight=900){ctx.font=`${weight} ${font}px Vazirmatn,Tahoma,Arial,sans-serif`;ctx.fillStyle='#111';ctx.direction='rtl';ctx.textAlign='center';ctx.textBaseline='middle';ctx.fillText(String(t),x,y,max)}
async function makeImage(){if(imageBlob)return imageBlob;if(!latest)throw Error('گزارش هنوز آماده نیست.');if(document.fonts)await document.fonts.ready;const d=normalize(latest),rows=Math.max(d.recv.length,d.pay.length,1),w=1460,rh=68,top=182,totalY=top+108+rows*rh+40,h=totalY+200,scale=2.25;const c=document.createElement('canvas');c.width=Math.round(w*scale);c.height=Math.round(h*scale);const ctx=c.getContext('2d');if(!ctx)throw Error('ساخت تصویر پشتیبانی نمی‌شود.');ctx.setTransform(scale,0,0,scale,0,0);ctx.fillStyle='#fff';ctx.fillRect(0,0,w,h);paintText(ctx,d.title,w/2,52,w-50,42,900);paintText(ctx,d.date,w/2,108,w-50,30,800);paintText(ctx,'مبالغ به '+d.unit,w/2,150,w-50,21,700);const pad=30,gap=68,cw=(w-2*pad-gap)/2;
function cell(x,y,width,height,color,v,size,weight=800){ctx.fillStyle=color;ctx.fillRect(x,y,width,height);ctx.strokeStyle='#222';ctx.lineWidth=1.6;ctx.strokeRect(x,y,width,height);paintText(ctx,v,x+width/2,y+height/2,width-12,size,weight)}
function column(x,title,items,color){cell(x,top,cw,56,color,title,28,900);cell(x,top+56,cw/2,52,color,'مبلغ',21,800);cell(x+cw/2,top+56,cw/2,52,color,'نام مشتری',21,800);for(let i=0;i<rows;i++){const z=items[i];cell(x,top+108+i*rh,cw/2,rh,z?color:'#fff',z?fmt(z.amount):'',25,900);cell(x+cw/2,top+108+i*rh,cw/2,rh,z?color:'#fff',z?z.name:'',22,800)}}
column(pad,'پرداختی',d.pay,d.colors.pay);column(pad+cw+gap,'دریافتی',d.recv,d.colors.recv);
const resultColor=d.net>0?(d.colors.win||d.colors.pay):d.net<0?(d.colors.loss||d.colors.recv):(d.colors.comm||d.colors.pay);
for(const [i,label,value,color] of [[0,'جمع دریافتی',d.received,d.colors.recv],[1,'جمع پرداختی',d.paid,d.colors.pay],[2,d.net>0?'برد سایت':d.net<0?'باخت سایت':'تسویه',Math.abs(d.net),resultColor]]){cell(pad,totalY+i*58,520,58,color,label,24,800);cell(pad+520,totalY+i*58,w-pad-(pad+520),58,color,fmt(value),26,900)}
imageBlob=await SvoImage617.encode(c);return imageBlob}
async function download(){try{const blob=await makeImage(),url=URL.createObjectURL(blob),a=document.createElement('a');a.href=url;a.download='soltan-cumulative-summary.png';a.click();setTimeout(()=>URL.revokeObjectURL(url),60000)}catch(e){alert(e.message)}}
async function copyImage(){try{await SvoImage617.copyImage(()=>makeImage(),'cumulative.png');svoCopyToast('عکس کپی شد.')}catch(e){alert(e.message)}}
async function share(target){try{const blob=await makeImage(),file=new File([blob],'soltan-cumulative-summary.png',{type:'image/png'});if(navigator.share&&(!navigator.canShare||navigator.canShare({files:[file]}))){await navigator.share({files:[file],title:latest.title});return}await download();if(target!=='any')alert('عکس ذخیره شد؛ برای ارسال آن را در '+(target==='telegram'?'تلگرام':'واتساپ')+' پیوست کن.')}catch(e){if(e.name!=='AbortError')alert(e.message)}}
function print(){if(!latest)return;const root=q('svoCumulativeContent');const w=window.open('','_blank');if(!w){alert('اجازه باز شدن پنجره چاپ را بده.');return}const style=[...document.styleSheets].map(s=>{try{return [...s.cssRules].map(r=>r.cssText).join('\n')}catch(e){return ''}}).join('\n');w.document.open();w.document.write('<!doctype html><html lang="fa" dir="rtl"><head><meta charset="UTF-8"><title>خلاصه تجمعی</title><style>'+style+'body{background:#fff!important}.report-summary-actions,.dialog-head button{display:none!important}.report-summary-card{max-width:980px;margin:auto}@media print{@page{size:A4 portrait;margin:8mm}}</style></head><body>'+root.outerHTML+'</body></html>');w.document.close();w.focus();w.onload=()=>w.print();setTimeout(()=>w.print(),500)}
window.svoShowNativeSummary=async ids=>{const dialog=q('svoCumulativeDialog'),root=q('svoCumulativeContent');if(!dialog||!root)return;root.textContent='در حال آماده‌سازی خلاصه…';if(!dialog.open)dialog.showModal();try{const p=new URLSearchParams();ids.forEach(id=>p.append('ids[]',id));const res=await fetch('cumulative_preview.php?'+p.toString(),{credentials:'same-origin',cache:'no-store'});const body=normalize(await res.json());if(!res.ok||!body.ok)throw Error(body.error||'خطای دریافت خلاصه');if(Number(body.selected_count)!==new Set(ids.map(String)).size)throw Error('تعداد گزارش‌های دریافت‌شده با انتخاب شما متفاوت است.');if(dialog.open)render(body)}catch(e){root.replaceChildren(el('div','notice bad',e.message))}};
})();

// Consolidated v0.6.3 controls: one implementation of selection and filters.
(()=>{'use strict';
 const $=s=>document.querySelector(s), $$=s=>Array.from(document.querySelectorAll(s));
 const checks=()=>$$('#reportDataTable tbody input[name="ids[]"]');
 const chosen=()=>checks().filter(el=>el.checked);
 const header=()=>$('#svoSelectAllReports') || $('#reportDataTable thead input[type="checkbox"]');
 function sync(){const items=checks(),on=chosen(),head=header();if(head){head.checked=items.length>0&&on.length===items.length;head.indeterminate=on.length>0&&on.length<items.length;head.disabled=!items.length;head.setAttribute('aria-label',head.checked?'لغو انتخاب همه':'انتخاب همه گزارش‌های نمایش‌داده‌شده')};const c=$('#svoSelectionCount');if(c)c.textContent=on.length.toLocaleString('fa-IR')+' گزارش انتخاب‌شده';}
 function init(){const head=header();if(!head)return;head.removeAttribute('onclick');head.addEventListener('click',e=>{e.stopPropagation();checks().forEach(c=>{c.checked=head.checked});sync()});checks().forEach(c=>c.addEventListener('change',sync));const actions=$('#cumulativeSelectForm')?.nextElementSibling;if(actions&&!$('#svoSelectionCount')){const counter=document.createElement('strong');counter.id='svoSelectionCount';counter.className='svo-selection-count';actions.prepend(counter)}sync()}
 window.svoOpenCumulative=()=>{const ids=chosen().map(x=>x.value);if(!ids.length){if(window.appAlert)appAlert('حداقل یک گزارش را انتخاب کن.');else alert('حداقل یک گزارش را انتخاب کن.');return false;}if(new Set(ids).size!==ids.length){if(window.appAlert)appAlert('شناسه تکراری در انتخاب وجود دارد.');return false;}if(typeof window.svoShowNativeSummary!=='function'){if(window.appAlert)appAlert('پنجره خلاصه در دسترس نیست؛ صفحه را تازه‌سازی کن.');return false;}window.svoShowNativeSummary(ids);return false;};
 function initDate(){
  const form=$('#reportFilterForm'),type=$('#reportTypeSelect'),single=form?.querySelector('[name="date"]');
  if(!form||!type||!single)return;
  const today=single.dataset.today||'';
  function sync(){
   const range=form.querySelector('input[name="date_mode"]:checked')?.value==='range';
   form.classList.toggle('svo-range-active',range);
   form.querySelectorAll('.svo-single-date').forEach(el=>{el.hidden=range;el.classList.toggle('hidden',range);el.querySelectorAll('input').forEach(i=>i.disabled=range)});
   form.querySelectorAll('.svo-range-date').forEach(el=>{el.hidden=!range;el.classList.toggle('hidden',!range);el.querySelectorAll('input').forEach(i=>i.disabled=!range)});
  }
  form.querySelectorAll('input[name="date_mode"]').forEach(el=>el.addEventListener('change',sync));
  // Legacy inline handler also listens for changes. Re-run sync after its toggles.
  sync();
  type.addEventListener('change',()=>{
   if(type.value==='cumulative'&&!single.value.trim()&&today){single.value=today;single.dataset.autoFill='1';single.dispatchEvent(new Event('input',{bubbles:true}))}
   else if(type.value!=='cumulative'&&single.dataset.autoFill==='1'){single.value='';delete single.dataset.autoFill}
   sync();
  });
 }
 document.addEventListener('DOMContentLoaded',()=>{init();initDate()});
})();
