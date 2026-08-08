<?php

namespace App\Filament\Pages;

use App\Services\MemoryService;
use App\Services\OpenAIService;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

class TestSohbeti extends Page
{
    protected static ?string $navigationLabel = 'Test Sohbeti';

    protected static ?string $title = 'Yapay Zekâyı Test Et';

    protected static ?string $slug = 'test-sohbeti';

    protected string $view = 'filament.pages.test-sohbeti';

    public string $mesaj = '';

    public array $mesajlar = [];

    public string $sessionId = '';

    public function mount(MemoryService $memoryService): void
    {
        $this->sessionId = $memoryService->yeniOturumId();

        $this->mesajlar = [
            [
                'rol' => 'assistant',
                'metin' => 'Merhaba 👋 Ben yapay zekâ asistanınızım. Size nasıl yardımcı olabilirim?',
            ],
        ];
    }

    public function mesajGonder(
        OpenAIService $openAIService,
        MemoryService $memoryService
    ): void {
        $kullaniciMesaji = trim($this->mesaj);

        if ($kullaniciMesaji === '') {
            Notification::make()
                ->title('Lütfen bir mesaj yazın.')
                ->warning()
                ->send();

            return;
        }

        $userId = auth()->id();

        if (! $userId) {
            Notification::make()
                ->title('Oturum bulunamadı.')
                ->danger()
                ->send();

            return;
        }

        $memoryService->mesajKaydet(
            userId: $userId,
            aiBotId: null,
            sessionId: $this->sessionId,
            role: 'user',
            message: $kullaniciMesaji,
        );

        $this->mesajlar[] = [
            'rol' => 'user',
            'metin' => $kullaniciMesaji,
        ];

        $this->mesaj = '';

        $gecmis = $memoryService->openAIMesajlariHazirla(
            userId: $userId,
            sessionId: $this->sessionId,
            limit: 20,
        );

        $yapayZekaCevabi = $openAIService->cevapVer($gecmis);

        $memoryService->mesajKaydet(
            userId: $userId,
            aiBotId: null,
            sessionId: $this->sessionId,
            role: 'assistant',
            message: $yapayZekaCevabi,
        );

        $this->mesajlar[] = [
            'rol' => 'assistant',
            'metin' => $yapayZekaCevabi,
        ];
    }

    public function sohbetiTemizle(MemoryService $memoryService): void
    {
        $userId = auth()->id();

        if ($userId && $this->sessionId !== '') {
            $memoryService->sohbetiTemizle(
                userId: $userId,
                sessionId: $this->sessionId,
            );
        }

        $this->sessionId = $memoryService->yeniOturumId();

        $this->mesaj = '';

        $this->mesajlar = [
            [
                'rol' => 'assistant',
                'metin' => 'Sohbet temizlendi. Size nasıl yardımcı olabilirim?',
            ],
        ];
    }
}