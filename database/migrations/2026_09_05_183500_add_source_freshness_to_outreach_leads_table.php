<?php

use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        // Superseded by 2026_09_05_183000_expand_outreach_leads_for_bulk_import.
        // Kept as an intentional no-op so existing migration ordering stays stable.
    }

    public function down(): void
    {
        // No-op.
    }
};
