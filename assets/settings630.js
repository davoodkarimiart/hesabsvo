/* v0.6.30 — no anchor-scroll pretending to be tabs. */
(()=>{
 'use strict';
 const shell=document.querySelector('.s630-shell');if(!shell)return;
 const buttons=[...shell.querySelectorAll('[data-settings-tab]')];
 const panels={accounting:document.getElementById('s630Accounting'),security:document.getElementById('s630Security')};
 function selectTab(name,focus=false){
   if(!panels[name])name='accounting';
   for(const btn of buttons){const on=btn.dataset.settingsTab===name;btn.setAttribute('aria-selected',on?'true':'false');btn.tabIndex=on?0:-1;}
   for(const [key,panel] of Object.entries(panels)){if(panel)panel.hidden=key!==name;}
   if(focus)buttons.find(btn=>btn.dataset.settingsTab===name)?.focus({preventScroll:true});
   if(location.protocol==='http:'||location.protocol==='https:'){
     const url=new URL(location.href);url.searchParams.set('tab',name);
     history.replaceState(history.state,'',url.pathname+url.search);
   }
 }
 const requested=shell.dataset.initialTab==='security'?'security':'accounting';
 selectTab(requested,false);
 buttons.forEach((btn,i)=>{
   btn.addEventListener('click',()=>selectTab(btn.dataset.settingsTab));
   btn.addEventListener('keydown',e=>{if(e.key==='ArrowLeft'||e.key==='ArrowRight'){e.preventDefault();const next=buttons[(i+(e.key==='ArrowLeft'?1:-1)+buttons.length)%buttons.length];selectTab(next.dataset.settingsTab,true);}else if(e.key==='Home'||e.key==='End'){e.preventDefault();selectTab(buttons[e.key==='Home'?0:buttons.length-1].dataset.settingsTab,true);}});
 });
})();
