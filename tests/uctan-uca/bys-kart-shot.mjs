// Kart taslagini goruntule (yerel HTML dosyasindan). Sunucuya YAZMAZ.
import puppeteer from 'puppeteer-core';
import { readdirSync } from 'node:fs';
const K='/root/.cache/puppeteer/chrome';
const CHROME=`${K}/${readdirSync(K).sort().pop()}/chrome-linux64/chrome`;
const b=await puppeteer.launch({executablePath:CHROME,headless:'new',args:['--no-sandbox','--disable-dev-shm-usage']});
const p=await b.newPage();
// 90x130mm @96dpi = 340x491 px; 3x olcek
await p.setViewport({width:340,height:491,deviceScaleFactor:3});
await p.goto('file:///tmp/bys-kart-onizleme.html',{waitUntil:'networkidle0'});
await p.screenshot({path:'/root/bys-kart-taslak.png'});
await p.pdf({path:'/root/bys-kart-taslak.pdf',width:'90mm',height:'130mm',printBackground:true,margin:{top:0,right:0,bottom:0,left:0}});
await b.close();
console.log('kart taslağı üretildi');
