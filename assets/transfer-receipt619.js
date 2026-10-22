/* v0.6.25: polished centered shareable receipt; financial semantics unchanged. */
(()=>{'use strict';
const root=document.getElementById('savedTransferReceipt');if(!root)return;
const $=id=>document.getElementById(id);let filePromise=null,imageURL='';
const lines=()=>[...root.querySelectorAll('.transfer-receipt-line')].map(el=>({label:el.querySelector('span')?.textContent?.trim()||'',value:el.querySelector('b')?.textContent?.trim()||'',total:el.classList.contains('transfer-receipt-total'),member:el.classList.contains('transfer-member-win')}));
const days=()=>[...root.querySelectorAll('.transfer-daily-breakdown tbody tr')].map(tr=>({rowNo:tr.cells[0]?.textContent.trim()||'',fa:tr.cells[1]?.querySelector('span')?.textContent.trim()||'',iso:tr.cells[1]?.querySelector('small')?.textContent.trim()||'',amount:tr.cells[2]?.textContent.trim()||'',negative:tr.lastElementChild?.classList.contains('negative'),positive:tr.lastElementChild?.classList.contains('positive')}));
const numcolor=str=>/^\s*[−-]/.test(str)?'#c23842':/^\s*\+/.test(str)?'#236cc4':'#1d2b45';
function buildReceipt(){
 if(filePromise)return filePromise;
 filePromise=(async()=>{
  if(document.fonts)await document.fonts.ready;
  const rows=lines(),details=days(),code=root.querySelector('.transfer-receipt-code')?.textContent?.trim()||'TRANSFER';
  const company=rows.find(r=>r.label==='طرف تسویه')?.value||'';
  const total=rows.find(r=>r.total),fields=rows.filter(r=>r.label!=='طرف تسویه'&&!r.total);
  const dailySum=root.querySelector('.transfer-daily-breakdown tfoot th:last-child')?.textContent.trim()||rows.find(r=>r.member)?.value||'—';
  const width=730,pad=28,gap=9,cardH=45,headH=143,tableTitleH=37,tableHeadH=31,dayH=34,footerH=45;
  const pairs=Math.ceil(fields.length/2),bodyH=pairs*(cardH+gap),totalH=74;
  const tableH=details.length?tableTitleH+tableHeadH+details.length*dayH+39:0;
  const height=headH+bodyH+totalH+tableH+footerH+8;
  const scale=Math.min(2,15500/Math.max(height,width));
  const canvas=document.createElement('canvas');canvas.width=Math.round(width*scale);canvas.height=Math.round(height*scale);
  const x=canvas.getContext('2d');if(!x)throw Error('ساخت PNG در این مرورگر پشتیبانی نمی‌شود.');x.scale(scale,scale);
  const font=(size,weight=700)=>`${weight} ${size}px Vazirmatn,Tahoma,Arial,sans-serif`;
  function rect(left,top,w,h,r,fill){x.beginPath();if(x.roundRect)x.roundRect(left,top,w,h,r);else{x.moveTo(left+r,top);x.arcTo(left+w,top,left+w,top+h,r);x.arcTo(left+w,top+h,left,top+h,r);x.arcTo(left,top,left+w,top,r);x.arcTo(left,top,left+w,top,r)}x.fillStyle=fill;x.fill()}
  function txt(value,xx,yy,max,size=15,color='#1f3652',weight=700,align='right',direction='rtl'){
    x.save();x.textBaseline='middle';x.textAlign=align;x.direction=direction;x.fillStyle=color;x.font=font(size,weight);x.fillText(String(value),xx,yy,Math.max(12,max));x.restore();
  }
  function line(x1,y1,x2,y2,color='#e6edf4',width=1){x.beginPath();x.moveTo(x1,y1);x.lineTo(x2,y2);x.lineWidth=width;x.strokeStyle=color;x.stroke()}
  // Paper, consistent margins and a restrained navy-and-teal stationery palette.
  x.fillStyle='#f1f5fa';x.fillRect(0,0,width,height);
  rect(13,10,width-26,height-20,17,'#ffffff');
  const header=x.createLinearGradient(13,0,width-13,0);
  header.addColorStop(0,'#163452');header.addColorStop(.56,'#245575');header.addColorStop(1,'#287080');
  rect(13,10,width-26,119,15,header);
  rect(width/2-30,24,60,3,2,'#e9bc79');
  txt('رسید تسویه ترانسفر',width/2,53,width-2*pad,25,'#fff',900,'center');
  txt(company,width/2,84,width-2*pad,17,'#eaf6fb',800,'center');
  rect(width/2-88,99,176,23,11,'#557a95');
  txt(code,width/2,111,164,11,'#ffe1b7',800,'center','ltr');
  let y=headH;const cardW=(width-2*pad-gap)/2;
  fields.forEach((item,i)=>{
    const col=i%2,yy=y+Math.floor(i/2)*(cardH+gap),left=col?pad:pad+cardW+gap;
    rect(left,yy,cardW,cardH,9,'#f7f9fc');
    txt(item.label,left+cardW-12,yy+12,cardW-24,10,'#78899c',700);
    const val=item.label==='بازه میلادی'?item.value.replace(/\s+تا\s+/,' → '):item.value;
    const color=item.member?numcolor(item.value):'#203b59';
    const isLatin=/\d/.test(val)||val.includes('$');
    txt(val,left+cardW-12,yy+31,cardW-24,item.member?16:13,color,900,'right',isLatin?'ltr':'rtl');
  });
  y+=bodyH+4;
  if(total){
    rect(pad,y,width-2*pad,totalH-11,11,'#e5f4ed');
    rect(width-pad-5,y+12,3,totalH-35,2,'#2a9371');
    txt(total.label,width-pad-17,y+19,width-2*pad-34,12,'#3f7964',800);
    txt(total.value.replace(/^\s*[-−]/,''),width-pad-17,y+44,width-2*pad-34,22,'#145d46',900);
  }
  y+=totalH;
  if(details.length){
    txt('ریز روزانه Member Win',width-pad,y+16,width-2*pad,14,'#244460',900);
    line(pad,y+34,width-pad,y+34,'#e0eaf2');y+=tableTitleH;
    rect(pad,y,width-2*pad,tableHeadH,5,'#e7f0f9');
    txt('ردیف',width-pad-13,y+tableHeadH/2,65,11,'#355777',900);
    txt('تاریخ شمسی / میلادی',width-pad-100,y+tableHeadH/2,310,11,'#355777',900);
    txt('Member Win ($)',pad+13,y+tableHeadH/2,210,11,'#355777',900,'left','ltr');
    y+=tableHeadH;
    details.forEach((d,i)=>{
      if(i%2===0)rect(pad,y,width-2*pad,dayH,2,'#f8fafc');
      txt(d.rowNo||String(i+1),width-pad-15,y+dayH/2,60,11,'#617690',800);
      txt(d.fa||'—',width-pad-95,y+dayH/2-7,295,11,'#264561',800);
      txt(d.iso||'',width-pad-95,y+dayH/2+8,295,9,'#889aab',700,'right','ltr');
      txt(d.amount||'—',pad+13,y+dayH/2,220,12,d.negative?'#ba3445':d.positive?'#206eb7':'#8090a1',900,'left',/[0-9$]/.test(d.amount||'')?'ltr':'rtl');
      y+=dayH;
    });
    rect(pad,y,width-2*pad,36,7,'#dfeaf4');
    txt('جمع دوره',width-pad-16,y+18,260,13,'#264765',900);
    txt(dailySum,pad+13,y+18,240,14,numcolor(dailySum),900,'left','ltr');
    y+=39;
  }
  txt('مبنای رسید: گزارش‌های نهایی ثبت‌شده در سیستم',width/2,height-28,width-2*pad,11,'#8997aa',700,'center');
  const blob=await new Promise((resolve,reject)=>canvas.toBlob(b=>b?resolve(b):reject(Error('PNG ساخته نشد.')),'image/png'));
  const safeCode=code.replace(/[^A-Za-z0-9_-]/g,'').slice(0,70)||'transfer';
  return new File([blob],`transfer-${safeCode}.png`,{type:'image/png'});
 })().catch(error=>{filePromise=null;throw error});return filePromise;
}
async function download(){const file=await buildReceipt();if(imageURL)URL.revokeObjectURL(imageURL);imageURL=URL.createObjectURL(file);const a=document.createElement('a');a.href=imageURL;a.download=file.name;document.body.append(a);a.click();a.remove()}
async function share(){const file=await buildReceipt();if(navigator.share&&(!navigator.canShare||navigator.canShare({files:[file]}))){await navigator.share({files:[file],title:'رسید تسویه ترانسفر'});return}await download();alert('اشتراک مستقیم در این مرورگر موجود نیست؛ PNG دانلود شد.')}
async function copy(){try{await SvoImage617.copyImage(()=>buildReceipt(),'transfer-receipt.png');alert('تصویر رسید کپی شد؛ اکنون می‌توانی Ctrl+V بزنی.')}catch(e){alert(e.message)}}
async function printImage(){const file=await buildReceipt(),url=URL.createObjectURL(file);const frame=document.createElement('iframe');frame.style.cssText='position:fixed;width:1px;height:1px;left:-9999px;top:-9999px;border:0';document.body.append(frame);
const doc=frame.contentDocument;doc.open();doc.write('<!doctype html><html><head><meta charset="utf-8"><title>رسید ترانسفر</title><style>@page{size:A4 portrait;margin:6mm}html,body{margin:0}img{display:block;width:100%;height:auto;max-height:100vh;object-fit:contain}</style></head><body><img src="'+url+'"></body></html>');doc.close();const im=doc.querySelector('img');await new Promise(resolve=>{if(im.complete)return resolve();im.onload=resolve;im.onerror=resolve});frame.contentWindow.focus();frame.contentWindow.print();const cleanup=()=>{frame.remove();URL.revokeObjectURL(url)};frame.contentWindow.addEventListener('afterprint',cleanup,{once:true});setTimeout(cleanup,30000)}
$('transferReceiptImage')?.addEventListener('click',()=>download().catch(e=>alert(e.message)));
$('transferReceiptShare')?.addEventListener('click',()=>share().catch(e=>{if(e.name!=='AbortError')alert(e.message)}));
$('transferReceiptCopy')?.addEventListener('click',copy);
$('transferReceiptPrint')?.addEventListener('click',()=>printImage().catch(e=>alert(e.message)));
})();
