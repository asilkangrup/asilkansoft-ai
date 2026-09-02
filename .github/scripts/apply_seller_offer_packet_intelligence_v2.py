from pathlib import Path


def replace_once(path: str, old: str, new: str) -> None:
    p = Path(path)
    s = p.read_text()
    if old not in s:
        raise SystemExit(f"pattern not found in {path}: {old[:120]!r}")
    p.write_text(s.replace(old, new, 1))


replace_once(
    'app/Observers/RealEstateProfileObserver.php',
    'use App\\Services\\RealEstateSellerMotivationService;\n',
    'use App\\Services\\RealEstateSellerMotivationService;\nuse App\\Services\\RealEstateSellerOfferPacketService;\n',
)
replace_once(
    'app/Observers/RealEstateProfileObserver.php',
    "        app(RealEstateSellerMotivationService::class)->sync($profile);\n        $profile->refresh();\n\n        $this->recordEvidence($profile, $conversation);\n",
    "        app(RealEstateSellerMotivationService::class)->sync($profile);\n        $profile->refresh();\n        app(RealEstateSellerOfferPacketService::class)->sync($profile);\n        $profile->refresh();\n\n        $this->recordEvidence($profile, $conversation);\n",
)

replace_once(
    'app/Services/MemoryService.php',
    "                        trim(app(RealEstateSellerMotivationService::class)->promptFor($conversation)),\n                        trim(app(RealEstateEvidenceReconciliationService::class)->promptFor($conversation)),\n",
    "                        trim(app(RealEstateSellerMotivationService::class)->promptFor($conversation)),\n                        trim(app(RealEstateSellerOfferPacketService::class)->promptFor($conversation)),\n                        trim(app(RealEstateEvidenceReconciliationService::class)->promptFor($conversation)),\n",
)

replace_once(
    'app/Services/RealEstateMediaAnalysisService.php',
    '  "document_type": null,\n  "summary": null,\n',
    '  "media_category": null,\n  "document_type": null,\n  "summary": null,\n',
)
replace_once(
    'app/Services/RealEstateMediaAnalysisService.php',
    'confidence_score 0-100 arası olsun.\nsummary en fazla 3 kısa cümle olsun ve kişi adı/iletişim/kimlik/hesap bilgisi içerme.\n',
    'media_category yalnızca title_deed, parcel_document, listing, property_photo, location_map veya other olsun. property_photo yalnız görselin ana konusu gerçek taşınmaz/arsa/arazi fotoğrafıysa kullan; ilan/tapu/parsel ekran görüntüsünü property_photo sayma.\nconfidence_score 0-100 arası olsun.\nsummary en fazla 3 kısa cümle olsun ve kişi adı/iletişim/kimlik/hesap bilgisi içerme.\n',
)
replace_once(
    'app/Services/RealEstateMediaAnalysisService.php',
    "            'mime_type' => app(RealEstateMediaSafetyService::class)->normalizeMime(\n                (string) ($mediaContext['mime_type'] ?? '')\n            ) ?: null,\n            'document_type' => $this->nullable($data['document_type'] ?? null),\n",
    "            'mime_type' => app(RealEstateMediaSafetyService::class)->normalizeMime(\n                (string) ($mediaContext['mime_type'] ?? '')\n            ) ?: null,\n            'media_category' => $this->normalizeMediaCategory(\n                $data['media_category'] ?? null\n            ),\n            'document_type' => $this->nullable($data['document_type'] ?? null),\n",
)
replace_once(
    'app/Services/RealEstateMediaAnalysisService.php',
    '    private function nullable(mixed $value): ?string\n    {\n',
    "    private function normalizeMediaCategory(mixed $value): ?string\n    {\n        $value = strtolower(trim((string) ($value ?? '')));\n\n        return in_array($value, [\n            'title_deed',\n            'parcel_document',\n            'listing',\n            'property_photo',\n            'location_map',\n            'other',\n        ], true) ? $value : null;\n    }\n\n    private function nullable(mixed $value): ?string\n    {\n",
)

replace_once(
    'app/Services/RealEstateNextBestActionService.php',
    "        $missing = $this->stringList(\n            $decision['missing_critical_data'] ?? []\n        );\n\n        // A material verification conflict",
    "        $missing = $this->stringList(\n            $decision['missing_critical_data'] ?? []\n        );\n        $offerPacket = app(RealEstateSellerOfferPacketService::class)\n            ->summaryForProfile($profile);\n        $offerMissing = $this->stringList(\n            $offerPacket['missing_critical_for_offer'] ?? []\n        );\n        $offerQuestion = $this->cleanQuestion(\n            $offerPacket['recommended_next_request'] ?? null\n        );\n\n        // A material verification conflict",
)
replace_once(
    'app/Services/RealEstateNextBestActionService.php',
    "        // Pricing research blockers outrank optional seller-discovery prompts:\n",
    "        if (in_array('property_identity', $offerMissing, true)) {\n            return $this->payload(\n                actionCode: 'complete_seller_offer_packet',\n                priority: 'high',\n                actionText: $offerQuestion\n                    ?? 'Yatırımcı ön teklifi için taşınmaz kimliğini konum linki veya ada/parsel ile tamamla.',\n                singleQuestion: $offerQuestion,\n                blocking: true,\n                reasonCodes: ['seller_offer_packet_incomplete', 'missing_property_identity'],\n                matchCount: $matchCount,\n                stage: $stage,\n            );\n        }\n\n        // Pricing research blockers outrank optional seller-discovery prompts:\n",
)
replace_once(
    'app/Services/RealEstateNextBestActionService.php',
    "        // Optional conversation-completeness questions are allowed only after\n        // hard valuation and verification guards are clear.\n        if ($sellerQuestion !== null) {\n",
    "        if ($offerMissing !== []) {\n            return $this->payload(\n                actionCode: 'complete_seller_offer_packet',\n                priority: 'medium',\n                actionText: $offerQuestion\n                    ?? 'Satıcı dosyasını yatırımcı ön teklifine hazırlamak için kalan en kritik taşınmaz bilgisini veya gerçek mülk fotoğrafını iste.',\n                singleQuestion: $offerQuestion,\n                blocking: false,\n                reasonCodes: array_values(array_unique([\n                    'seller_offer_packet_incomplete',\n                    ...array_map(\n                        fn (string $field): string => 'offer_missing_'.$this->code($field),\n                        $offerMissing\n                    ),\n                ])),\n                matchCount: $matchCount,\n                stage: $stage,\n            );\n        }\n\n        // Optional conversation-completeness questions are allowed only after\n        // hard valuation, verification and investor-offer-packet guards are clear.\n        if ($sellerQuestion !== null) {\n",
)

