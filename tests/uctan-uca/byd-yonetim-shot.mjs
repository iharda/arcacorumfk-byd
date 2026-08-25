// Yonetim panelinin ekranlarindan goruntu. Veri YAZMAZ.
import puppeteer from 'puppeteer-core';
import { readdirSync, readFileSync } from 'node:fs';
import { totp } from './byd-totp.mjs';
const K='/root/.cache/puppeteer/chrome';
const CHROME=`${K}/${readdirSync(K).sort().pop()}/chrome-linux64/chrome`;
const ALAN='byd.ordolive.com', KOK=`https://${ALAN}`;
const bekle=ms=>new Promise(r=>setTimeout(r,ms));
const b=await puppeteer.launch({executablePath:CHROME,headless:'new',
  args:['--no-sandbox','--disable-dev-shm-usage',`--host-resolver-rules=MAP ${ALAN} 127.0.0.1`,'--ignore-certificate-errors']});
const s=await b.newPage();
await s.setViewport({width:1500,height:1000});
await s.goto(`${KOK}/yonetim/login`,{waitUntil:'networkidle2'});
await s.type('#form\\.email','admin@byd.ordolive.com');
await s.type('#form\\.password',readFileSync('/root/.byd-admin-pass','utf8').trim());
await Promise.all([s.waitForNavigation({waitUntil:'networkidle2'}).catch(()=>{}),s.click('button[type="submit"]')]);
await bekle(1200);
const kutular=await s.$$('input[inputmode="numeric"]');
if(kutular.length>=6){await kutular[0].click();await s.keyboard.type(totp(readFileSync('/root/.byd-admin-totp','utf8').trim()),{delay:60});await bekle(900);
  await s.evaluate(()=>[...document.querySelectorAll('button')].find(b=>/Girişi doğrula/i.test(b.innerText))?.click());await bekle(3000);}

let hata=0;
for(const [yol,ad] of [['/yonetim','panel'],['/yonetim/basvurular','basvurular'],['/yonetim/kurumlar','kurumlar'],['/yonetim/akreditasyonlar','akreditasyonlar'],['/yonetim/duyurular','duyurular'],['/yonetim/antrenmanlar','antrenmanlar'],['/yonetim/bultenler','bultenler'],['/yonetim/kapilar','kapilar'],['/yonetim/gecis-kayitlari','gecis-kayitlari'],['/yonetim/denetim-kaydi','denetim-kaydi'],['/yonetim/ayarlar','ayarlar'],['/yonetim/kullanicilar','kullanicilar']]){
  const y=await s.goto(KOK+yol,{waitUntil:'networkidle2'});
  await bekle(700);
  const govde=await s.evaluate(()=>document.body.innerText);
  const iyi = y.status()===200 && !/Sunucu Hatası|Server Error/.test(govde);
  if(!iyi) hata++;
  console.log(`${iyi?'✅':'❌'} ${yol}  → HTTP ${y.status()}  ${govde.replace(/\s+/g,' ').slice(0,60)}`);
  await s.screenshot({path:`/root/byd-yonetim-${ad}.png`,fullPage:true});
}
await b.close();
console.log(hata?`\n${hata} ekran hatalı`:'\nTüm yönetim ekranları açılıyor');
process.exit(hata?1:0);
