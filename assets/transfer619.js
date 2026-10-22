/* Transfer wizard v0.6.19. 5-step responsive flow, server-authoritative calculations. */
(()=>{'use strict';
const f=document.getElementById('transferWizardForm');if(!f)return;
const conf=window.TRANSFER_SETUP619||{},$=id=>document.getElementById(id),steps=[...f.querySelectorAll('[data-step]')],progress=[...document.querySelectorAll('.transfer-progress span')];
const fields={company:$('transferCompany'),type:$('transferPeriodType'),start:$('transferStart'),end:$('transferEnd'),override:$('transferUsdOverride'),overrideReason:$('transferOverrideReason'),percent:$('transferPercent'),rate:$('transferRate'),notes:$('transferNotes'),amountToggle:$('transferChangeAmount'),checkAmount:$('transferConfirmAmount'),final:$('transferConfirmFinal')};
let step=1,source=null,calculation=null,loading=false;
const usd=value=>Number(value||0).toLocaleString('en-US',{minimumFractionDigits:2,maximumFractionDigits:2});
const toman=value=>Number(value||0).toLocaleString('en-US',{maximumFractionDigits:0});
const normal=value=>String(value??'').replace(/[۰-۹]/g,d=>'۰۱۲۳۴۵۶۷۸۹'.indexOf(d)).replace(/[٠-٩]/g,d=>'٠١٢٣٤٥٦٧٨٩'.indexOf(d)).replace(/,/g,'').replace(/٫/g,'.').trim();
const error=message=>{const e=$('transferWizardError');e.textContent=message||'';e.hidden=!message;if(message)e.scrollIntoView({behavior:'smooth',block:'nearest'})};
const iso=d=>[d.getFullYear(),String(d.getMonth()+1).padStart(2,'0'),String(d.getDate()).padStart(2,'0')].join('-');
const parseIso=s=>new Date(String(s)+'T12:00:00');
const offset=(str,n)=>{const d=parseIso(str);d.setDate(d.getDate()+n);return iso(d)};
const edit=conf.edit||null;
// Money grouping affects only what the user sees. Keep unmodified DB snapshots intact.
const rateField=fields.rate,wordsBox=$('transferRateWords');
const ones=['','یک','دو','سه','چهار','پنج','شش','هفت','هشت','نه'];
const teens=['ده','یازده','دوازده','سیزده','چهارده','پانزده','شانزده','هفده','هجده','نوزده'];
const tens=['','','بیست','سی','چهل','پنجاه','شصت','هفتاد','هشتاد','نود'];
const hundreds=['','صد','دویست','سیصد','چهارصد','پانصد','ششصد','هفتصد','هشتصد','نهصد'];
const levels=['','هزار','میلیون','میلیارد','هزار میلیارد'];
function chunkWords(n){const result=[],h=Math.trunc(n/100),tail=n%100;if(h)result.push(hundreds[h]);if(tail>=10&&tail<20)result.push(teens[tail-10]);else{if(tail>=20)result.push(tens[Math.trunc(tail/10)]);if(tail%10&&tail>=20||tail>0&&tail<10)result.push(ones[tail%10])}return result.filter(Boolean).join(' و ')}
function tomanWords(v){const val=Number(normal(v));if(!Number.isFinite(val)||val<0||val>999999999999999)return 'مبلغ خارج از محدوده نمایش به حروف است.';let n=Math.round(val);if(!n)return 'صفر تومان';const parts=[];let i=0;while(n>0&&i<levels.length){const group=n%1000;if(group)parts.unshift(chunkWords(group)+(levels[i]?' '+levels[i]:''));n=Math.floor(n/1000);i++}return parts.join(' و ')+' تومان'}
function groupedRate(value){const n=normal(value);if(!n||!/^\d+(?:\.\d*)?$/.test(n))return String(value||'');const [a,b]=n.split('.');return a.replace(/\B(?=(\d{3})+(?!\d))/g,',')+(b===undefined?'':'.'+b)}
function refreshRateWords(){const value=rateField.value.trim();if(!wordsBox)return;wordsBox.textContent=value?tomanWords(value):'مبلغ نرخ به حروف اینجا نمایش داده می‌شود.'}
rateField.addEventListener('input',()=>{rateField.dataset.rateChanged='1';let caret=rateField.selectionStart??rateField.value.length;const old=rateField.value,display=groupedRate(old);if(old!==display){const digitsBefore=old.slice(0,caret).replace(/,/g,'').length;rateField.value=display;let seen=0;caret=0;while(caret<display.length&&seen<digitsBefore){if(display[caret]!==',')seen++;caret++}try{rateField.setSelectionRange(caret,caret)}catch(e){}}refreshRateWords()});
rateField.addEventListener('blur',()=>{rateField.value=groupedRate(rateField.value);refreshRateWords()});
function rateForServer(){return edit&&rateField.dataset.rateChanged!=='1'&&rateField.dataset.originalRate?rateField.dataset.originalRate:normal(rateField.value)}
function overrideForServer(){return edit&&fields.override.dataset.changed!=='1'&&fields.override.dataset.originalUsd?fields.override.dataset.originalUsd:normal(fields.override.value)}
fields.override.addEventListener('input',()=>{fields.override.dataset.changed='1'});

function monthlyStart(endStr){const end=parseIso(endStr);const prev=new Date(end.getFullYear(),end.getMonth()-1,1,12);const prevMax=new Date(prev.getFullYear(),prev.getMonth()+1,0,12).getDate();const day=Math.min(end.getDate(),prevMax);return iso(new Date(prev.getFullYear(),prev.getMonth(),day+1,12))}
function recomputeStart(){if(!fields.end.value)return;const mode=fields.type.value;if(mode==='weekly')fields.start.value=offset(fields.end.value,-6);else if(mode==='monthly')fields.start.value=monthlyStart(fields.end.value);if(window.TransferCalendar620)TransferCalendar620.refresh();help()}
function help(){const box=$('transferPeriodHelp'),mode=fields.type.value;box.textContent=(mode==='weekly'?'۷ روز شناور':mode==='monthly'?'یک ماه شناور':'بازه دلخواه')+' • '+(fields.start.value||'—')+' تا '+(fields.end.value||'—')+' • شمسی و میلادی را از تقویم می‌بینی.'}
function preset(){const mode=fields.type.value;if(mode==='custom'){help();invalidate();return}recomputeStart();invalidate()}
function invalidate(){source=null;calculation=null;fields.checkAmount.checked=false;fields.final.checked=false;error('');help();checkOverlap()}
fields.type.addEventListener('change',preset);
fields.company.addEventListener('change',()=>{TransferCalendar620?.resetCache();invalidate()});
fields.end.addEventListener('change',()=>{if(fields.type.value!=='custom')recomputeStart();invalidate()});
fields.start.addEventListener('change',()=>{if(fields.type.value!=='custom')fields.type.value='custom';invalidate()});
let overlapSeq=0,overlapInfo=null;
async function checkOverlap(){const box=$('transferPeriodOverlap'),seq=++overlapSeq;box.hidden=true;box.replaceChildren();overlapInfo=null;
if(!fields.company.value||!fields.start.value||!fields.end.value||fields.start.value>fields.end.value)return;
try{const q=new URLSearchParams({mode:'range',company_id:fields.company.value,from:fields.start.value,to:fields.end.value});if(edit)q.set('editing',edit.id);
 const response=await fetch('transfer_calendar620.php?'+q.toString(),{cache:'no-store',credentials:'same-origin'}),data=await response.json();if(seq!==overlapSeq)return;
 if(!response.ok||!data.ok)throw Error(data.error||'بررسی بازه انجام نشد');overlapInfo=data.data;
 if(!overlapInfo.overlap_days){box.hidden=false;box.className='transfer-period-overlap good';box.textContent='✓ بازه از نظر تکراری نبودن تاریخ‌های تسویه تأیید شد.';return}
 box.className='transfer-period-overlap bad';box.hidden=false;
 const msg=document.createElement('p');msg.textContent='⛔ '+overlapInfo.overlap_days.toLocaleString('fa-IR')+' روز از این بازه قبلاً تسویه شده است. امکان ثبت روی روز تکراری وجود ندارد.';box.append(msg);
 for(const row of overlapInfo.overlaps.slice(0,4)){const a=document.createElement('a');a.href='index.php?page=transfers&tab=list&view='+row.id;a.textContent=(row.transfer_code||'ترانسفر')+' ('+row.period_start+' تا '+row.period_end+')';box.append(a)}
 if(overlapInfo.suggested_start){const button=document.createElement('button');button.type='button';button.className='ghost';button.textContent='اصلاح خودکار: شروع از '+overlapInfo.suggested_start;button.addEventListener('click',()=>{fields.type.value='custom';fields.start.value=overlapInfo.suggested_start;fields.start.dispatchEvent(new Event('change',{bubbles:true}));TransferCalendar620?.refresh();});box.append(button)}
 }catch(e){if(seq!==overlapSeq)return;box.hidden=false;box.className='transfer-period-overlap warn';box.textContent='بررسی هم‌پوشانی فعلاً انجام نشد: '+e.message+'؛ موقع ثبت نهایی دوباره کنترل می‌شود.'}
}
fields.amountToggle.addEventListener('change',()=>{$('transferAmountEdit').hidden=!fields.amountToggle.checked;if(!fields.amountToggle.checked){fields.override.value='';fields.overrideReason.value=''}calculation=null});
for(const k of ['override','percent','rate','overrideReason'])fields[k].addEventListener('input',()=>{calculation=null;fields.final.checked=false});
function showStep(next){step=next;steps.forEach(a=>{a.hidden=Number(a.dataset.step)!==step});progress.forEach((p,i)=>p.classList.toggle('active',i<step));$('transferStepCount').textContent='مرحله '+step+' از ۵';$('transferPrev').hidden=step===1;$('transferNext').hidden=step===5;$('transferSave').hidden=step!==5;error('');if(next>1)document.querySelector('.transfer-step-head')?.scrollIntoView({block:'start',behavior:'smooth'})}

async function request(withRate=false){const query=new URLSearchParams({company_id:fields.company.value,period_type:fields.type.value,period_start:fields.start.value,period_end:fields.end.value});if(edit)query.set('editing',edit.id);if(withRate){query.set('transfer_percent',normal(fields.percent.value));query.set('usd_rate_toman',rateForServer());if(fields.amountToggle.checked){query.set('site_usd_override',overrideForServer());query.set('override_reason',fields.overrideReason.value)}}const res=await fetch('transfer_preview619.php?'+query.toString(),{credentials:'same-origin',cache:'no-store'});const body=await res.json();if(!res.ok||!body.ok)throw Error(body.error||'محاسبه انجام نشد.');return body.data}
function renderSource(){if(!source)return;$('transferMemberWin').textContent=usd(source.member_win_total)+' $';$('transferSiteUsd').textContent=(Number(source.site_usd)>=0?'+':'')+usd(source.site_usd)+' $';$('transferReportCount').textContent=source.report_count.toLocaleString('fa-IR');$('transferReportList').replaceChildren();for(const r of source.reports.slice(0,30)){const div=document.createElement('div');div.className='transfer-source-row';const date=document.createElement('span');date.textContent=r.report_date;const name=document.createElement('b');name.textContent='گزارش #'+r.id;const amount=document.createElement('span');amount.dir='ltr';amount.textContent=usd(r.member_total)+' $';div.append(date,name,amount);$('transferReportList').append(div)}if(source.reports.length>30){const n=document.createElement('p');n.className='transfer-hint';n.textContent='نمایش ۳۰ مورد از '+source.reports.length+' گزارش';$('transferReportList').append(n)}
const auditBox=$('transferUsdAuditWarnings');if(auditBox){const warns=source.audit_warnings||[];auditBox.hidden=!warns.length;if(warns.length){auditBox.replaceChildren();const heading=document.createElement('b');heading.textContent='⚠ '+warns.length+' گزارش دارای مغایرت تأییدشده در این دوره';auditBox.append(heading);for(const a of warns){const line=document.createElement('p');line.textContent='تاریخ '+a.report_date+' • گزارش #'+a.daily_report_id+' • اختلاف '+((n)=>Number.isFinite(n)?Math.abs(n).toLocaleString('en-US',{minimumFractionDigits:2,maximumFractionDigits:2}):String(a.discrepancy))(Number(a.discrepancy))+' دلار • علت: '+a.reason;auditBox.append(line)}}}
if(!edit){const match=conf.companies.find(c=>c.id===Number(fields.company.value));if(match)fields.percent.value=match.percent||''}
const co=conf.companies.find(c=>c.id===Number(fields.company.value));$('transferCompanyInfo').textContent=co?'شرکت '+co.name+' • '+(co.role==='master'?'مستر':co.role==='agent'?'ایجنت':'نوع نامشخص')+' • درصد پیش‌فرض: '+(co.percent||'تعریف‌نشده')+'٪':'';
}
function decorateReceipt(data){
 const src=data.source,calc=data.calculation;if(!calc)throw Error('نرخ و درصد را تکمیل کن.');const company=data.company,el=$('transferDraftReceipt');el.replaceChildren();
 const title=document.createElement('div');title.className='transfer-receipt-title';title.textContent='پیش‌نمایش رسید ترانسفر';el.append(title);
 const role=company.company_role==='master'?'مستر':company.company_role==='agent'?'ایجنت':'نوع نامشخص';
 const arr=[['طرف تسویه',company.name+' • '+role],['از / تا (میلادی)',src.start+' تا '+src.end],['از / تا (شمسی)',src.start_fa+' تا '+src.end_fa],['Member Win',(Number(src.member_win_total)>0?'+':'')+usd(src.member_win_total)+' $'],['تعداد گزارش',src.report_count],['درصد سهم',calc.transfer_percent+'٪'],['سهم دلاری',usd(calc.usd_share)+' $'],['نرخ دلار (تومان)',toman(calc.usd_rate_toman)],['مبلغ نهایی (تومان)',toman(Math.abs(Number(calc.total_toman)))],['نوع تسویه',Number(src.member_win_total)>0?'پرداخت به طرف تسویه':Number(src.member_win_total)<0?'دریافت از طرف تسویه':'تسویه صفر']];
 for(const [label,value] of arr){const row=document.createElement('div');row.className='transfer-receipt-line';if(label==='مبلغ نهایی (تومان)')row.classList.add('transfer-receipt-total');if(label==='Member Win')row.classList.add('transfer-member-win');const a=document.createElement('span');a.textContent=label;const b=document.createElement('b');b.textContent=value;if(label==='Member Win')b.className=Number(src.member_win_total)<0?'negative':Number(src.member_win_total)>0?'positive':'';row.append(a,b);el.append(row)}
 const wrap=document.createElement('div');wrap.className='transfer-daily-breakdown';const heading=document.createElement('h3');heading.textContent='ریز روزانه Member Win';wrap.append(heading);
 const table=document.createElement('table'),thead=document.createElement('thead'),head=document.createElement('tr');for(const c of ['#','شمسی / میلادی','Member Win ($)']){const th=document.createElement('th');th.textContent=c;head.append(th)}thead.append(head);table.append(thead);
 const tbody=document.createElement('tbody');const sums=new Map();for(const r of src.reports){sums.set(r.report_date,(sums.get(r.report_date)||0)+Number(r.member_total||0))}
 let num=0;for(let d=parseIso(src.start);iso(d)<=src.end;d.setDate(d.getDate()+1)){
  num++;const day=iso(d),value=sums.has(day)?sums.get(day):null,tr=document.createElement('tr');
  const rowNo=document.createElement('td');rowNo.textContent=String(num);
  const date=document.createElement('td');const fa=document.createElement('span');fa.textContent=new Intl.DateTimeFormat('fa-IR-u-ca-persian',{year:'numeric',month:'2-digit',day:'2-digit'}).format(d);const en=document.createElement('small');en.textContent=day;en.dir='ltr';date.append(fa,en);
  const amount=document.createElement('td');amount.dir='ltr';amount.textContent=value===null?'بدون گزارش':usd(value)+' $';amount.className=value===null?'':value<0?'negative':value>0?'positive':'';
  tr.append(rowNo,date,amount);tbody.append(tr);
 }
 table.append(tbody);wrap.append(table);el.append(wrap);
 if(src.audit_warnings?.length){const warn=document.createElement('div');warn.className='notice warn';warn.textContent='⚠ '+src.audit_warnings.length+' گزارش در این بازه با تأیید مغایرت دلاری ثبت شده‌اند. دلایل آن‌ها را در مرحله کنترل مبلغ بررسی کن.';el.append(warn)}
}

function checkStep(){if(step===1){if(!fields.company.value||!fields.start.value||!fields.end.value)throw Error('شرکت و تاریخ‌های شروع و پایان را وارد کن.');if(fields.start.value>fields.end.value)throw Error('تاریخ شروع نباید بعد از پایان باشد.');if(overlapInfo?.overlap_days>0)throw Error('این بازه روزهای تسویه‌شده دارد؛ از تقویم یا اصلاح خودکار استفاده کن.');return}if(step===2){if(!fields.checkAmount.checked)throw Error('مبلغ و گزارش‌های مبنا را تأیید کن.');if(fields.amountToggle.checked){if(normal(fields.override.value)==='')throw Error('مبلغ دلاری جایگزین را وارد کن.');if(!fields.overrideReason.value.trim())throw Error('دلیل اصلاح مبلغ را بنویس.')}return}if(step===3){const pct=Number(normal(fields.percent.value));if(!Number.isFinite(pct)||pct<0||pct>100||fields.percent.value==='')throw Error('درصد ترانسفر باید بین ۰ و ۱۰۰ باشد.');return}if(step===4){const rate=Number(normal(fields.rate.value));if(!Number.isFinite(rate)||rate<=0||fields.rate.value==='')throw Error('نرخ روز دلار به تومان را وارد کن.');if(edit&&!$('transferEditReason').value.trim())throw Error('دلیل ویرایش ترانسفر را وارد کن.');return}}
$('transferNext').addEventListener('click',async()=>{if(loading)return;loading=true;$('transferNext').disabled=true;try{error('');checkStep();if(step===1){const data=await request(false);source=data.source;renderSource()}if(step===4){const data=await request(true);source=data.source;calculation=data.calculation;decorateReceipt(data)}showStep(step+1)}catch(e){error(e.message||'مشکل در دریافت اطلاعات ترانسفر.')}finally{loading=false;$('transferNext').disabled=false}});
$('transferPrev').addEventListener('click',()=>{if(step>1)showStep(step-1)});
f.addEventListener('submit',e=>{if(step!==5||!calculation||!fields.final.checked){e.preventDefault();error('برای ثبت نهایی، رسید و مبلغ نهایی را تأیید کن.');return}if(fields.amountToggle.checked){fields.override.value=overrideForServer()}else{fields.override.value='';fields.overrideReason.value=''}fields.percent.value=normal(fields.percent.value);fields.rate.value=rateForServer()});
if(edit){fields.company.value=edit.company_id;fields.type.value=edit.period_type;fields.start.value=edit.period_start;fields.end.value=edit.period_end;fields.percent.value=String(edit.transfer_percent??'');fields.rate.dataset.originalRate=String(edit.usd_rate_toman??'');fields.rate.value=groupedRate(String(Math.round(Number(edit.usd_rate_toman)||0)));fields.notes.value=String(edit.notes??'');if(Math.abs(Number(edit.site_usd_applied)-Number(edit.site_usd_calculated))>0.0000004){fields.amountToggle.checked=true;$('transferAmountEdit').hidden=false;fields.override.dataset.originalUsd=String(edit.site_usd_applied);fields.override.value=usd(edit.site_usd_applied);fields.overrideReason.value=edit.override_reason||''}}else{fields.type.value='weekly';fields.start.value=conf.defaultStart;fields.end.value=conf.defaultEnd;recomputeStart();}
showStep(1);help();checkOverlap();refreshRateWords();
})();
