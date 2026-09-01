<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class RealEstateProfileSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_real_estate_profiles_table_has_required_columns(): void
    {
        $this->assertTrue(Schema::hasTable('real_estate_profiles'));

        foreach ([
            'conversation_control_id',
            'user_id',
            'ai_bot_id',
            'profile_type',
            'data',
            'valuation',
            'completeness_score',
            'confidence_score',
            'last_extracted_at',
        ] as $column) {
            $this->assertTrue(
                Schema::hasColumn('real_estate_profiles', $column),
                "Missing real_estate_profiles.{$column}"
            );
        }
    }
}
