#!/usr/bin/env python3
"""
BYD uctan uca testleri icin GERCEKCI ornek belgeler uretir.

Her belge, gercek bir resmi evrakla karistirilmasin diye capraz
"ORNEK - TEST BELGESI" filigrani tasir ve uydurma kurum/kisi bilgisi kullanir.
Amac: yukleme, onizleme, magic byte ve boyut yollarini gercek dosyalarla sinamak.

python3 /root/byd-belge-uret.py
"""
from PIL import Image, ImageDraw, ImageFont
from pathlib import Path

HEDEF = Path('/root/byd-test-dosyalari')
HEDEF.mkdir(exist_ok=True)

DEJAVU = '/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf'
DEJAVU_B = '/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf'
KIRMIZI = (193, 17, 25)
KOYU = (22, 24, 29)
GRI = (110, 116, 128)

def f(boy, kalin=False):
    return ImageFont.truetype(DEJAVU_B if kalin else DEJAVU, boy)

def filigran(img, metin='ÖRNEK · TEST BELGESİ'):
    """Capraz, yari saydam uyari — belge gercek sanilmasin."""
    kat = Image.new('RGBA', img.size, (255, 255, 255, 0))
    d = ImageDraw.Draw(kat)
    yazi = f(int(img.width / 13), True)
    kutu = d.textbbox((0, 0), metin, font=yazi)
    t = Image.new('RGBA', (kutu[2] - kutu[0] + 40, kutu[3] - kutu[1] + 40), (255, 255, 255, 0))
    ImageDraw.Draw(t).text((20, 20 - kutu[1]), metin, font=yazi, fill=(193, 17, 25, 70))
    t = t.rotate(30, expand=True, resample=Image.BICUBIC)
    for sy in range(-t.height, img.height + t.height, int(t.height * 1.25)):
        kat.alpha_composite(t, (int((img.width - t.width) / 2), sy))
    return Image.alpha_composite(img.convert('RGBA'), kat).convert('RGB')

def a4(baslik_bandi=True):
    img = Image.new('RGB', (1240, 1754), (252, 252, 252))   # A4 @150dpi
    d = ImageDraw.Draw(img)
    d.rectangle([40, 40, 1200, 1714], outline=(214, 218, 224), width=2)
    if baslik_bandi:
        d.rectangle([40, 40, 1200, 170], fill=KOYU)
    return img, d

def satirlar(d, x, y, ciftler, adim=46, etiket_gen=330):
    for etiket, deger in ciftler:
        d.text((x, y), etiket, font=f(21), fill=GRI)
        d.text((x + etiket_gen, y), deger, font=f(22, True), fill=KOYU)
        y += adim
    return y

# ─────────── 1) Vergi levhası ───────────
img, d = a4()
d.text((70, 78), 'GELİR İDARESİ BAŞKANLIĞI', font=f(28, True), fill=(255, 255, 255))
d.text((70, 120), 'VERGİ LEVHASI', font=f(22), fill=(210, 214, 220))
d.text((70, 220), '2026 YILI', font=f(20), fill=GRI)
y = satirlar(d, 70, 280, [
    ('Vergi kimlik numarası', '4820561973'),
    ('Ticaret unvanı', 'Kızılırmak Medya ve Yayıncılık Ltd. Şti.'),
    ('Vergi dairesi', 'Çorum Vergi Dairesi Müdürlüğü'),
    ('İş yeri adresi', 'Gazi Caddesi No: 48/3, Merkez / ÇORUM'),
    ('Faaliyet konusu', 'Gazete ve süreli yayın faaliyetleri'),
    ('İşe başlama tarihi', '14.03.2019'),
    ('Beyan edilen matrah', '3.482.900,00 TL'),
    ('Tahakkuk eden vergi', '870.725,00 TL'),
])
d.line([70, y + 30, 1170, y + 30], fill=(214, 218, 224), width=2)
d.text((70, y + 60), 'Bu belge test amacıyla üretilmiştir; hukuki geçerliliği yoktur.', font=f(19), fill=GRI)
d.text((70, 1620), 'Belge no: TEST-VL-2026-0481', font=f(18), fill=GRI)
_vl = filigran(img)
_vl.save(HEDEF / 'vergi-levhasi.pdf', resolution=150.0)
_vl.save(HEDEF / 'vergi-levhasi.jpg', quality=85)   # eski testler JPG istiyor

