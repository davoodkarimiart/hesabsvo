/* SoltanHesab v0.6.16 • Mother Excel: one compact combined PNG + generic native share */
(()=>{'use strict';
 const $=id=>document.getElementById(id),dialog=$('cumulativeMotherDialog');if(!dialog)return;
 let selectedKey='',file=null,objectURL='',pending=false,err='';
 const status=$('mother614Status'),gallery=$('mother615Pages'),shareBtn=dialog.querySelector('.svo-mother-share-btn');
 const selected=()=>[...document.querySelectorAll('#reportDataTable tbody input[name="ids[]"]:checked')].map(n=>String(n.value));
 const text=(message,bad=false)=>{status.textContent=message;status.classList.toggle('mother-error',bad)};
 const query=(endpoint,ids)=>{const url=new URL(endpoint,document.baseURI);ids.forEach(v=>url.searchParams.append('ids[]',v));return url.toString()};
 const clean=()=>{if(objectURL)URL.revokeObjectURL(objectURL);objectURL='';file=null;gallery.replaceChildren();shareBtn.disabled=true};
 const fmt=(n)=>Number(n||0).toLocaleString('en-US',{maximumFractionDigits:2});
 function drawText(ctx,s,x,y,max,font=22,color='#111827',weight=800,align='center'){ctx.direction='rtl';ctx.textAlign=align;ctx.textBaseline='middle';ctx.font=`${weight} ${font}px Vazirmatn,Tahoma,Arial,sans-serif`;ctx.fillStyle=color;ctx.fillText(String(s),x,y,max)}
 function rect(ctx,x,y,w,h,fill='#fff',stroke='#d0d8e5',lw=1.2,r=16){ctx.beginPath();ctx.moveTo(x+r,y);ctx.arcTo(x+w,y,x+w,y+h,r);ctx.arcTo(x+w,y+h,x,y+h,r);ctx.arcTo(x,y+h,x,y,r);ctx.arcTo(x,y,x+w,y,r);ctx.closePath();ctx.fillStyle=fill;ctx.fill();ctx.lineWidth=lw;ctx.strokeStyle=stroke;ctx.stroke()}
 function minifyTitle(title){return String(title||'').replace(/\s*\|\s*#/,' | #')}
 async function buildCombinedImage(data){
  const sections=(data.sections||[]).map(section=>({
   ...section,
   title:minifyTitle(section.title),
   rows:Array.isArray(section.rows)?section.rows:[]
  }));
  if(!sections.length)throw Error('داده‌ای برای تصویر مادر وجود ندارد.');
  const totalRows=sections.reduce((s,sec)=>s+sec.rows.length,0);
  if(totalRows>250)throw Error('ردیف‌های فایل مادر زیاد است؛ برای خروجی کامل، اکسل را دانلود کن.');
  const colCount=sections.length<=2?2:(sections.length<=6?2:3);
  const cardW=colCount===3?500:560,gap=24,pad=24,headH=92,rowH=40,tableHeadH=40,footH=46,pageTop=134,pageBottom=34;
  const cards=sections.map(sec=>({
   sec,
   height:headH+tableHeadH+Math.max(1,sec.rows.length)*rowH+footH
  }));
  const colHeights=new Array(colCount).fill(0),placements=[];
  cards.forEach(card=>{let idx=0;for(let i=1;i<colCount;i++)if(colHeights[i]<colHeights[idx])idx=i;placements.push({col:idx,y:colHeights[idx],card});colHeights[idx]+=card.height+gap});
  const contentH=Math.max(...colHeights,0);
  const w=pad*2+colCount*cardW+gap*(colCount-1),h=pageTop+contentH+pageBottom;
  const scale=Math.min(1.55,12000/Math.max(w,h),Math.sqrt(22000000/(w*h)));
  const canvas=document.createElement('canvas');canvas.width=Math.round(w*scale);canvas.height=Math.round(h*scale);
  const ctx=canvas.getContext('2d');if(!ctx)throw Error('مرورگر امکان تولید تصویر ندارد.');ctx.setTransform(scale,0,0,scale,0,0);
  ctx.fillStyle='#f8fafc';ctx.fillRect(0,0,w,h);
  drawText(ctx,'اکسل مادر ترکیبی • سلطان حساب',w/2,36,w-40,34,'#101828',900);
  drawText(ctx,`${sections.length} پنل انتخاب شده • ${totalRows} ردیف`,w/2,74,w-40,18,'#667085',700);
  drawText(ctx,'یک تصویر واحد و جمع‌وجور برای ذخیره یا اشتراک',w/2,104,w-40,17,'#667085',700);
  const widths=[245,135,180];
  const drawCell=(x,y,cw,ch,fill,text,color='#182230',font=15,weight=800)=>{rect(ctx,x,y,cw,ch,fill,'#c9d3e0',1,0);drawText(ctx,text,x+cw/2,y+ch/2,cw-12,font,color,weight)};
  placements.forEach(({col,y,card})=>{
    const x=pad+col*(cardW+gap), top=pageTop+y, sec=card.sec;
    rect(ctx,x,top,cardW,card.height,'#ffffff','#d7dfeb',1.25,18);
    rect(ctx,x,top,cardW,50,'#35436b','#35436b',1,18);
    drawText(ctx,sec.title,x+cardW/2,top+25,cardW-28,19,'#fff',900);
    drawText(ctx,'Username / Turnover / Member Win',x+cardW/2,top+70,cardW-28,14,'#667085',700);
    let cy=top+headH;let cx=x;
    ['Username','Turnover','Member Win'].forEach((label,i)=>{drawCell(cx,cy,widths[i],tableHeadH,'#dbe8f4',label,'#1f2937',16,900);cx+=widths[i]});
    const rows=sec.rows.length?sec.rows:[{username:'—',turnover:'',member_win:''}];
    rows.forEach((row,idx)=>{cy=top+headH+tableHeadH+idx*rowH;cx=x;const vals=[row.username||'—',fmt(row.turnover),fmt(row.member_win)];for(let i=0;i<3;i++){const mv=Number(row.member_win)||0;const color=i===2&&mv<0?'#b42318':i===2&&mv>0?'#1557ac':'#182230';drawCell(cx,cy,widths[i],rowH,idx%2===0?'#fff':'#f8fafc',vals[i],color,i===0?14:15,i===2?900:800);cx+=widths[i]}});
    cy=top+headH+tableHeadH+rows.length*rowH;drawCell(x,cy,widths[0],footH,'#d4eee1','Grand Total','#182230',15,900);drawCell(x+widths[0],cy,widths[1],footH,'#d4eee1',fmt(sec.turnover_total),'#182230',15,900);drawCell(x+widths[0]+widths[1],cy,widths[2],footH,'#d4eee1',fmt(sec.member_win_total),Number(sec.member_win_total)<0?'#b42318':'#1557ac',15,900);
  });
  const blob=await new Promise((resolve,reject)=>canvas.toBlob(v=>v?resolve(v):reject(Error('PNG ساخته نشد.')),'image/png'));
  return new File([blob],'soltan-mother-combined.png',{type:'image/png'});
 }
 async function prepare(ids,key){
  pending=true;clean();text('فایل‌های مادر در حال خواندن و تبدیل به یک تصویر واحد هستند…');
  try{
   const res=await fetch(query('cumulative_mother_preview.php',ids),{credentials:'same-origin',cache:'no-store'});let data;
   try{data=await res.json()}catch(e){throw Error('پاسخ سرور برای اکسل مادر معتبر نیست.');}
   if(!res.ok||!data.ok)throw Error(data.error||'دریافت فایل مادر ناموفق بود.');
   if(Number(data.selected_count)!==ids.length)throw Error('تعداد فایل‌های دریافتی با انتخاب شما سازگار نیست.');
   if(document.fonts)await document.fonts.ready;
   if(selected().join(',')!==key)throw Error('انتخاب گزارش‌ها تغییر کرده است. پنجره را دوباره باز کن.');
   file=await buildCombinedImage(data);
   objectURL=URL.createObjectURL(file);
   const card=document.createElement('div');card.className='svo-mother-page';
   const img=document.createElement('img');img.src=objectURL;img.alt='تصویر ترکیبی فایل‌های مادر';img.loading='lazy';
   const a=document.createElement('a');a.className='btn ghost';a.href=objectURL;a.download=file.name;a.textContent='ذخیره PNG';
   card.append(img,a);gallery.append(card);
   shareBtn.disabled=false;err='';text('تصویر واحد آماده است. برای ارسال مستقیم «اشتراک» را بزن.');
  }catch(e){err=e.message||'ساخت تصویر انجام نشد.';clean();text(err,true)}finally{pending=false}
 }
 window.svoMotherOpen=function(){const ids=selected();if(!ids.length){(window.appAlert||alert)('حداقل یک گزارش انتخاب کن.');return false}const key=ids.join(',');if(key!==selectedKey){selectedKey=key;clean();err=''}if(!dialog.open)dialog.showModal();if(!file&&!pending)prepare(ids,key);return false};
 window.svoMotherExcel=function(){const ids=selected();if(!ids.length){text('گزارشی انتخاب نشده.',true);return false}const a=document.createElement('a');a.href=query('cumulative_excel.php',ids);document.body.append(a);a.click();a.remove();text('درخواست دانلود XLSX ارسال شد.');return false};
 window.svoMotherImage=function(){if(objectURL)gallery.scrollIntoView({block:'nearest',behavior:'smooth'});else text(err||'تصویر در حال آماده‌شدن است…',!!err);return false};
 window.svoMotherShare=function(){if(!file){text(err||'ابتدا منتظر آماده‌شدن تصویر بمان.',!!err);return false}try{if(navigator.share&&(!navigator.canShare||navigator.canShare({files:[file]}))){navigator.share({files:[file],title:'اکسل مادر ترکیبی سلطان حساب'}).catch(e=>{if(e?.name!=='AbortError')text('اشتراک مستقیم ممکن نشد. از دکمه ذخیره PNG استفاده کن.',true)});return false}}catch(e){}text('این مرورگر اشتراک فایل را پشتیبانی نمی‌کند. از دکمه ذخیره PNG استفاده کن.',true);return false};
})();
