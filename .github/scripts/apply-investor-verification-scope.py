from pathlib import Path

service = Path("app/Services/RealEstateVerificationService.php")
s = service.read_text()

needle = """        if (! $profile) {
            return null;
        }

        $data = is_array($profile->data) ? $profile->data : [];
"""
repl = """        if (! $profile) {
            return null;
        }

        // Documentary/property-identity verification is seller-side only.
        // Investor/buyer preferences can legitimately differ from a listing or
        // screenshot they share; treating that as a property-identity conflict
        // can incorrectly block matching and ask seller-specific questions.
        if ($profile->profile_type !== 'seller') {
            $this->clearNonSellerVerification($profile, $conversation);

            return null;
        }

        $data = is_array($profile->data) ? $profile->data : [];
"""
if needle not in s:
    raise SystemExit("process seller-scope needle not found")
s = s.replace(needle, repl, 1)

needle = """        $profile = $this->profileFor($conversation);
        $verification = is_array($profile?->data)
            ? ($profile->data['verification_intelligence'] ?? null)
            : null;
"""
repl = """        $profile = $this->profileFor($conversation);

        if (! $profile || $profile->profile_type !== 'seller') {
            return '';
        }

        $verification = is_array($profile->data)
            ? ($profile->data['verification_intelligence'] ?? null)
            : null;
"""
if needle not in s:
    raise SystemExit("prompt seller-scope needle not found")
s = s.replace(needle, repl, 1)

marker = """    private function inScope(ConversationControl $conversation): bool
    {
"""
cleanup = """    private function clearNonSellerVerification(
        RealEstateProfile $profile,
        ConversationControl $conversation
    ): void {
        $data = is_array($profile->data) ? $profile->data : [];

        if (array_key_exists('verification_intelligence', $data)) {
            unset($data['verification_intelligence']);
            $profile->update(['data' => $data]);
        }

        $conversation->update([
            'tags' => collect($conversation->etiketler())
                ->filter(fn ($tag): bool =>
                    is_string($tag)
                    && ! str_starts_with($tag, self::TAG_PREFIX)
                )
                ->values()
                ->all(),
        ]);
    }

"""
if marker not in s:
    raise SystemExit("cleanup insertion marker not found")
s = s.replace(marker, cleanup + marker, 1)
service.write_text(s)

alerts = Path("app/Services/RealEstateOperatorAlertService.php")
a = alerts.read_text()
needle = """        if (in_array($verificationStatus, ['blocked', 'high_risk'], true)) {
"""
repl = """        if (
            $profile->profile_type === 'seller'
            && in_array($verificationStatus, ['blocked', 'high_risk'], true)
        ) {
"""
if needle not in a:
    raise SystemExit("operator alert seller-scope needle not found")
a = a.replace(needle, repl, 1)
alerts.write_text(a)

test = Path("tests/Feature/RealEstateVerificationRiskTest.php")
t = test.read_text()
import_needle = """use App\\Services\\RealEstateMatchVerificationFilterService;
"""
import_repl = """use App\\Services\\RealEstateMatchVerificationFilterService;
use App\\Services\\RealEstateOperatorAlertService;
"""
if import_needle not in t:
    raise SystemExit("test import marker not found")
t = t.replace(import_needle, import_repl, 1)

method_marker = """    public function test_verification_services_are_hard_scoped_to_fresh_account(): void
"""
method = r"""    public function test_investor_media_mismatch_does_not_create_seller_verification_risk(): void
    {
        $bot = $this->seedRealEstateBot();
        $conversation = $this->conversation(
            $bot,
            'investor-media-preference',
            [
                'business:real_estate_investor',
                'real_estate:verification:high_risk',
            ]
        );

        $profile = RealEstateProfile::query()->create([
            'conversation_control_id' => $conversation->id,
            'user_id' => 40,
            'ai_bot_id' => 35,
            'profile_type' => 'investor',
            'data' => [
                'property_type' => 'arsa',
                'city' => 'Muğla',
                'budget_max' => 5000000,
                'media_findings' => [[
                    'document_type' => 'listing_screenshot',
                    'property_type' => 'daire',
                    'city' => 'İstanbul',
                    'confidence_score' => 95,
                ]],
                'verification_intelligence' => [
                    'status' => 'high_risk',
                    'risk_score' => 65,
                    'safe_to_match' => false,
                    'conflicts' => [[
                        'field' => 'property_type',
                        'severity' => 'high',
                    ]],
                ],
                'decision_intelligence' => [
                    'lead_score' => 80,
                    'lead_temperature' => 'hot',
                    'stage' => 'ready',
                    'ready_for_match' => true,
                    'next_best_action' => 'Yatırım hedefini netleştir.',
                ],
            ],
            'valuation' => [],
            'completeness_score' => 80,
            'confidence_score' => 80,
        ]);

        $this->assertNull(
            app(RealEstateVerificationService::class)->process($conversation)
        );
        $this->assertSame(
            '',
            app(RealEstateVerificationService::class)->promptFor($conversation)
        );

        $profile->refresh();
        $conversation->refresh();

        $this->assertArrayNotHasKey(
            'verification_intelligence',
            $profile->data
        );
        $this->assertFalse(
            collect($conversation->etiketler())
                ->contains(fn ($tag): bool =>
                    is_string($tag)
                    && str_starts_with($tag, 'real_estate:verification:')
                )
        );

        $alerts = app(RealEstateOperatorAlertService::class)->sync($profile);

        $this->assertFalse(
            collect($alerts)->contains(
                fn (array $alert): bool => ($alert['type'] ?? null) === 'verification_risk'
            )
        );
        $this->assertTrue(
            collect($alerts)->contains(
                fn (array $alert): bool => ($alert['type'] ?? null) === 'hot_lead'
            )
        );
        $this->assertNull($conversation->next_follow_up_at);
    }

"""
if method_marker not in t:
    raise SystemExit("test insertion marker not found")
t = t.replace(method_marker, method + method_marker, 1)
test.write_text(t)
