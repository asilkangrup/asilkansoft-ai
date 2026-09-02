from pathlib import Path


def replace_once(path: str, old: str, new: str) -> None:
    p = Path(path)
    s = p.read_text()
    if old not in s:
        raise SystemExit(f'pattern not found in {path}: {old[:120]!r}')
    p.write_text(s.replace(old, new, 1))


# Existing safe matches remain actionable; collect non-blocking photos only while no safe match exists.
replace_once(
    'app/Services/RealEstateNextBestActionService.php',
    '        if ($offerMissing !== []) {\n',
    '        if ($offerMissing !== [] && $matchCount === 0) {\n',
)

# Fail closed on the exact isolated conversation, not merely profile-level tenant columns.
replace_once(
    'app/Services/RealEstateSellerOfferPacketService.php',
    "    private function supports(RealEstateProfile $profile): bool\n    {\n        return $profile->profile_type === 'seller'\n            && $profile->belongsToIsolatedProductionScope();\n    }\n",
    "    private function supports(RealEstateProfile $profile): bool\n    {\n        if (\n            $profile->profile_type !== 'seller'\n            || (int) $profile->user_id !== RealEstateIsolationService::USER_ID\n            || (int) $profile->ai_bot_id !== RealEstateIsolationService::BOT_ID\n        ) {\n            return false;\n        }\n\n        $conversation = $profile->conversation()->first();\n\n        return app(RealEstateIsolationService::class)\n            ->supportsConversation($conversation);\n    }\n",
)

# Assert CLI privacy output from the captured Artisan buffer rather than ordered console events.
p = Path('tests/Feature/RealEstateSellerOfferPacketIntelligenceTest.php')
s = p.read_text()
if 'use Illuminate\\Support\\Facades\\Artisan;' not in s:
    s = s.replace(
        'use Illuminate\\Support\\Facades\\Hash;\n',
        'use Illuminate\\Support\\Facades\\Artisan;\nuse Illuminate\\Support\\Facades\\DB;\nuse Illuminate\\Support\\Facades\\Hash;\n',
        1,
    )
elif 'use Illuminate\\Support\\Facades\\DB;' not in s:
    s = s.replace(
        'use Illuminate\\Support\\Facades\\Artisan;\n',
        'use Illuminate\\Support\\Facades\\Artisan;\nuse Illuminate\\Support\\Facades\\DB;\n',
        1,
    )

old = """        $conversation = $this->conversation($bot, 38, 'offer-packet-foreign');
        $profile = $this->profileQuietly($conversation, [
"""
new = """        // Production conversation creation normalizes the isolated bot back to
        // organization 37. Force a post-create organization drift here so the
        // service is tested against an actual foreign-organization row.
        $conversation = $this->conversation($bot, 37, 'offer-packet-foreign');
        DB::table('conversation_controls')
            ->where('id', $conversation->id)
            ->update(['organization_id' => 38]);
        $conversation->refresh();

        $profile = $this->profileQuietly($conversation, [
"""
if old not in s:
    raise SystemExit('foreign organization setup block not found')
s = s.replace(old, new, 1)

old = """        $this->artisan('real-estate:offer-packets', ['--json' => true])
            ->expectsOutputToContain('\"seller_profiles\": 1')
            ->doesntExpectOutputToContain('private-customer-note-should-never-appear')
            ->expectsOutputToContain('\"contains_customer_pii\": false')
            ->expectsOutputToContain('\"follow_up_scheduling_allowed\": false')
            ->assertSuccessful();
"""
new = """        $exitCode = Artisan::call('real-estate:offer-packets', ['--json' => true]);
        $output = Artisan::output();

        $this->assertSame(0, $exitCode);
        $this->assertStringContainsString('\"seller_profiles\": 1', $output);
        $this->assertStringNotContainsString('private-customer-note-should-never-appear', $output);
        $this->assertStringContainsString('\"contains_customer_pii\": false', $output);
        $this->assertStringContainsString('\"follow_up_scheduling_allowed\": false', $output);
"""
if old not in s:
    raise SystemExit('console assertion block not found')
p.write_text(s.replace(old, new, 1))

for path in [
    '.github/scripts/fix_seller_offer_packet_ci.py',
    '.github/workflows/fix-seller-offer-packet-ci.yml',
]:
    Path(path).unlink(missing_ok=True)
