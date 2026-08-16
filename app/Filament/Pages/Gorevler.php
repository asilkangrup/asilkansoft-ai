<?php

namespace App\Filament\Pages;

use App\Models\ConversationControl;
use App\Services\CrmActivityService;
use App\Services\OrganizationAccessService;
use BackedEnum;
use Carbon\Carbon;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class Gorevler extends Page
{
    protected string $view = 'filament.pages.gorevler';

    protected static ?string $title = 'GÃ¶revler';

    protected static ?string $navigationLabel = 'GÃ¶revler';

    protected static string | BackedEnum | null $navigationIcon =
        Heroicon::OutlinedClipboardDocumentCheck;

    protected static ?int $navigationSort = 33;

    public string $search = '';

    public string $temperatureFilter = 'all';

    public string $channelFilter = 'all';

    public static function canAccess(): bool
    {
        return app(
            OrganizationAccessService::class
        )->can(
            'tasks'
        );
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canAccess();
    }

    protected function accessService(): OrganizationAccessService
    {
        return app(
            OrganizationAccessService::class
        );
    }

    protected function currentOrganization()
    {
        return $this
            ->accessService()
            ->currentOrganization();
    }

    protected function currentRole(): ?string
    {
        return $this
            ->accessService()
            ->currentRole();
    }

    protected function canWriteTasks(): bool
    {
        return $this
            ->accessService()
            ->canWriteCrm();
    }

    protected function scopedConversationQuery(): Builder
    {
        $user = auth()->user();

        $query = ConversationControl::query();

        if (! $user) {
            return $query->whereRaw('1 = 0');
        }

        if ($user->is_admin) {
            return $query;
        }

        $organization =
            $this->currentOrganization();

        if (! $organization) {
            return $query->where(
                'user_id',
                $user->id
            );
        }

        $query->where(
            'organization_id',
            $organization->id
        );

        if ($this->currentRole() === 'sales') {
            $query->where(
                'assigned_user_id',
                $user->id
            );
        }

        return $query;
    }

    /*
    |--------------------------------------------------------------------------
    | CRM AKTÄ°VÄ°TE SERVÄ°SÄ°
    |--------------------------------------------------------------------------
    */

    protected function activityService(): CrmActivityService
    {
        return app(
            CrmActivityService::class
        );
    }

    /*
    |--------------------------------------------------------------------------
    | ANA QUERY
    |--------------------------------------------------------------------------
    */

    protected function baseQuery(): Builder
    {
        return $this->scopedConversationQuery()
            ->with([
                'aiBot',
                'assignedUser',
            ])
            ->whereNotNull(
                'next_follow_up_at'
            )
            ->whereNotIn(
                'lead_status',
                [
                    'won',
                    'lost',
                ]
            )
            ->when(
                trim($this->search) !== '',
                function (Builder $query): void {
                    $search = trim(
                        $this->search
                    );

                    $query->where(
                        function (Builder $query) use ($search): void {
                            $query
                                ->where(
                                    'customer_name',
                                    'like',
                                    "%{$search}%"
                                )
                                ->orWhere(
                                    'whatsapp_number',
                                    'like',
                                    "%{$search}%"
                                )
                                ->orWhere(
                                    'company_name',
                                    'like',
                                    "%{$search}%"
                                );
                        }
                    );
                }
            )
            ->when(
                $this->temperatureFilter !== 'all',
                fn (Builder $query) =>
                    $query->where(
                        'lead_temperature',
                        $this->temperatureFilter
                    )
            )
            ->when(
                $this->channelFilter !== 'all',
                fn (Builder $query) =>
                    $query->where(
                        'channel',
                        $this->channelFilter
                    )
            );
    }

    /*
    |--------------------------------------------------------------------------
    | GECÄ°KENLER
    |--------------------------------------------------------------------------
    */

    public function getOverdueTasksProperty(): Collection
    {
        return $this->baseQuery()
            ->where(
                'next_follow_up_at',
                '<',
                now()->startOfDay()
            )
            ->orderBy(
                'next_follow_up_at'
            )
            ->get();
    }

    /*
    |--------------------------------------------------------------------------
    | BUGÃœN
    |--------------------------------------------------------------------------
    */

    public function getTodayTasksProperty(): Collection
    {
        return $this->baseQuery()
            ->whereBetween(
                'next_follow_up_at',
                [
                    now()->startOfDay(),
                    now()->endOfDay(),
                ]
            )
            ->orderBy(
                'next_follow_up_at'
            )
            ->get();
    }

    /*
    |--------------------------------------------------------------------------
    | YAKLAÅAN
    |--------------------------------------------------------------------------
    */

    public function getUpcomingTasksProperty(): Collection
    {
        return $this->baseQuery()
            ->where(
                'next_follow_up_at',
                '>',
                now()->endOfDay()
            )
            ->orderBy(
                'next_follow_up_at'
            )
            ->limit(100)
            ->get();
    }

    /*
    |--------------------------------------------------------------------------
    | KPI
    |--------------------------------------------------------------------------
    */

    public function getOverdueCountProperty(): int
    {
        return $this->baseQuery()
            ->where(
                'next_follow_up_at',
                '<',
                now()->startOfDay()
            )
            ->count();
    }

    public function getTodayCountProperty(): int
    {
        return $this->baseQuery()
            ->whereBetween(
                'next_follow_up_at',
                [
                    now()->startOfDay(),
                    now()->endOfDay(),
                ]
            )
            ->count();
    }

    public function getUpcomingCountProperty(): int
    {
        return $this->baseQuery()
            ->where(
                'next_follow_up_at',
                '>',
                now()->endOfDay()
            )
            ->count();
    }

    public function getHotFollowUpsProperty(): int
    {
        return $this->baseQuery()
            ->where(
                'lead_temperature',
                'hot'
            )
            ->count();
    }

    /*
    |--------------------------------------------------------------------------
    | TAKÄ°BÄ° TAMAMLA
    |--------------------------------------------------------------------------
    */

    public function completeTask(
        int $customerId
    ): void {
        if (! $this->canWriteTasks()) {
            abort(403);
        }

        $customer = $this->scopedConversationQuery()
            ->whereKey(
                $customerId
            )
            ->first();

        if (! $customer) {
            return;
        }

        $oldFollowUp =
            $customer->next_follow_up_at
                ? $customer->next_follow_up_at->copy()
                : null;

        $customer->update([
            'next_follow_up_at' =>
                null,
        ]);

        $customer->refresh();

        $this->activityService()
            ->followUpChanged(
                conversation: $customer,
                oldDate: $oldFollowUp,
                newDate: null,
                performedBy: auth()->user(),
            );

        $this->dispatch(
            'task-updated'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | YARINA ERTELE
    |--------------------------------------------------------------------------
    */

    public function postponeToTomorrow(
        int $customerId
    ): void {
        if (! $this->canWriteTasks()) {
            abort(403);
        }

        $customer = $this->scopedConversationQuery()
            ->whereKey(
                $customerId
            )
            ->first();

        if (! $customer) {
            return;
        }

        $oldFollowUp =
            $customer->next_follow_up_at
                ? $customer->next_follow_up_at->copy()
                : null;

        $current =
            $customer->next_follow_up_at
                ?: now();

        $newTime =
            Carbon::parse(
                $current
            )
                ->addDay();

        $customer->update([
            'next_follow_up_at' =>
                $newTime,
        ]);

        $customer->refresh();

        $this->activityService()
            ->followUpChanged(
                conversation: $customer,
                oldDate: $oldFollowUp,
                newDate: $customer->next_follow_up_at,
                performedBy: auth()->user(),
            );

        $this->dispatch(
            'task-updated'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | 1 SAAT ERTELE
    |--------------------------------------------------------------------------
    */

    public function postponeOneHour(
        int $customerId
    ): void {
        if (! $this->canWriteTasks()) {
            abort(403);
        }

        $customer = $this->scopedConversationQuery()
            ->whereKey(
                $customerId
            )
            ->first();

        if (! $customer) {
            return;
        }

        $oldFollowUp =
            $customer->next_follow_up_at
                ? $customer->next_follow_up_at->copy()
                : null;

        $current =
            $customer->next_follow_up_at
                ?: now();

        $newTime =
            Carbon::parse(
                $current
            )
                ->addHour();

        $customer->update([
            'next_follow_up_at' =>
                $newTime,
        ]);

        $customer->refresh();

        $this->activityService()
            ->followUpChanged(
                conversation: $customer,
                oldDate: $oldFollowUp,
                newDate: $customer->next_follow_up_at,
                performedBy: auth()->user(),
            );

        $this->dispatch(
            'task-updated'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | FÄ°LTRELER
    |--------------------------------------------------------------------------
    */

    public function resetFilters(): void
    {
        $this->search = '';

        $this->temperatureFilter =
            'all';

        $this->channelFilter =
            'all';
    }

    /*
    |--------------------------------------------------------------------------
    | LABEL
    |--------------------------------------------------------------------------
    */

    public function temperatureLabel(
        ?string $temperature
    ): string {
        return match ($temperature) {
            'hot' =>
                'SÄ±cak',

            'warm' =>
                'IlÄ±k',

            default =>
                'SoÄŸuk',
        };
    }

    public function channelLabel(
        ?string $channel
    ): string {
        return match ($channel) {
            'instagram' =>
                'Instagram',

            'facebook' =>
                'Facebook',

            'web' =>
                'Web',

            default =>
                'WhatsApp',
        };
    }

    public function customerUrl(
        int $customerId
    ): string {
        return url(
            '/admin/musteriler'
            .'?customer='
            .$customerId
        );
    }


    public function getLiveKpiTrendsProperty(): array
    {
        $s=now()->subDays(6)->startOfDay();
        $rows=$this->baseQuery()->where('next_follow_up_at','>=',$s)->get(['next_follow_up_at','lead_temperature']);
        $days=collect(range(0,6))->map(fn($i)=>$s->copy()->addDays($i));
        $daily=fn($f)=>$days->map(fn($d)=>$rows->filter(fn(ConversationControl $c)=>$c->next_follow_up_at?->isSameDay($d)&&$f($c))->count())->all();
        return [
            'overdue'=>$this->liveSeries($daily(fn(ConversationControl $c)=>$c->next_follow_up_at<now()->startOfDay())),
            'today'=>$this->liveSeries($daily(fn(ConversationControl $c)=>true)),
            'upcoming'=>$this->liveSeries($daily(fn(ConversationControl $c)=>$c->next_follow_up_at>now()->endOfDay())),
            'hot'=>$this->liveSeries($daily(fn(ConversationControl $c)=>$c->lead_temperature==='hot')),
        ];
    }


    protected function liveSeries(array $v): array
    {
        while (count($v)<7) array_unshift($v,0);
        $v=array_slice(array_map('intval',$v),-7);
        $a=$v[6]??0; $b=$v[5]??0;
        if($a===0&&$b===0){$pct=0;$dir='flat';}
        elseif($b===0){$pct=$a>0?100:0;$dir=$a>0?'up':'flat';}
        else{$pct=(int)round((($a-$b)/$b)*100);$dir=$pct>0?'up':($pct<0?'down':'flat');}
        $min=min($v);$max=max($v);$flat=$min===$max;$range=max(1,$max-$min);
        $pts=collect($v)->map(function($n,$i)use($min,$range,$flat){
            $x=4+$i*(100/6);$y=$flat?21:5+(1-(($n-$min)/$range))*32;
            return round($x,1).','.round($y,1);
        })->implode(' ');
        return ['points'=>$pts,'trend_label'=>($pct>0?'+':'').$pct.'%','trend_direction'=>$dir];
    }

    public function getHeading(): string
    {
        return '';
    }

    public function getSubheading(): ?string
    {
        return null;
    }
}