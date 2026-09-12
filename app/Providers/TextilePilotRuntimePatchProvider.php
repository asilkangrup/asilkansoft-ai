<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class TextilePilotRuntimePatchProvider extends ServiceProvider
{
    public function register(): void
    {
        $scripts = [
            base_path('scripts/patch_textile_natural_conversation.php'),
            base_path('scripts/patch_textile_casper_notify.php'),
        ];

        foreach ($scripts as $script) {
            if (! is_file($script)) {
                continue;
            }

            $command = escapeshellarg(PHP_BINARY).' '.escapeshellarg($script).' 2>&1';
            exec($command, $output, $exitCode);

            if ($exitCode !== 0) {
                logger()->warning('TEXTILE PILOT RUNTIME PATCH FAILED', [
                    'script' => basename($script),
                    'output' => implode("\n", $output),
                ]);
            }
        }
    }
}
