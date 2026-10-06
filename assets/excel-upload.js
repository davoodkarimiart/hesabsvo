(()=>{
  const msg=(form,text,type='bad')=>{
    let box=form.querySelector('.excel-client-status');
    if(!box){box=document.createElement('div');box.className='notice excel-client-status';form.prepend(box)}
    box.className='notice excel-client-status '+type;box.textContent=text;
  };
  async function parseFile(file){
    if(!window.XLSX) throw new Error('کتابخانه Excel در مرورگر لود نشده است. اتصال اینترنت را بررسی و صفحه را Refresh کن.');
    const buf=await file.arrayBuffer();
    const wb=XLSX.read(buf,{type:'array',cellDates:false,raw:true});
    const first=wb.SheetNames&&wb.SheetNames[0];
    if(!first)throw new Error('هیچ Sheet قابل خواندنی در فایل پیدا نشد.');
    return XLSX.utils.sheet_to_json(wb.Sheets[first],{header:1,defval:null,raw:true,blankrows:true});
  }
  document.addEventListener('submit',async e=>{
    const form=e.target.closest?.('form.excel-upload-form'); if(!form||form.dataset.excelReady==='1')return;
    const input=form.querySelector('input[type=file][data-excel-input],input[type=file][name=account_file],input[type=file][name=report_file]');
    const hidden=form.querySelector('input[data-excel-rows],input[name=parsed_excel_rows],input[name=parsed_report_rows]');
    if(!input||!input.files||!input.files[0]||!hidden)return;
    const file=input.files[0];
    const ext=(file.name.split('.').pop()||'').toLowerCase();
    if(!['xls','xlsx'].includes(ext))return;
    e.preventDefault();
    try{
      msg(form,'در حال خواندن فایل Excel…','ok');
      const rows=await parseFile(file);
      if(!Array.isArray(rows)||!rows.length)throw new Error('فایل Excel خالی است یا قابل خواندن نیست.');
      hidden.value=JSON.stringify(rows);
      form.dataset.excelReady='1';
      msg(form,`Excel خوانده شد (${rows.length.toLocaleString('fa-IR')} ردیف). در حال ارسال…`,'ok');
      form.submit();
    }catch(err){msg(form,err&&err.message?err.message:'خواندن فایل Excel ناموفق بود.','bad')}
  },true);
})();
