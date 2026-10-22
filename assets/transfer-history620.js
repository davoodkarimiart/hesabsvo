/* v0.6.21 – compact history table with top-layer actions / receipts. */
(()=>{'use strict';
function init(){
 const b=document.getElementById('transferAdvancedToggle'),panel=document.getElementById('transferAdvancedPanel');
 if(b&&panel)b.addEventListener('click',()=>{panel.hidden=!panel.hidden;b.setAttribute('aria-expanded',String(!panel.hidden))});
 document.getElementById('transferClearDates')?.addEventListener('click',()=>{for(const name of ['historyFrom','historyTo']){const input=document.getElementById(name);input.value='';input.dispatchEvent(new Event('change',{bubbles:true}))}window.TransferCalendar620?.refresh()});
 const dlg=document.getElementById('transferOperationsDialog');
 if(dlg){
  document.querySelectorAll('.transfer-action-trigger').forEach(btn=>btn.addEventListener('click',()=>{
   const id=btn.dataset.id||'',code=btn.dataset.code||'',company=btn.dataset.company||'',isFinal=btn.dataset.status==='final';
   document.getElementById('transferActionHeading').textContent='عملیات '+code;
   document.getElementById('transferActionCompany').textContent=company;
   document.getElementById('transferActionView').href='index.php?page=transfers&tab=list&view='+encodeURIComponent(id);
   const edit=document.getElementById('transferActionEdit');edit.hidden=!isFinal;
   if(isFinal)edit.href='index.php?page=transfers&tab=new&edit='+encodeURIComponent(id);
   document.getElementById('transferActionVoidSection').hidden=!isFinal;
   document.getElementById('transferActionVoidId').value=id;
   dlg.showModal();
  }));
  document.getElementById('transferActionsClose')?.addEventListener('click',()=>dlg.close());
  dlg.addEventListener('click',e=>{if(e.target===dlg)dlg.close()});
 }
 const receipt=document.getElementById('transferReceiptDialog');
 if(receipt){receipt.showModal();receipt.addEventListener('click',e=>{if(e.target===receipt)receipt.close()})}
}
if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',init);else init();
})();
