<?php

namespace App\Filament\Resources\Products\Pages;

use App\Filament\Resources\Products\ProductResource;
use App\Imports\ProductImport;
use App\Models\AiBot;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Maatwebsite\Excel\Facades\Excel;
use Throwable;

class ListProducts extends ListRecords
{
    protected static string $resource = ProductResource::class;

    protected function getHeaderActions(): array
    {
        return [

            Action::make('excel_yukle')
                ->label('Excel Yükle')
                ->icon('heroicon-o-arrow-up-tray')
                ->modalHeading('Excel ile Toplu Ürün Yükle')
                ->modalDescription(
                    'Ürünlerin hangi yapay zekâ botuna ekleneceğini seçin ve Excel dosyanızı yükleyin.'
                )
                ->modalSubmitActionLabel('Ürünleri Yükle')
                ->schema([

                    Select::make('ai_bot_id')
                        ->label('Yapay Zekâ')
                        ->options(
                            AiBot::query()
                                ->orderBy('name')
                                ->pluck('name', 'id')
                        )
                        ->searchable()
                        ->required(),

                    FileUpload::make('excel_file')
                        ->label('Excel Dosyası')
                        ->acceptedFileTypes([
                            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                            'application/vnd.ms-excel',
                            'text/csv',
                            'text/plain',
                        ])
                        ->helperText('XLSX, XLS veya CSV dosyası yükleyebilirsiniz.')
                        ->disk('local')
                        ->directory('product-imports')
                        ->required(),
                ])
                ->action(function (array $data): void {
                    try {
                        $path = storage_path(
                            'app/private/' . $data['excel_file']
                        );

                        if (! file_exists($path)) {
                            $path = storage_path(
                                'app/' . $data['excel_file']
                            );
                        }

                        if (! file_exists($path)) {
                            throw new \Exception(
                                'Yüklenen Excel dosyası bulunamadı.'
                            );
                        }

                        Excel::import(
                            new ProductImport((int) $data['ai_bot_id']),
                            $path
                        );

                        Notification::make()
                            ->title('Ürünler başarıyla yüklendi')
                            ->body('Excel dosyasındaki ürünler sisteme aktarıldı.')
                            ->success()
                            ->send();

                    } catch (Throwable $exception) {
                        report($exception);

                        Notification::make()
                            ->title('Excel yüklenemedi')
                            ->body($exception->getMessage())
                            ->danger()
                            ->send();
                    }
                }),

            CreateAction::make()
                ->label('Yeni Ürün'),
        ];
    }
}