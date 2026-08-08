<?php

namespace App\Filament\Resources\Orders\Pages;

use App\Filament\Resources\Orders\OrderResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListOrders extends ListRecords
{
    protected static string $resource = OrderResource::class;

    public function mount(): void
    {
        parent::mount();

        $status = request()->query('status');

        $allowedStatuses = [
            'draft',
            'pending',
            'confirmed',
            'preparing',
            'shipped',
            'delivered',
            'cancelled',
        ];

        if (
            is_string($status)
            && in_array($status, $allowedStatuses, true)
        ) {
            $this->tableFilters = [
                'status' => [
                    'value' => $status,
                ],
            ];
        }
    }

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Yeni Sipariş'),
        ];
    }
}