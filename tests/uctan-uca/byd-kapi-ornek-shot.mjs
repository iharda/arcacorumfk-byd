// Kapi ekraninin "izinli" ve "reddedildi" gorunumu -- TANITIM icin.
// Gercek dogrulama yapmaz, ekran yerlesimini gosterir. Sunucuya YAZMAZ.
import puppeteer from 'puppeteer-core';
import { readdirSync, readFileSync } from 'node:fs';
const K='/root/.cache/puppeteer/chrome';
const CHROME=`${K}/${readdirSync(K).sort().pop()}/chrome-linux64/chrome`;
const ALAN=process.env.BYD_ALAN || 'byd.ordolive.com', KOK=`https://${ALAN}`;
const foto = 'data:image/jpeg;base64,' + readFileSync('/tmp/byd-portre.jpg').toString('base64');

const b=await puppeteer.launch({executablePath:CHROME,headless:'new',
  args:['--no-sandbox','--disable-dev-shm-usage',`--host-resolver-rules=MAP ${ALAN} 127.0.0.1`,'--ignore-certificate-errors']});
const p=await b.newPage();
await p.setViewport({width:420,height:860,deviceScaleFactor:2});
await p.goto(`${KOK}/kapi`,{waitUntil:'networkidle2'});
await new Promise(r=>setTimeout(r,900));

for (const [ad, v] of [
  ['izinli', {izinli:true, etiket:'İzinli', mesaj:'Geçiş izinli.', zaman:'19:42:07',
    isim:'Şükrü Ağaoğlu', kurum:'Çorum Haber Ajansı', kartNo:'2026-K-0042'}],
  ['reddedildi', {izinli:false, etiket:'Askıda', mesaj:'Akreditasyon askıda.', zaman:'19:43:15',
    isim:'Şükrü Ağaoğlu', kurum:'Çorum Haber Ajansı', kartNo:'2026-K-0042'}],
]) {
  await p.evaluate((v, foto) => {
    document.querySelectorAll('[data-ekran]').forEach(e => { e.hidden = e.dataset.ekran !== 'sonuc'; });
    const k = document.querySelector('#sonuc');
    k.className = 'sonuc ' + (v.izinli ? 'sonuc--izinli' : 'sonuc--ret');
    document.querySelector('#sonuc-etiket').textContent = v.etiket;
    document.querySelector('#sonuc-mesaj').textContent = v.mesaj;
    document.querySelector('#sonuc-zaman').textContent = v.zaman;
    document.querySelector('#kisi').hidden = false;
    document.querySelector('#kisi-isim').textContent = v.isim;
    document.querySelector('#kisi-kurum').textContent = v.kurum;
    document.querySelector('#kisi-kartno').textContent = v.kartNo;
    const f = document.querySelector('#kisi-foto');
    f.src = foto; f.hidden = false;
    document.querySelector('#kapi-adi').textContent = 'Kuzey turnike 1';
  }, v, foto);
  await new Promise(r=>setTimeout(r,400));
  await p.screenshot({path:`/root/byd-kapi-${ad}.png`});
  console.log(`✅ /root/byd-kapi-${ad}.png`);
}
await b.close();
