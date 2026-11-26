const {spawn} = require('node:child_process');
const fs = require('node:fs');
const path = require('node:path');
const out = path.resolve(__dirname, '../.mobile-audit');
const profile = fs.mkdtempSync(path.join(out, 'finance-check-'));
let chrome, socket;
const pending = new Map(); let sequence = 0;
const delay = ms => new Promise(resolve => setTimeout(resolve, ms));
function send(method, params = {}) { const id = ++sequence; return new Promise((resolve, reject) => { const timer = setTimeout(() => {pending.delete(id);reject(new Error(method));}, 15000);pending.set(id,{resolve,reject,timer});socket.send(JSON.stringify({id,method,params})); }); }
(async () => {
  try {
    chrome = spawn('C:/Program Files/Google/Chrome/Application/chrome.exe',['--headless=new','--disable-gpu','--no-first-run','--no-default-browser-check','--remote-debugging-port=0',`--user-data-dir=${profile}`,'about:blank'],{windowsHide:true,stdio:'ignore'});
    const portFile = path.join(profile,'DevToolsActivePort');
    for(let i=0;i<100&&!fs.existsSync(portFile);i++)await delay(100);
    const port=fs.readFileSync(portFile,'utf8').split('\n')[0];
    const targets=await(await fetch(`http://127.0.0.1:${port}/json/list`)).json();
    socket=new WebSocket(targets.find(t=>t.type==='page').webSocketDebuggerUrl);
    await new Promise((resolve,reject)=>{socket.addEventListener('open',resolve,{once:true});socket.addEventListener('error',reject,{once:true});});
    socket.addEventListener('message',event=>{const m=JSON.parse(event.data);const call=pending.get(m.id);if(!call)return;clearTimeout(call.timer);pending.delete(m.id);m.error?call.reject(new Error(m.error.message)):call.resolve(m.result);});
    for(const width of [375,1440]) {
      await send('Emulation.setDeviceMetricsOverride',{width,height:1000,deviceScaleFactor:1,mobile:width<900});
      await send('Page.navigate',{url:'http://127.0.0.1:8123/.mobile-audit/finance-preview.html'});await delay(3000);
      const r=await send('Runtime.evaluate',{expression:`JSON.stringify({viewport:innerWidth,scroll:document.documentElement.scrollWidth,chartReady:typeof Chart==='function',charts:typeof Chart==='function'?Object.keys(Chart.instances).length:0,overflow:[...document.querySelectorAll('.finance-report *')].filter(e=>{const r=e.getBoundingClientRect();return r.right>innerWidth+1&&!e.closest('.erp-table-wrap')}).slice(0,10).map(e=>({tag:e.tagName,cls:e.className,width:Math.round(e.getBoundingClientRect().width)}))})`,returnByValue:true});
      console.log(width+': '+r.result.value);
      const shot=await send('Page.captureScreenshot',{format:'png'});fs.writeFileSync(path.join(out,`finance-${width}.png`),Buffer.from(shot.data,'base64'));
    }
  } finally {
    if(socket?.readyState===WebSocket.OPEN){try{await send('Browser.close');}catch{}socket.close();}
    chrome?.kill();
    await delay(700);
    if(profile.startsWith(out+path.sep))try{fs.rmSync(profile,{recursive:true,force:true});}catch{}
  }
})().catch(e=>{console.error(e.message);process.exitCode=1;});
