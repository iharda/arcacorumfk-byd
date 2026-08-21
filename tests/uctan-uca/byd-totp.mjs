// TOTP kod uretici (RFC 6238, SHA1/6 hane/30 sn) -- Google2FA ile uyumlu.
// Testlerde yetkili paneline girmek icin kullanilir; uygulamada arka kapi YOK.
import crypto from 'node:crypto';

export function base32Coz(gizli) {
  const abc = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
  let bit = 0, deger = 0;
  const cikti = [];
  for (const ch of gizli.toUpperCase().replace(/=+$/, '')) {
    const i = abc.indexOf(ch);
    if (i < 0) continue;
    deger = (deger << 5) | i;
    bit += 5;
    if (bit >= 8) { cikti.push((deger >>> (bit - 8)) & 0xff); bit -= 8; }
  }
  return Buffer.from(cikti);
}

export function totp(gizli, zaman = Date.now()) {
  const sayac = Math.floor(zaman / 1000 / 30);
  const buf = Buffer.alloc(8);
  buf.writeUInt32BE(Math.floor(sayac / 2 ** 32), 0);
  buf.writeUInt32BE(sayac >>> 0, 4);
  const hmac = crypto.createHmac('sha1', base32Coz(gizli)).update(buf).digest();
  const ofs = hmac[hmac.length - 1] & 0x0f;
  const kod = ((hmac[ofs] & 0x7f) << 24 | hmac[ofs + 1] << 16 | hmac[ofs + 2] << 8 | hmac[ofs + 3]) % 1_000_000;
  return String(kod).padStart(6, '0');
}

if (import.meta.url === `file://${process.argv[1]}`) {
  const { readFileSync } = await import('node:fs');
  console.log(totp(readFileSync('/root/.byd-admin-totp', 'utf8').trim()));
}
