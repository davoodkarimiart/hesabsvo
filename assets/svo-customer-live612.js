(() => {
  'use strict';
  const form=document.querySelector('form.customer-filter-bar');
  const q=document.getElementById('customerLiveSearch');
  const company=document.getElementById('customerFilterCompany');
  const panel=document.getElementById('customerFilterPanel');
  const sort=document.getElementById('customerSort');
  const list=document.getElementById('customerDirectory');
  const status=document.getElementById('customerSearchStatus');
  if(!form||!q||!list||!company||!panel)return;
  let timer=null, seq=0, controller=null;
  const norm=v=>String(v??'').trim().toLocaleLowerCase('fa-IR').replace(/ي|ى/g,'ی').replace(/ك/g,'ک').replace(/\s+/g,' ');
  const initial=[...list.querySelectorAll('.customer-row')];
  const sortNodes=nodes=>{
    const mode=sort?.value||'az';
    if(mode==='default')return nodes;
    return nodes.sort((a,b)=>{
      const v=norm(a.dataset.customerName).localeCompare(norm(b.dataset.customerName),'fa',{sensitivity:'base'});
      return mode==='za'?-v:v;
    });
  };
  const showNodes=nodes=>{
    const frag=document.createDocumentFragment();
    for(const node of sortNodes(nodes))frag.append(node);
    list.replaceChildren(frag);
    if(status)status.textContent=`${nodes.length.toLocaleString('fa-IR')} مشتری پیدا شد`;
    if(!nodes.length){
      const empty=document.createElement('div');empty.className='empty-state compact';
      empty.textContent='مشتری پیدا نشد. نام، Username یا فیلتر شرکت و پنل را بررسی کن.';
      list.append(empty);
    }
  };
  const span=(cls,value)=>{const el=document.createElement('span');el.className=cls;el.textContent=value;return el};
  const build=row=>{
    const a=document.createElement('a');a.className='customer-row';a.href=`index.php?page=customers&id=${encodeURIComponent(row.id)}`;
    a.dataset.customerName=row.name;
    const main=document.createElement('div');main.className='customer-row-main';
    const b=document.createElement('b');b.textContent=row.name;main.append(b);
    const small=document.createElement('small');small.textContent=`${row.account_count} حساب • ${row.active?'فعال':'غیرفعال'}`+
      (row.companies?' • '+row.companies:'')+(row.panels?' / '+row.panels:'');main.append(small);
    if(row.usernames){const un=document.createElement('small');un.className='ltr-inline customer-usernames';un.textContent=row.usernames;main.append(un);}
    const numbers=document.createElement('div');numbers.className='customer-row-numbers';
    numbers.append(span('recv-text',`دریافتی ${row.received}`),span('pay-text',`پرداختی ${row.paid}`));
    a.append(main,numbers);return a;
  };
  const params=()=>{
    const p=new URLSearchParams();p.set('customer_query',q.value.trim());p.set('company_id',company.value||'0');p.set('panel_id',panel.value||'0');
    return p;
  };
  const search=async(fallback=false)=>{
    const id=++seq;
    if(controller)controller.abort();controller=new AbortController();
    const qs=params();if(status)status.textContent='در حال جست‌وجو…';
    try{
      const res=await fetch(`customer_search.php?${qs}`,{credentials:'same-origin',cache:'no-store',headers:{'Accept':'application/json'},signal:controller.signal});
      if(!res.ok)throw Error('server');
      const data=await res.json();if(!data.ok||!Array.isArray(data.rows))throw Error('invalid result');
      if(id!==seq)return;
      const url=new URL(location.href);url.searchParams.set('page','customers');url.searchParams.delete('id');
      for(const [k,v] of qs)if(v&&v!=='0')url.searchParams.set(k,v);else url.searchParams.delete(k);
      history.replaceState(null,'',url.pathname+url.search);
      showNodes(data.rows.map(build));
    }catch(err){
      if(err?.name==='AbortError'||id!==seq)return;
      if(status)status.textContent='اتصال جست‌وجو برقرار نشد؛ از فیلتر معمولی استفاده می‌شود.';
      if(fallback)HTMLFormElement.prototype.submit.call(form);
    }
  };
  const schedule=()=>{clearTimeout(timer);timer=setTimeout(search,260)};
  q.addEventListener('input',schedule);
  q.addEventListener('search',schedule);
  company.addEventListener('change',()=>{panel.value='0';setTimeout(search,0)});
  panel.addEventListener('change',search);
  sort?.addEventListener('change',()=>{
    const rows=[...list.querySelectorAll('.customer-row')];
    if(rows.length)showNodes(rows);
  });
  form.addEventListener('submit',e=>{e.preventDefault();clearTimeout(timer);search(true)});
  showNodes(initial);
  // Refresh initial data if opening the page with filters, so JS and non-JS use the same backend.
  if(q.value.trim()||Number(company.value)>0||Number(panel.value)>0)schedule();
})();
