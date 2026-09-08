# WAI Insurance OS — Open Hızlı Teklif Entegrasyonu

Bu modül, sigorta operasyonunu WAI panelinde yönetmek ve Open Yazılım Hızlı Teklif servislerini son aşamada bağlamak için hazırlanmıştır.

## Doğrulanan Open servisleri

Open entegrasyon kılavuzunda her metot için `Acente Kodu` ve Hızlı Teklif Yönetici panelinde Tali Kullanıcı üzerinden üretilen `Token` gerektiği belirtilmektedir.

Kılavuzda doğrulanan servisler:

- `POST /api/APIGetUsers`
- `POST /api/APITeklifGetir?teklifid={id}`
- `POST /api/APITeklifDetayGetir?teklifid={id}`
- `POST /api/APITeklifFiyatlariniGetir?teklifid={id}`
- `POST /api/ApiDaskDetayGetir?teklifid={id}`
- `POST /api/ApiKonutDetayGetir?teklifid={id}`

Request body temel kimlik doğrulaması:

```json
{
  "acenteKodu": "...",
  "token": "..."
}
```

Swagger: `https://webservis.openyazilim.com/swagger/index.html`

Kılavuzdaki not gereği kullanılabilir dış servisler adı `API` ile başlayan metotlardır. Teklif oluşturma metodunun adı kılavuz metninde açıkça verilmediği için, üretim kodunda tahmin edilmemiştir. Token bağlandığında Swagger üzerinden doğrulanıp ayrı adapter metoduna eklenecektir.

## Mimari

- `insurance_cases`: tek operasyon kaydı ve durum makinesi.
- `insurance_quote_results`: Open'dan alınan fiyat sonuçları.
- `insurance_events`: denetlenebilir işlem geçmişi.
- `OpenHizliTeklifClient`: Open API adapteri.
- `InsuranceWorkflowService`: iş akışı ve hata yönetimi.
- `SigortaOperasyonMerkezi`: Filament canlı operasyon paneli.

## Durumlar

`new -> waiting_vehicle -> ready_for_open -> open_pending -> quoted -> payment_ready -> issued`

Hatalar `needs_attention` / `failed` durumuna alınır. Sahte fiyat veya demo veri üretilmez.

## Son bağlantı

Canlı bağlantı için `.env`:

```env
OPEN_HIZLI_TEKLIF_ACENTE_KODU=
OPEN_HIZLI_TEKLIF_TOKEN=
```

Bu bilgiler en son aşamada müşteri Open yönetici panelinden alındıktan sonra tanımlanacaktır.
