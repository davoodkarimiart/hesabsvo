/* SoltanHesab v0.6.17. Shared PNG encoder for financial tables.
   Text and cell edges are kept lossless; only a duplicate canvas is gently color-quantized
   when this produces a smaller PNG. No automatic scaling or lossy JPEG. */
(function(){'use strict';
  function blobOf(canvas,type='image/png',quality){
    return new Promise((resolve,reject)=>canvas.toBlob(b=>b?resolve(b):reject(Error('خروجی تصویر ساخته نشد.')),type,quality));
  }
  async function quantizedPng(canvas,step){
    const w=canvas.width,h=canvas.height;
    const duplicate=document.createElement('canvas');duplicate.width=w;duplicate.height=h;
    const ctx=duplicate.getContext('2d',{willReadFrequently:true});
    if(!ctx)throw Error('مرورگر حافظه کافی برای بهینه‌سازی تصویر ندارد.');
    ctx.drawImage(canvas,0,0);
    // Process narrow strips, not a giant pixel buffer. This also works with tall reports.
    const strip=192;
    for(let y=0;y<h;y+=strip){
      const sh=Math.min(strip,h-y),img=ctx.getImageData(0,y,w,sh),pix=img.data;
      for(let i=0;i<pix.length;i+=4){
        if(pix[i+3]!==255)continue;
        pix[i]=Math.min(255,Math.round(pix[i]/step)*step);
        pix[i+1]=Math.min(255,Math.round(pix[i+1]/step)*step);
        pix[i+2]=Math.min(255,Math.round(pix[i+2]/step)*step);
      }
      ctx.putImageData(img,0,y);
    }
    return blobOf(duplicate);
  }
  async function encode(canvas,opts={}){
    if(!canvas||!canvas.width||!canvas.height)throw Error('بوم تصویر معتبر نیست.');
    const original=await blobOf(canvas);
    if(opts.optimize===false||original.size<95000||canvas.width*canvas.height>16500000)return original;
    try{
      const candidate=await quantizedPng(canvas,8);
      return candidate.size<original.size*0.97?candidate:original;
    }catch(e){console.warn('PNG optimization skipped:',e);return original}
  }
  function file(blob,name){return new File([blob],name||'accounting-report.png',{type:'image/png'});}
  async function download(fileOrBlob,name){
    const url=URL.createObjectURL(fileOrBlob),a=document.createElement('a');
    a.href=url;a.download=name||fileOrBlob.name||'accounting-report.png';a.style.display='none';
    document.body.append(a);a.click();a.remove();setTimeout(()=>URL.revokeObjectURL(url),60000);
  }
  function shareReady(file,title,onUnsupported){
    try{
      if(navigator.share&&(!navigator.canShare||navigator.canShare({files:[file]}))){
        navigator.share({files:[file],title:title||'تصویر گزارش'}).catch(e=>{
          if(e?.name!=='AbortError'&&typeof onUnsupported==='function')onUnsupported(e);
        });return true;
      }
    }catch(e){console.warn('Native share unavailable:',e)}
    if(typeof onUnsupported==='function')onUnsupported();
    return false;
  }

  async function copyImage(produce,name='report.png') {
    const make=Promise.resolve().then(produce).then(blob=>blob instanceof Blob?blob:new Blob([blob],{type:'image/png'}));
    if(!navigator.clipboard?.write || typeof ClipboardItem==='undefined'){
      const blob=await make;await download(blob,name);
      throw new Error('مرورگر اجازه کپی مستقیم عکس را نمی‌دهد. PNG دانلود شد؛ می‌توانی آن را پیوست کنی.');
    }
    try{
      // Immediately call write in the click gesture, even when canvas encoding is async.
      const entry=new ClipboardItem({'image/png':make});
      await navigator.clipboard.write([entry]);
      return true;
    }catch(error){
      const blob=await make;await download(blob,name);
      throw new Error('کپی در کلیپ‌بورد انجام نشد. عکس دانلود شد. روی HTTPS از مرورگر به Clipboard دسترسی بده.');
    }
  }
  window.SvoImage617=Object.freeze({encode,file,download,shareReady,copyImage});
})();
