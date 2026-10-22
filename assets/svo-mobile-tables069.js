/* Soltan Hesab 0.6.9: presentation-only responsive table labels. */
(()=>{
  'use strict';
  const table=document.getElementById('reportDataTable');
  if(!table)return;
  const shell=table.closest('.report-fit');
  if(!shell)return;
  const heads=Array.from(table.tHead?.rows[table.tHead.rows.length-1]?.cells||[]);
  if(!heads.length)return;
  const labels=heads.map((head,i)=>{
    const text=(head.textContent||'').replace(/\s+/g,' ').trim();
    return text || (head.querySelector('input[type="checkbox"]')?'انتخاب گزارش':(i===0?'ردیف':'انتخاب'));
  });
  function labelRows(){
    for(const row of table.tBodies[0]?.rows||[]){
      Array.from(row.cells).forEach((cell,i)=>{
        if(!cell.dataset.svoLabel){
          cell.dataset.svoLabel=cell.querySelector('input[type="checkbox"]')?'انتخاب گزارش':(labels[i]||'مقدار');
        }
      });
    }
  }
  labelRows();
  shell.classList.add('svo-mobile-ready');
  // On the reports screen rows are server-rendered; preserve selection and DOM.
})();
