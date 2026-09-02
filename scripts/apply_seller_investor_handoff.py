from pathlib import Path


def replace_once(path: str, old: str, new: str) -> None:
    p = Path(path)
    text = p.read_text()
    if new in text:
        return
    if old not in text:
        raise SystemExit(f"needle not found in {path}: {old[:100]!r}")
    p.write_text(text.replace(old, new, 1))


observer = "app/Observers/RealEstateProfileObserver.php"
replace_once(
    observer,
    "use App\\Services\\RealEstateSellerOfferPacketService;\n",
    "use App\\Services\\RealEstateSellerOfferPacketService;\nuse App\\Services\\RealEstateSellerInvestorHandoffService;\n",
)
replace_once(
    observer,
    "        app(RealEstateSellerOfferPacketService::class)->sync($profile);\n        $profile->refresh();\n\n        $this->recordEvidence($profile, $conversation);",
    "        app(RealEstateSellerOfferPacketService::class)->sync($profile);\n        $profile->refresh();\n        app(RealEstateSellerInvestorHandoffService::class)->sync($profile);\n        $profile->refresh();\n\n        $this->recordEvidence($profile, $conversation);",
)

alert = "app/Services/RealEstateOperatorAlertService.php"
replace_once(
    alert,
    "        'seller_protection_attention',\n",
    "        'seller_protection_attention',\n        'investor_offer_handoff',\n",
)
replace_once(
    alert,
    "        $sellerMotivation = is_array($data['seller_motivation_intelligence'] ?? null)\n            ? $data['seller_motivation_intelligence']\n            : [];\n        $alerts = [];",
    "        $sellerMotivation = is_array($data['seller_motivation_intelligence'] ?? null)\n            ? $data['seller_motivation_intelligence']\n            : [];\n        $handoff = is_array($data['investor_offer_handoff_intelligence'] ?? null)\n            ? $data['investor_offer_handoff_intelligence']\n            : [];\n        $alerts = [];",
)

marker = "\n        return $alerts;\n    }\n\n    private function upsert("
block = r'''

        if (
            $profile->profile_type === 'seller'
            && (bool) ($handoff['ready_for_operator_handoff'] ?? false)
            && (string) ($handoff['status'] ?? '') === 'ready'
        ) {
            $candidateCount = max(0, (int) ($handoff['candidate_count'] ?? 0));
            $strongestGrade = (string) ($handoff['strongest_match_grade'] ?? '');
            $alerts[] = [
                'alert_key' => 'investor_offer_handoff:'.$profile->id,
                'type' => 'investor_offer_handoff',
                'severity' => $strongestGrade === 'strong' ? 'high' : 'medium',
                'title' => 'Yatırımcı teklif toplama dosyası hazır',
                'message' => (string) ($handoff['recommended_operator_action']
                    ?? 'Uygun yatırımcı adaylarını operatör inceleyip gerçek teklif toplama sürecini manuel başlatsın.'),
                'payload' => [
                    'candidate_count' => $candidateCount,
                    'strongest_match_score' => is_numeric($handoff['strongest_match_score'] ?? null)
                        ? (int) $handoff['strongest_match_score']
                        : null,
                    'strongest_match_grade' => in_array($strongestGrade, ['possible', 'good', 'strong'], true)
                        ? $strongestGrade
                        : null,
                    'candidate_refs' => collect($handoff['candidate_refs'] ?? [])
                        ->filter(fn ($candidate): bool => is_array($candidate))
                        ->take(3)
                        ->values()
                        ->all(),
                    'automatic_investor_outreach_allowed' => false,
                    'automatic_customer_follow_up_allowed' => false,
                    'private_seller_floor_included' => false,
                    'customer_pii_included' => false,
                    'human_review_required_before_investor_contact' => true,
                ],
            ];
        }
'''
p = Path(alert)
text = p.read_text()
if "'investor_offer_handoff:'.$profile->id" not in text:
    if marker not in text:
        raise SystemExit("operator alert return marker not found")
    p.write_text(text.replace(marker, block + marker, 1))

readiness = "app/Services/RealEstateReadinessService.php"
replace_once(
    readiness,
    "        $caseLifecycleReady = Schema::hasTable('real_estate_case_events')\n            && class_exists(RealEstateCaseLifecycleService::class);\n        $audioTranscriptionReady = $this->audioTranscriptionReady();",
    "        $caseLifecycleReady = Schema::hasTable('real_estate_case_events')\n            && class_exists(RealEstateCaseLifecycleService::class);\n        $sellerInvestorHandoffReady = class_exists(RealEstateSellerInvestorHandoffService::class);\n        $audioTranscriptionReady = $this->audioTranscriptionReady();",
)
replace_once(
    readiness,
    "            'case_lifecycle_ready' => $caseLifecycleReady,\n            'audio_transcription_ready' => $audioTranscriptionReady,",
    "            'case_lifecycle_ready' => $caseLifecycleReady,\n            'seller_investor_handoff_ready' => $sellerInvestorHandoffReady,\n            'audio_transcription_ready' => $audioTranscriptionReady,",
)
