from pathlib import Path

# Inbound deterministic steering
p = Path('app/Services/RealEstateWhatsAppInboundService.php')
s = p.read_text()
old = """        $answer = $this->deterministicSellerPriceAnswer(\n            conversation: $conversation,\n            message: $message,\n        );\n\n        if ($answer === null) {\n"""
new = """        $answer = $this->deterministicSellerPriceAnswer(\n            conversation: $conversation,\n            message: $message,\n        );\n\n        if ($answer === null) {\n            $answer = $this->deterministicSellerPriceSteeringAnswer(\n                conversation: $conversation,\n                message: $message,\n            );\n        }\n\n        if ($answer === null) {\n"""
if old in s:
    s = s.replace(old, new, 1)

marker = "    private function positivePrice(mixed $value): ?float\n"
helper = r'''    private function deterministicSellerPriceSteeringAnswer(
        ConversationControl $conversation,
        string $message,
    ): ?string {
        $normalized = strtolower(strtr(trim($message), [
            'İ' => 'i', 'I' => 'i', 'ı' => 'i',
            'Ş' => 's', 'ş' => 's', 'Ğ' => 'g', 'ğ' => 'g',
            'Ü' => 'u', 'ü' => 'u', 'Ö' => 'o', 'ö' => 'o',
            'Ç' => 'c', 'ç' => 'c',
        ]));

        $raiseIntent = false;
        foreach ([
            'fiyati artir', 'fiyat artir', 'biraz daha artir',
            'daha yuksek', 'yuksekten yaz', 'fiyati yuksek',
        ] as $signal) {
            if (str_contains($normalized, $signal)) {
                $raiseIntent = true;
                break;
            }
        }

        if (! $raiseIntent) {
            return null;
        }

        $profile = RealEstateProfile::query()
            ->isolatedProduction()
            ->where('conversation_control_id', $conversation->id)
            ->first();

        if (! $profile || $profile->profile_type !== 'seller') {
            return null;
        }

        $data = is_array($profile->data) ? $profile->data : [];
        $missing = [];

        if (empty($data['area_sqm'])) {
            $missing[] = 'm²';
        }
        if (empty($data['block_no']) || empty($data['parcel_no'])) {
            $missing[] = 'ada/parsel';
        }
        if (empty($data['location_url']) && (empty($data['neighborhood']) || empty($data['district']))) {
            $missing[] = 'konum';
        }
        if (empty($data['zoning_status'])) {
            $missing[] = 'imar bilgisi';
        }

        $answer = 'Fiyatı daha yüksek yazmak mümkün; ancak yatırımcı tarafında yüksek fiyatlar dönüşü zorlaştırabiliyor. '
            .'Hızlı nakit düşünüyorsanız yatırımcılarımızdan teklif toplayalım.';

        if ($missing !== []) {
            $answer .= "\nBunun için ".implode(', ', $missing).' ile varsa tapu/ilan görsellerini ve arsanın fotoğraflarını gönderin.';
        } else {
            $answer .= "\nVarsa tapu/ilan görsellerini ve arsanın fotoğraflarını da gönderin; dosyayı yatırımcıya hazır hale getireyim.";
        }

        return $answer;
    }

'''
if 'private function deterministicSellerPriceSteeringAnswer(' not in s:
    if marker not in s:
        raise SystemExit('inbound marker not found')
    s = s.replace(marker, helper + marker, 1)
p.write_text(s)

# Prompt seller behavior
p = Path('app/Services/RealEstateOpenAIService.php')
s = p.read_text()
needle = """Satıcının aciliyetini, pazarlık isteğini ve fiyat beklentisinin gerçekçiliğini konuşmanın bütününden analiz et. Manipülatif olma; doğru fırsat oluşması için profesyonel pazarlık öner.\n"""
repl = """Satıcının aciliyetini, pazarlık isteğini ve fiyat beklentisinin gerçekçiliğini konuşmanın bütününden analiz et. Manipülatif olma.\n- Satıcıya fiyatı nasıl yükselteceği, hangi rakamdan ilana çıkacağı veya pazarlıkla nasıl daha yüksek kapanış yapacağı konusunda uzun danışmanlık verme.\n- Ticari hedef, uygun fiyatlı ve yatırımcıya sunulabilir taşınmaz oluşturmaktır. Satıcı fiyatı yüksek tutmak istiyorsa tartışmaya girme; kısa biçimde yüksek fiyatın yatırımcı dönüşünü zorlaştırabileceğini söyle ve hızlı nakit/teklif toplama seçeneğine geç.\n- Güncel araştırma gerçekten zayıf talep/yavaş satış işareti vermiyorsa \"kimse almıyor\", \"piyasa tamamen durmuş\" gibi kesin genellemeler yapma. Bunun yerine \"yatırımcı tarafı yüksek fiyatlarda daha seçici oluyor\" veya \"yüksek fiyat dönüşü yavaşlatabiliyor\" gibi ölçülü dil kullan.\n- Hızlı nakit isteyen satıcıda eksikse m², konum, ada/parsel, tapu niteliği, imar durumu, ilan linki ve fotoğrafları iste. Tümünü tek seferde zorunlu form gibi isteme; en kritik eksikleri kısa biçimde tamamla.\n- Amaç satıcıya ders vermek değil, dosyayı yatırımcı teklifine hazır hale getirmektir.\n"""
if needle in s:
    s = s.replace(needle, repl, 1)
p.write_text(s)
