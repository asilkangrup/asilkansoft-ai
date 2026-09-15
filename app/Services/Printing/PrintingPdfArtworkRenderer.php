<?php

namespace App\Services\Printing;

use RuntimeException;
use Symfony\Component\Process\Process;

final class PrintingPdfArtworkRenderer
{
    /** @return list<string> */
    public function renderFirstTwoPages(string $pdfBase64): array
    {
        $bytes = $this->decodePdf($pdfBase64);
        $binary = trim((string) shell_exec('command -v pdftoppm 2>/dev/null'));
        if ($binary === '') {
            throw new RuntimeException('PDF önizleme aracı sunucuda bulunamadı.');
        }

        $workDir = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.'wai-printing-'.bin2hex(random_bytes(8));
        if (! mkdir($workDir, 0700, true) && ! is_dir($workDir)) {
            throw new RuntimeException('PDF önizleme çalışma klasörü oluşturulamadı.');
        }

        $pdfPath = $workDir.DIRECTORY_SEPARATOR.'artwork.pdf';
        $prefix = $workDir.DIRECTORY_SEPARATOR.'page';

        try {
            if (file_put_contents($pdfPath, $bytes, LOCK_EX) === false) {
                throw new RuntimeException('PDF geçici dosyaya yazılamadı.');
            }

            $process = new Process([$binary, '-f', '1', '-l', '2', '-jpeg', '-jpegopt', 'quality=94', '-r', '180', $pdfPath, $prefix]);
            $process->setTimeout(30);
            $process->run();

            if (! $process->isSuccessful()) {
                throw new RuntimeException('PDF sayfaları önizlemeye dönüştürülemedi.');
            }

            $files = glob($prefix.'-*.jpg') ?: [];
            natsort($files);
            $files = array_slice(array_values($files), 0, 2);
            if ($files === []) {
                throw new RuntimeException('PDF içinde önizlenebilir sayfa bulunamadı.');
            }

            $pages = [];
            foreach ($files as $file) {
                $jpeg = file_get_contents($file);
                if (is_string($jpeg) && $jpeg !== '') {
                    $pages[] = base64_encode($jpeg);
                }
            }
            if ($pages === []) {
                throw new RuntimeException('PDF önizleme çıktısı okunamadı.');
            }

            return $pages;
        } finally {
            foreach (glob($workDir.DIRECTORY_SEPARATOR.'*') ?: [] as $file) {
                @unlink($file);
            }
            @rmdir($workDir);
        }
    }

    private function decodePdf(string $encoded): string
    {
        $encoded = trim($encoded);
        if (str_contains($encoded, ';base64,')) {
            $encoded = explode(';base64,', $encoded, 2)[1] ?? '';
        }

        $bytes = base64_decode($encoded, true);
        if (! is_string($bytes) || $bytes === '' || strlen($bytes) > 15 * 1024 * 1024) {
            throw new RuntimeException('PDF çözülemedi veya güvenli boyut sınırını aşıyor.');
        }
        if (! str_starts_with($bytes, '%PDF-')) {
            throw new RuntimeException('Gönderilen belge geçerli bir PDF değil.');
        }

        return $bytes;
    }
}
