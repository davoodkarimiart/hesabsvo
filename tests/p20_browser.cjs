/* Real-clock Chromium CDP over debugging pipe, no dependency or package download. */
const {spawn}=require('node:child_process');
const [chrome,url]=process.argv.slice(2);
const p=spawn(chrome,['--headless','--no-sandbox','--disable-dev-shm-usage','--remote-debugging-pipe','--window-size=390,844','about:blank'],{stdio:['ignore','ignore','ignore','pipe','pipe']});
let id=0,buf='',pending=new Map();
p.stdio[4].on('data',chunk=>{buf+=chunk.toString();let at;while((at=buf.indexOf('\0'))>=0){let raw=buf.slice(0,at);buf=buf.slice(at+1);if(!raw)continue;let msg=JSON.parse(raw);let cb=pending.get(msg.id);if(cb){pending.delete(msg.id);msg.error?cb.reject(Error(JSON.stringify(msg.error))):cb.resolve(msg.result)}}});
function cmd(method,params={},sessionId){return new Promise((resolve,reject)=>{let n=++id;pending.set(n,{resolve,reject});p.stdio[3].write(JSON.stringify({id:n,method,params,...(sessionId?{sessionId}:{})})+'\0')})}
const sleep=ms=>new Promise(r=>setTimeout(r,ms));
const watchdog=setTimeout(()=>{console.error('CDP timeout');p.kill();process.exit(1)},22000);
(async()=>{try{
 const {targetId}=await cmd('Target.createTarget',{url:'about:blank'}),{sessionId}=await cmd('Target.attachToTarget',{targetId,flatten:true});
 await cmd('Page.enable',{},sessionId);await cmd('Runtime.enable',{},sessionId);await cmd('Page.navigate',{url},sessionId);
 for(let n=0;n<160;n++){const r=await cmd('Runtime.evaluate',{expression:"document.body?.dataset.p20 || ''",returnByValue:true},sessionId),v=r.result?.value||'';if(v==='PASS'){console.log('P20 browser PASS');return}if(v.startsWith('FAIL'))throw Error(v);await sleep(100)}
 const r=await cmd('Runtime.evaluate',{expression:"JSON.stringify({url:location.href,body:document.body?.outerHTML.slice(0,1600)})",returnByValue:true},sessionId);throw Error('no result '+r.result?.value);
}catch(e){console.error(String(e));process.exitCode=1}finally{clearTimeout(watchdog);p.kill()}})();