# ─────────── 2) Ticaret sicil gazetesi ───────────
img, d = a4(baslik_bandi=False)
d.rectangle([40, 40, 1200, 150], fill=(245, 246, 248))
d.text((70, 62), 'TÜRKİYE TİCARET SİCİLİ GAZETESİ', font=f(26, True), fill=KOYU)
d.text((70, 104), 'Sayı: 11284  ·  Tarih: 06.02.2026  ·  Sayfa: 37', font=f(19), fill=GRI)
d.line([70, 200, 1170, 200], fill=KIRMIZI, width=3)
d.text((70, 230), 'ÇORUM TİCARET SİCİLİ MÜDÜRLÜĞÜ', font=f(21, True), fill=KIRMIZI)
y = satirlar(d, 70, 290, [
    ('Sicil numarası', '17492'),
    ('Ticaret unvanı', 'Kızılırmak Medya ve Yayıncılık Ltd. Şti.'),
    ('Merkez', 'Çorum / Merkez'),
    ('Sermaye', '500.000,00 TL'),
    ('Tescil tarihi', '14.03.2019'),
    ('Temsile yetkili', 'Selim Aydoğan (Müdür)'),
])
d.text((70, y + 30), 'Şirketin amaç ve konusu:', font=f(21, True), fill=KOYU)
metin = ('Her türlü süreli ve süresiz yayın çıkarmak, haber ajansı faaliyetinde\n'
         'bulunmak, internet haber sitesi kurmak ve işletmek, radyo-televizyon\n'
         'yayıncılığı yapmak, basılı ve dijital içerik üretimi gerçekleştirmek.')
d.multiline_text((70, y + 76), metin, font=f(21), fill=KOYU, spacing=12)
d.text((70, 1620), 'Bu sayfa test amacıyla üretilmiş bir örnektir.', font=f(18), fill=GRI)
_ts = filigran(img)
_ts.save(HEDEF / 'ticaret-sicil.pdf', resolution=150.0)
_ts.save(HEDEF / 'ticaret-sicil.jpg', quality=85)

# ─────────── 3) Çalışma belgesi ───────────
img, d = a4(baslik_bandi=False)
d.rectangle([40, 40, 1200, 190], fill=(250, 250, 251))
d.rectangle([40, 186, 1200, 190], fill=KIRMIZI)
d.text((70, 70), 'KIZILIRMAK MEDYA VE YAYINCILIK LTD. ŞTİ.', font=f(25, True), fill=KOYU)
d.text((70, 112), 'Gazi Caddesi No: 48/3  ·  Merkez / ÇORUM  ·  0364 213 45 67', font=f(19), fill=GRI)
d.text((70, 250), 'ÇALIŞMA BELGESİ', font=f(28, True), fill=KOYU)
d.text((70, 300), 'Tarih: 18.08.2026  ·  Sayı: İK-2026/214', font=f(19), fill=GRI)
govde = ('İlgili Makama,\n\n'
         'Aşağıda kimlik bilgileri yazılı personel, şirketimizin Haber Merkezi\n'
         'biriminde 02.09.2021 tarihinden itibaren tam zamanlı olarak\n'
         '"muhabir" unvanıyla görev yapmaktadır. Personelin 212 sayılı Basın\n'
         'İş Kanunu kapsamında sigorta girişi yapılmış olup, hâlen aktif\n'
         'çalışan statüsündedir.\n\n'
         'Bilgilerinize arz ederiz.')
