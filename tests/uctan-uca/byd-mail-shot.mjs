// E-posta sablonunun goruntusu (yerel HTML). Sunucuya YAZMAZ.
import puppeteer from 'puppeteer-core';
import { readdirSync } from 'node:fs';
const K='/root/.cache/puppeteer/chrome';
const CHROME=`${K}/${readdirSync(K).sort().pop()}/chrome-linux64/chrome`;
const b=await puppeteer.launch({executablePath:CHROME,headless:'new',args:['--no-sandbox','--disable-dev-shm-usage']});
const p=await b.newPage();
await p.setViewport({width:700,height:900,deviceScaleFactor:2});
await p.goto('file:///tmp/byd-mail-onizleme.html',{waitUntil:'networkidle0'});
await p.screenshot({path:'/root/byd-mail-ornek.png',fullPage:true});
await b.close();
console.log('e-posta görüntüsü: /root/byd-mail-ornek.png');
