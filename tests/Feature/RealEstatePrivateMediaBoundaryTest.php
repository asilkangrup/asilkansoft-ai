<?php

namespace Tests\Feature;

use Tests\TestCase;

class RealEstatePrivateMediaBoundaryTest extends TestCase
{
    public function test_unauthenticated_private_media_request_is_forbidden_instead_of_server_error(): void
    {
        $this->get(route('real-estate.private-media', [
            'profile' => 1,
            'media' => '00000000-0000-0000-0000-000000000000',
        ]))->assertForbidden();
    }
}
