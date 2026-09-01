<?php

namespace App\Console\Commands;

use App\Models\RealEstateOperatorAlert;
use App\Services\RealEstateIsolationService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

class RealEstateOperatorAlerts extends Command
{
    protected $signature = 'real-estate:operator-alerts
        {--status=open : Filter by open or resolved}
        {--severity= : Optional severity filter}
        {--limit=50 : Maximum rows to display}
        {--resolve= : Resolve one alert id without sending any customer message}
        {--json : Output JSON instead of a table}';

    protected $description = 'Inspect or safely resolve operator alerts for the isolated Emlak AI account.';

    public function handle(): int
    {
        if (! Schema::hasTable('real_estate_operator_alerts')) {
            $this->error('real_estate_operator_alerts table is not available.');

            return self::FAILURE;
        }

        if ($this->option('resolve')) {
            return $this->resolveAlert((int) $this->option('resolve'));
        }

        $status = strtolower(trim((string) $this->option('status')));

        if (! in_array($status, ['open', 'resolved'], true)) {
            $this->error('--status must be open or resolved.');

            return self::INVALID;
        }

        $severity = strtolower(trim((string) $this->option('severity')));

        if ($severity !== '' && ! in_array($severity, ['low', 'medium', 'high', 'critical'], true)) {
            $this->error('--severity must be low, medium, high or critical.');

            return self::INVALID;
        }

        $limit = max(1, min(200, (int) $this->option('limit')));
        $query = $this->baseQuery()
            ->where('status', $status)
            ->when($severity !== '', fn ($query) => $query->where('severity', $severity))
            ->orderByRaw("case severity when 'critical' then 1 when 'high' then 2 when 'medium' then 3 else 4 end")
            ->latest('opened_at')
            ->limit($limit);

        $alerts = $query->get();

        if ($this->option('json')) {
            $this->line($alerts->map(fn (RealEstateOperatorAlert $alert): array => [
                'id' => $alert->id,
                'type' => $alert->type,
                'severity' => $alert->severity,
                'status' => $alert->status,
                'title' => $alert->title,
                'message' => $alert->message,
                'conversation_control_id' => $alert->conversation_control_id,
                'real_estate_profile_id' => $alert->real_estate_profile_id,
                'payload' => $alert->payload,
                'opened_at' => $alert->opened_at?->toIso8601String(),
                'resolved_at' => $alert->resolved_at?->toIso8601String(),
            ])->toJson(JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

            return self::SUCCESS;
        }

        $this->table(
            ['ID', 'Severity', 'Type', 'Title', 'Conversation', 'Opened'],
            $alerts->map(fn (RealEstateOperatorAlert $alert): array => [
                $alert->id,
                $alert->severity,
                $alert->type,
                $alert->title,
                $alert->conversation_control_id,
                $alert->opened_at?->format('Y-m-d H:i:s'),
            ])->all()
        );

        return self::SUCCESS;
    }

    private function resolveAlert(int $id): int
    {
        if ($id <= 0) {
            $this->error('--resolve requires a positive alert id.');

            return self::INVALID;
        }

        $alert = $this->baseQuery()->whereKey($id)->first();

        if (! $alert) {
            $this->error('Alert not found in isolated Emlak AI scope.');

            return self::FAILURE;
        }

        $alert->resolve();
        $this->info('Alert '.$id.' resolved. No customer message was sent.');

        return self::SUCCESS;
    }

    private function baseQuery()
    {
        return RealEstateOperatorAlert::query()
            ->where('user_id', RealEstateIsolationService::USER_ID)
            ->where('organization_id', RealEstateIsolationService::ORGANIZATION_ID)
            ->where('ai_bot_id', RealEstateIsolationService::BOT_ID);
    }
}
