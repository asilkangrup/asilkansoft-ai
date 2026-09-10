<?php

namespace Tests\Unit;

use App\Services\Textile\TextileWhatsAppInboundService;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

class TextileWhatsAppInboundServiceTest extends TestCase
{
    private TextileWhatsAppInboundService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = (new ReflectionClass(TextileWhatsAppInboundService::class))
            ->newInstanceWithoutConstructor();
    }

    public function test_seventy_five_items_receive_only_ten_lira_discount(): void
    {
        $state = $this->orderState(75, true);
        $this->assertSame([365, 27375, 'Sipariş detaylarına göre netleşir'], $this->invoke('quote', $state));
    }

    public function test_one_hundred_items_receive_fifteen_lira_discount(): void
    {
        $state = $this->orderState(100, true);
        $this->assertSame([340, 34000, 'Sipariş detaylarına göre netleşir'], $this->invoke('quote', $state));
    }

    public function test_quantity_thresholds_are_not_exposed_in_price_reply(): void
    {
        $state = $this->orderState(75, true);
        $reply = $this->invoke('pricingReply', 'İndirim mümkün mü?', $state);

        $this->assertStringContainsString('365 TL/adet', $reply);
        $this->assertStringContainsString('27.375 TL', $reply);
        $this->assertStringNotContainsString('30', $reply);
        $this->assertStringNotContainsString('100', $reply);
        $this->assertStringNotContainsString('aralık', $reply);
    }

    public function test_new_artwork_can_be_assigned_to_multiple_positions_at_once(): void
    {
        $state = $this->orderState(50, false);
        $state['logo_received'] = true;
        $state['pending_uploaded_artwork'] = [
            'logo_base64' => 'encoded-artwork',
            'logo_mime' => 'image/png',
        ];
        $state['awaiting_uploaded_artwork_position'] = true;

        $parsed = $this->invoke('parseText', $state, 'Ön büyük, sağ göğüs');

        $this->assertFalse($parsed['awaiting_uploaded_artwork_position']);
        $this->assertSame(
            ['front_large', 'right_chest'],
            array_column($parsed['additional_prints'], 'position'),
        );
    }

    private function orderState(int $quantity, bool $discount): array
    {
        return [
            'product' => 'Polo Yaka Tişört',
            'product_category' => 'shirt',
            'quantity' => $quantity,
            'color' => 'white',
            'color_label' => 'Beyaz',
            'position' => 'front_large',
            'print_type' => 'DTG Baskı',
            'discount_requested' => $discount,
            'additional_prints' => [[
                'position' => 'back_large',
                'logo_base64' => 'encoded-artwork',
                'logo_mime' => 'image/png',
            ]],
        ];
    }

    private function invoke(string $method, mixed ...$arguments): mixed
    {
        $reflection = new ReflectionClass($this->service);
        $callable = $reflection->getMethod($method);
        $callable->setAccessible(true);

        return $callable->invoke($this->service, ...$arguments);
    }
}
