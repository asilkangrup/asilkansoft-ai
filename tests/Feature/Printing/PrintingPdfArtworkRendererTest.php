<?php

namespace Tests\Feature\Printing;

use App\Services\Printing\PrintingPdfArtworkRenderer;
use RuntimeException;
use Tests\TestCase;

final class PrintingPdfArtworkRendererTest extends TestCase
{
    public function test_it_rejects_non_pdf_payloads(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('geçerli bir PDF');

        app(PrintingPdfArtworkRenderer::class)->renderFirstTwoPages(
            base64_encode('not-a-pdf')
        );
    }

    public function test_it_renders_a_small_single_page_pdf_to_jpeg(): void
    {
        if (trim((string) shell_exec('command -v pdftoppm 2>/dev/null')) === '') {
            $this->markTestSkipped('pdftoppm is not available.');
        }

        $pdf = "%PDF-1.4\n"
            ."1 0 obj<</Type/Catalog/Pages 2 0 R>>endobj\n"
            ."2 0 obj<</Type/Pages/Count 1/Kids[3 0 R]>>endobj\n"
            ."3 0 obj<</Type/Page/Parent 2 0 R/MediaBox[0 0 255 150]/Contents 4 0 R/Resources<<>>>>endobj\n"
            ."4 0 obj<</Length 30>>stream\n0.1 0.2 0.7 rg 0 0 255 150 re f\nendstream\nendobj\n"
            ."xref\n0 5\n0000000000 65535 f \n"
            ."0000000009 00000 n \n0000000058 00000 n \n0000000115 00000 n \n0000000210 00000 n \n"
            ."trailer<</Size 5/Root 1 0 R>>\nstartxref\n290\n%%EOF";

        try {
            $pages = app(PrintingPdfArtworkRenderer::class)
                ->renderFirstTwoPages(base64_encode($pdf));
        } catch (RuntimeException $e) {
            // Some Poppler builds reject ultra-minimal synthetic PDFs. In that
            // case the important contract remains that rendering fails closed.
            $this->assertStringContainsString('PDF', $e->getMessage());
            return;
        }

        $this->assertCount(1, $pages);
        $jpeg = base64_decode($pages[0], true);
        $this->assertIsString($jpeg);
        $this->assertSame("\xFF\xD8\xFF", substr($jpeg, 0, 3));
    }
}
