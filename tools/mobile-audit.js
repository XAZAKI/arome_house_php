const {spawn, execFileSync} = require('node:child_process');
const fs = require('node:fs');
const path = require('node:path');
const root = path.resolve(__dirname, '..');
const out = path.join(root, '.mobile-audit');
const aboutOnly = process.argv.includes('--about');
fs.mkdirSync(out, {recursive:true});
let sequence = 0;
const pending = new Map();
let chrome, socket, fixture;
const delay = ms => new Promise(resolve => setTimeout(resolve, ms));
async function send(method, params = {}) {
  const id = ++sequence;
  return new Promise((resolve, reject) => {
    const timer = setTimeout(() => {pending.delete(id); reject(new Error(`CDP timeout: ${method}`));}, 20000);
    pending.set(id, {resolve, reject, timer});socket.send(JSON.stringify({id,method,params}));
  });
}
async function evaluate(expression) {
  const result = await send('Runtime.evaluate', {expression,returnByValue:true,awaitPromise:true});
  if(result.exceptionDetails)throw new Error(result.exceptionDetails.text);
  return result.result.value;
}
(async () => {
  const fixturePath = path.join(out, 'fixture.json');
  try {
    fixture = aboutOnly ? {product:0} : JSON.parse(execFileSync('C:/xampp/php/php.exe', [path.join(__dirname,'mobile-audit-fixture.php')], {cwd:root,windowsHide:true}).toString());
    if (!aboutOnly) fs.writeFileSync(fixturePath,JSON.stringify(fixture));
    const profile = fs.mkdtempSync(path.join(out,'chrome-profile-'));
    chrome = spawn('C:/Program Files/Google/Chrome/Application/chrome.exe', ['--headless=new','--disable-gpu','--no-first-run','--no-default-browser-check','--remote-debugging-port=0',`--user-data-dir=${profile}`,'about:blank'], {windowsHide:true,stdio:['ignore','ignore','ignore']});
    const portFile = path.join(profile,'DevToolsActivePort');
    for(let i=0; i<100 && !fs.existsSync(portFile); i++)await delay(100);
    const port=fs.readFileSync(portFile,'utf8').split('\n')[0];
    const targets=await (await fetch(`http://127.0.0.1:${port}/json/list`)).json();
    const page=targets.find(target=>target.type==='page');socket=new WebSocket(page.webSocketDebuggerUrl);
    await new Promise((resolve,reject)=>{socket.addEventListener('open',resolve,{once:true});socket.addEventListener('error',reject,{once:true});});
    socket.addEventListener('message',event=>{const message=JSON.parse(event.data);if(!message.id)return;const call=pending.get(message.id);if(!call)return;clearTimeout(call.timer);pending.delete(message.id);message.error?call.reject(new Error(message.error.message)):call.resolve(message.result);});
    await send('Page.enable');await send('Network.enable');
    await send('Network.setBlockedURLs',{urls:['*fonts.googleapis.com*','*fonts.gstatic.com*','*google.com/maps*']});
    const base='http://localhost/arome_house_php/';
    const cases=[['home','guest'],['catalog','guest'],[`product&id=${fixture.product}`,'guest'],['payment','guest'],['login','guest'],['register','guest'],['contact','guest'],['about','guest'],['news','guest'],['promo','guest'],['shipping-policy','guest'],['cart','user'],['checkout','user'],['account','user'],['orders','user'],['admin&tab=overview','admin'],['admin&tab=products&new=1','admin'],['admin&tab=payments','admin']];
    const results=[];
    for(const width of [320,375,414,768,1280]) {
      await send('Emulation.setDeviceMetricsOverride',{width,height:900,deviceScaleFactor:1,mobile:width<900});
      for(const [route,role] of (aboutOnly ? [['about','guest']] : cases)) {
        await send('Network.clearBrowserCookies');
        if(role!=='guest')await send('Network.setCookie',{name:'PHPSESSID',value:fixture.sessions[role==='admin'?1:0],url:base});
        await send('Page.navigate',{url:base+'?page='+route});
        await delay(400);
        for(let i=0;i<50;i++){if(await evaluate('document.readyState === "complete"'))break;await delay(100);}
        await evaluate('new Promise(resolve => requestAnimationFrame(() => requestAnimationFrame(resolve)))');
        const metrics=await evaluate(`JSON.stringify({viewport:innerWidth,width:document.documentElement.scrollWidth,body:document.body.scrollWidth,error:/Fatal error:|Warning:|Parse error:/.test(document.body.innerText),overflow:[...document.querySelectorAll('main *,header *,footer *')].filter(e=>{const r=e.getBoundingClientRect();return r.width>0&&(r.right>innerWidth+1||r.left < -1)&&getComputedStyle(e).position!=='fixed'&&!e.closest('[hidden],dialog:not([open]),.hero-track,.product-gallery-track,.home-products-track,.erp-table-wrap,.customer-order-tabs')}).slice(0,8).map(e=>({tag:e.tagName,cls:e.className,width:Math.round(e.getBoundingClientRect().width)}))})`);
        const data=JSON.parse(metrics);results.push({route,role,width,...data});
        if((width===375 || (aboutOnly && width===1280)) && ['about','checkout','account','catalog',`product&id=${fixture.product}`,'admin&tab=payments'].includes(route)) {
          const screenshot=await send('Page.captureScreenshot',{format:'png',captureBeyondViewport:false});
          fs.writeFileSync(path.join(out,route.replace(/[^a-z0-9-]/gi,'_')+'.png'),Buffer.from(screenshot.data,'base64'));
          if(aboutOnly) {
            const layout=await send('Page.getLayoutMetrics');
            const full=await send('Page.captureScreenshot',{format:'png',captureBeyondViewport:true,clip:{x:0,y:0,width:layout.cssContentSize.width,height:layout.cssContentSize.height,scale:1}});
            fs.writeFileSync(path.join(out,`about-${width}-full.png`),Buffer.from(full.data,'base64'));
          }
        }
      }
    }
    fs.writeFileSync(path.join(out,aboutOnly?'about-report.json':'report.json'),JSON.stringify(results,null,2));
    const failures=results.filter(r=>r.error||r.overflow.length);
    const horizontal=results.filter(r=>r.viewport+1<r.body||r.viewport+1<r.width);
    console.log(JSON.stringify({checks:results.length,horizontal,other:failures.filter(r=>!horizontal.includes(r))},null,2));
  } finally {
    if(socket?.readyState===WebSocket.OPEN){try{await send('Browser.close');}catch{}socket.close();}
    chrome?.kill();
    if(fixture?.users) {
      execFileSync('C:/xampp/php/php.exe',[path.join(__dirname,'mobile-audit-fixture.php'),'cleanup',fixturePath],{cwd:root,windowsHide:true});
      fs.unlinkSync(fixturePath);
    }
  }
})().catch(error=>{console.error(error.message);process.exitCode=1;});
