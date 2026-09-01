<?php

namespace App\Console\Commands;

use App\Services\RealEstateOperationalAuditService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('wai:real-estate-audit {--hours=24} {--json} {--strict}')]
#[Description('İzole Emlak AI üretim güvenliği, teslimat, webhook, ses ve karar tutarlılığı denetimini çalıştırır.')]
class AuditRealEstateOperations extends Command
{
    public function handle(RealEstateOperationalAuditService $auditService): int
    {
        $hours = max(1, min(168, (int) $this->option('hours')));
        $snapshot = $auditService->snapshot($hours);

        if ((bool) $this->option('json')) {
            $this->line((string) json_encode(
                $snapshot,
                JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
            ));
        } else {
            $counts = $snapshot['incident_counts'];

            $this->info(
                "İzole Emlak AI operasyon denetimi: critical={$counts['critical']}, "
                ."warning={$counts['warning']}, info={$counts['info']}; "
                .'operational_safe='.(($snapshot['operational_safe'] ?? false) ? 'true' : 'false')
                .', ready_for_live_traffic='.(($snapshot['ready_for_live_traffic'] ?? false) ? 'true' : 'false')
            );

            $blockers = $snapshot['readiness_blockers'] ?? [];

            if ($blockers !== []) {
                $this->warn('Readiness blockers: '.implode(', ', $blockers));
            }

            foreach ($snapshot['incidents'] as $incident) {
                $entity = $incident['entity_type'];

                if ($incident['entity_id'] !== null) {
                    $entity .= '#'.$incident['entity_id'];
                }

                $this->line(
                    strtoupper($incident['severity']).' '
                    .$incident['code'].' ['.$entity.'] - '
                    .$incident['summary'].' | Aksiyon: '.$incident['remediation']
                );
            }
        }

        if (! ($snapshot['operational_safe'] ?? false)) {
            return self::FAILURE;
        }

        if ((bool) $this->option('strict') && ! ($snapshot['ready_for_live_traffic'] ?? false)) {
            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