d.multiline_text((70, 370), govde, font=f(22), fill=KOYU, spacing=14)
y = satirlar(d, 70, 760, [
    ('Adı soyadı', 'Elif Karaman'),
    ('T.C. kimlik no', '••••••••••• (belgede maskeli)'),
    ('Görevi', 'Muhabir — Spor Servisi'),
    ('İşe giriş tarihi', '02.09.2021'),
    ('SGK sicil no', '1 234 56 78 901 23'),
])
d.text((820, y + 90), 'Selim Aydoğan', font=f(22, True), fill=KOYU)
d.text((820, y + 124), 'İnsan Kaynakları Müdürü', font=f(19), fill=GRI)
d.text((70, 1620), 'Bu belge test amacıyla üretilmiştir; hukuki geçerliliği yoktur.', font=f(18), fill=GRI)
_cb = filigran(img)
_cb.save(HEDEF / 'calisma-belgesi.pdf', resolution=150.0)
_cb.save(HEDEF / 'calisma-belgesi.jpg', quality=85)  # eski testler JPG istiyor

# ─────────── 4) Kimlik görseli ───────────
img = Image.new('RGB', (1012, 638), (238, 241, 245))
d = ImageDraw.Draw(img)
d.rounded_rectangle([16, 16, 996, 622], radius=28, fill=(248, 249, 251), outline=(200, 206, 214), width=3)
d.rounded_rectangle([16, 16, 996, 120], radius=28, fill=(214, 222, 232))
d.rectangle([16, 92, 996, 120], fill=(214, 222, 232))
d.text((44, 46), 'TÜRKİYE CUMHURİYETİ  ·  ÖRNEK KİMLİK KARTI (TEST)', font=f(24, True), fill=(60, 68, 82))
d.rounded_rectangle([44, 156, 264, 456], radius=12, fill=(222, 226, 232), outline=(196, 202, 210), width=2)
d.ellipse([124, 216, 184, 276], fill=(178, 184, 194))
d.ellipse([104, 296, 204, 420], fill=(178, 184, 194))
d.text((92, 470), 'FOTOĞRAF', font=f(19), fill=GRI)
satirlar(d, 310, 170, [
    ('T.C. Kimlik No', '••••••••••• (test)'),
    ('Soyadı', 'KARAMAN'),
    ('Adı', 'ELİF'),
    ('Doğum tarihi', '11.06.1993'),
    ('Cinsiyet / Uyruk', 'K / T.C.'),
    ('Seri no', 'A00 000000'),
    ('Son geçerlilik', '11.06.2033'),
], adim=44, etiket_gen=250)
d.text((44, 560), 'Bu görsel bir test dosyasıdır, gerçek kimlik belgesi değildir.', font=f(19), fill=KIRMIZI)
filigran(img, 'TEST').save(HEDEF / 'kimlik.jpg', quality=88)

# ─────────── 5) Biyometrik fotoğraf ───────────
img = Image.new('RGB', (600, 800), (236, 239, 243))
d = ImageDraw.Draw(img)
d.ellipse([200, 150, 400, 350], fill=(168, 176, 188))          # bas
d.ellipse([130, 380, 470, 800], fill=(168, 176, 188))          # omuz
d.rectangle([0, 748, 600, 800], fill=(255, 255, 255))
d.text((22, 762), 'TEST · biyometrik fotoğraf yerine örnek görsel', font=f(19), fill=KIRMIZI)
img.save(HEDEF / 'foto.jpg', quality=90)

# ─────────── 6) Kasitli BOZUK dosya (magic byte testi) ───────────
(HEDEF / 'sahte-belge.pdf').write_bytes(b'Bu bir PDF degil, duz metin. Magic byte testi.\n' * 40)

for p in sorted(HEDEF.iterdir()):
    print(f'{p.name:24} {p.stat().st_size / 1024:8.1f} KB')