replace_once(
    'app/Services/RealEstateDecisionService.php',
    "                'Fiyat beklentisini tek bir ilana değil güncel emsal aralığına dayandırarak yeniden çerçevele; satıcının esnekliğini doğal biçimde ölç.',\n",
    "                'Satıcıya fiyat yükseltme veya maksimum kapanış stratejisi verme. Güncel emsaller destekliyorsa yüksek fiyatlarda yatırımcıların daha seçici olabileceğini kısa ve ölçülü anlat; hızlı nakit istiyorsa dosyayı yatırımcı teklifine hazırla ve pazarlık esnekliğini doğal biçimde ölç.',\n",
)
replace_once(
    'app/Services/RealEstateDecisionService.php',
    "                'Satıcıya piyasa, hızlı satış ve yatırımcı alım aralığını şeffaf biçimde ayır. Yatırımcıya sunulabilir gerçek bir fırsat oluşturmak için fiyatın yatırımcı alım bandına yaklaşması gerektiğini emsal ve hız/fiyat dengesiyle anlat; makul bir karşı teklif aralığı öner ve pazarlık esnekliğini ölç. Aciliyet üzerinden baskı kurma, sahte alıcı/teklif kullanma.',\n",
    "                'Satıcıya uzun fiyat/kapanış danışmanlığı verme. Yatırımcı alım seviyesinin hızlı nakit karşılığı olduğunu kısa ve şeffaf anlat; yüksek fiyatlarda yatırımcı dönüşünün zorlaşabileceğini yalnız veri destekliyorsa belirt. Hızlı nakit istiyorsa gerekli taşınmaz bilgileri ve fotoğrafları tamamlayıp gerçek yatırımcı tekliflerini topla. Aciliyet üzerinden baskı kurma, sahte alıcı/teklif veya uydurma piyasa kötülüğü kullanma.',\n",
)
replace_once(
    'app/Services/RealEstateDecisionService.php',
    "                'Piyasa, hızlı satış ve yatırımcı alım aralıklarını birbirinden ayır; satıcıya hangi hız/fiyat dengesini tercih ettiğini netleştir.',\n",
    "                'Gerçekçi satış seviyesi ile hızlı nakit yatırımcı seviyesini kısa biçimde ayır. Satıcı hızlı nakit istiyorsa dosyayı yatırımcı teklifine hazırla; gereksiz ilan fiyatı veya maksimum kapanış stratejisi verme.',\n",
)

p = Path('app/Services/RealEstateWhatsAppInboundService.php')
s = p.read_text()
method = s.index('    private function deterministicSellerPriceSteeringAnswer(')
start = s.index('        $data = is_array($profile->data) ? $profile->data : [];', method)
return_pos = s.index('        return $answer;\n', start) + len('        return $answer;\n')
new = """        $packet = app(RealEstateSellerOfferPacketService::class)
            ->summaryForProfile($profile);
        $nextRequest = trim((string) ($packet['recommended_next_request'] ?? ''));

        $answer = 'Fiyatı daha yüksek yazmak mümkün; ancak yatırımcı tarafında yüksek fiyatlar dönüşü zorlaştırabiliyor. '
            .'Hızlı nakit düşünüyorsanız yatırımcılarımızdan teklif toplayalım.';

        if ($nextRequest !== '') {
            $answer .= "\\n".$nextRequest;
        } elseif ((bool) ($packet['ready_for_investor_offer'] ?? false)) {
            $answer .= "\\nDosya ön teklif toplamak için yeterli görünüyor; hızlı nakit tekliflerini değerlendirmeye geçebiliriz.";
        }

        return $answer;
"""
s = s[:start] + new + s[return_pos:]
p.write_text(s)

for path in [
    '.github/scripts/apply_seller_offer_packet_intelligence.py',
    '.github/scripts/apply_seller_offer_packet_intelligence_v2.py',
    '.github/workflows/apply-seller-offer-packet-intelligence.yml',
    '.github/SELLER_OFFER_PACKET_TRIGGER',
]:
    Path(path).unlink(missing_ok=True)
