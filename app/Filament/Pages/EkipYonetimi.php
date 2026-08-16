<?php

namespace App\Filament\Pages;

use App\Models\Organization;
use App\Models\User;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class EkipYonetimi extends Page
{
    protected static string|\BackedEnum|null $navigationIcon =
        'heroicon-o-user-group';

    protected static ?string $navigationLabel =
        'Ekip Yönetimi';

    protected static ?string $title =
        'Ekip Yönetimi';

    protected static ?string $slug =
        'ekip-yonetimi';

    protected static ?int $navigationSort =
        30;

    protected static string|\UnitEnum|null $navigationGroup =
        'CRM';

    protected string $view =
        'filament.pages.ekip-yonetimi';

    public string $name = '';

    public string $email = '';

    public string $role = 'sales';

    public bool $showCreateForm = false;

    /*
    |--------------------------------------------------------------------------
    | ERİŞİM
    |--------------------------------------------------------------------------
    */

    public static function canAccess(): bool
    {
        $user = auth()->user();

        if (! $user) {
            return false;
        }

        if ($user->is_admin) {
            return true;
        }

        return $user
            ->organizations()
            ->wherePivot(
                'status',
                'active'
            )
            ->wherePivotIn(
                'role',
                [
                    'owner',
                    'manager',
                ]
            )
            ->exists();
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canAccess();
    }

    /*
    |--------------------------------------------------------------------------
    | AKTİF ORGANİZASYON
    |--------------------------------------------------------------------------
    */

    public function getOrganizationProperty(): ?Organization
    {
        $user = auth()->user();

        if (! $user) {
            return null;
        }

        /*
        |--------------------------------------------------------------------------
        | SİSTEM ADMIN
        |--------------------------------------------------------------------------
        |
        | Admin için şimdilik sahibi olduğu ilk organizasyonu açıyoruz.
        |
        */

        if ($user->is_admin) {
            return Organization::query()
                ->where(
                    'owner_user_id',
                    $user->id
                )
                ->first()
                ?? $user
                    ->organizations()
                    ->wherePivot(
                        'status',
                        'active'
                    )
                    ->first();
        }

        return $user
            ->organizations()
            ->wherePivot(
                'status',
                'active'
            )
            ->first();
    }

    /*
    |--------------------------------------------------------------------------
    | ÜYELER
    |--------------------------------------------------------------------------
    */

    public function getMembersProperty(): Collection
    {
        $organization =
            $this->organization;

        if (! $organization) {
            return collect();
        }

        return $organization
            ->users()
            ->orderByRaw("
                CASE organization_user.role
                    WHEN 'owner' THEN 1
                    WHEN 'manager' THEN 2
                    WHEN 'sales' THEN 3
                    WHEN 'support' THEN 4
                    WHEN 'viewer' THEN 5
                    ELSE 6
                END
            ")
            ->orderBy(
                'users.name'
            )
            ->get();
    }

    /*
    |--------------------------------------------------------------------------
    | KOLTUK
    |--------------------------------------------------------------------------
    */

    public function getSeatUsageProperty(): array
    {
        $organization =
            $this->organization;

        if (! $organization) {
            return [
                'used' => 0,
                'limit' => 0,
                'remaining' => 0,
                'percent' => 0,
            ];
        }

        $used =
            $organization
                ->users()
                ->wherePivot(
                    'status',
                    'active'
                )
                ->count();

        $limit =
            max(
                1,
                (int) $organization->seat_limit
            );

        return [
            'used' =>
                $used,

            'limit' =>
                $limit,

            'remaining' =>
                max(
                    0,
                    $limit - $used
                ),

            'percent' =>
                min(
                    100,
                    (int) round(
                        ($used / $limit)
                        * 100
                    )
                ),
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | ÇALIŞAN EKLE
    |--------------------------------------------------------------------------
    */

    public function createMember(): void
    {
        $organization =
            $this->organization;

        if (! $organization) {
            Notification::make()
                ->danger()
                ->title(
                    'Organizasyon bulunamadı'
                )
                ->send();

            return;
        }

        if (
            ! auth()
                ->user()
                ?->canManageOrganization(
                    $organization->id
                )
            && ! auth()->user()?->is_admin
        ) {
            abort(403);
        }

        $seatUsage =
            $this->seatUsage;

        if (
            $seatUsage['used']
            >=
            $seatUsage['limit']
        ) {
            Notification::make()
                ->danger()
                ->title(
                    'Koltuk limiti doldu'
                )
                ->body(
                    'Yeni çalışan eklemek için paketinizi yükseltmeniz gerekiyor.'
                )
                ->send();

            return;
        }

        $data =
            validator(
                [
                    'name' =>
                        $this->name,

                    'email' =>
                        $this->email,

                    'role' =>
                        $this->role,
                ],
                [
                    'name' =>
                        [
                            'required',
                            'string',
                            'max:120',
                        ],

                    'email' =>
                        [
                            'required',
                            'email',
                            'max:190',
                        ],

                    'role' =>
                        [
                            'required',
                            'in:manager,sales,support,viewer',
                        ],
                ]
            )->validate();

        $email =
            Str::lower(
                trim(
                    $data['email']
                )
            );

        $user =
            User::query()
                ->where(
                    'email',
                    $email
                )
                ->first();

        /*
        |--------------------------------------------------------------------------
        | KULLANICI VARSA
        |--------------------------------------------------------------------------
        */

        if ($user) {
            if (
                $organization
                    ->users()
                    ->where(
                        'users.id',
                        $user->id
                    )
                    ->exists()
            ) {
                throw ValidationException::withMessages([
                    'email' =>
                        'Bu kullanıcı zaten ekibinizde.',
                ]);
            }

            /*
            |--------------------------------------------------------------------------
            | BAŞKA ORGANİZASYONDA MI?
            |--------------------------------------------------------------------------
            |
            | İlk sürümde bir çalışanı aynı anda birden fazla işletmeye bağlamıyoruz.
            |
            */

            if (
                $user
                    ->organizations()
                    ->exists()
            ) {
                throw ValidationException::withMessages([
                    'email' =>
                        'Bu e-posta başka bir işletmeye bağlı.',
                ]);
            }
        }

        /*
        |--------------------------------------------------------------------------
        | YENİ KULLANICI
        |--------------------------------------------------------------------------
        */

        if (! $user) {
            $temporaryPassword =
                Str::password(
                    length: 12
                );

            $user =
                User::create([
                    'name' =>
                        trim(
                            $data['name']
                        ),

                    'email' =>
                        $email,

                    'password' =>
                        Hash::make(
                            $temporaryPassword
                        ),

                    'is_admin' =>
                        false,
                ]);

            /*
            |--------------------------------------------------------------------------
            | GEÇİCİ ŞİFRE
            |--------------------------------------------------------------------------
            |
            | Şimdilik mail davet sistemi kurmadığımız için geçici şifreyi
            | notification içinde gösteriyoruz.
            |
            */

            Notification::make()
                ->success()
                ->title(
                    'Çalışan hesabı oluşturuldu'
                )
                ->body(
                    'Geçici şifre: '
                    .$temporaryPassword
                )
                ->persistent()
                ->send();
        }

        $organization
            ->users()
            ->attach(
                $user->id,
                [
                    'role' =>
                        $data['role'],

                    'status' =>
                        'active',

                    'joined_at' =>
                        now(),
                ]
            );

        $this->reset([
            'name',
            'email',
        ]);

        $this->role =
            'sales';

        $this->showCreateForm =
            false;

        Notification::make()
            ->success()
            ->title(
                'Çalışan eklendi'
            )
            ->send();
    }

    /*
    |--------------------------------------------------------------------------
    | ROL DEĞİŞTİR
    |--------------------------------------------------------------------------
    */

    public function changeRole(
        int $userId,
        string $role
    ): void {
        $organization =
            $this->organization;

        if (! $organization) {
            return;
        }

        if (
            ! in_array(
                $role,
                [
                    'manager',
                    'sales',
                    'support',
                    'viewer',
                ],
                true
            )
        ) {
            return;
        }

        $member =
            $organization
                ->users()
                ->where(
                    'users.id',
                    $userId
                )
                ->first();

        if (! $member) {
            return;
        }

        if (
            $member->pivot->role
            === 'owner'
        ) {
            Notification::make()
                ->warning()
                ->title(
                    'İşletme sahibi rolü değiştirilemez'
                )
                ->send();

            return;
        }

        $organization
            ->users()
            ->updateExistingPivot(
                $userId,
                [
                    'role' =>
                        $role,
                ]
            );

        Notification::make()
            ->success()
            ->title(
                'Rol güncellendi'
            )
            ->send();
    }

    /*
    |--------------------------------------------------------------------------
    | AKTİF / PASİF
    |--------------------------------------------------------------------------
    */

    public function toggleStatus(
        int $userId
    ): void {
        $organization =
            $this->organization;

        if (! $organization) {
            return;
        }

        $member =
            $organization
                ->users()
                ->where(
                    'users.id',
                    $userId
                )
                ->first();

        if (! $member) {
            return;
        }

        if (
            $member->pivot->role
            === 'owner'
        ) {
            Notification::make()
                ->warning()
                ->title(
                    'İşletme sahibi pasif yapılamaz'
                )
                ->send();

            return;
        }

        $newStatus =
            $member->pivot->status
            === 'active'
                ? 'inactive'
                : 'active';

        if (
            $newStatus
            === 'active'
        ) {
            $seatUsage =
                $this->seatUsage;

            if (
                $seatUsage['used']
                >=
                $seatUsage['limit']
            ) {
                Notification::make()
                    ->danger()
                    ->title(
                        'Koltuk limiti doldu'
                    )
                    ->send();

                return;
            }
        }

        $organization
            ->users()
            ->updateExistingPivot(
                $userId,
                [
                    'status' =>
                        $newStatus,
                ]
            );

        Notification::make()
            ->success()
            ->title(
                $newStatus === 'active'
                    ? 'Çalışan aktifleştirildi'
                    : 'Çalışan pasifleştirildi'
            )
            ->send();
    }

    /*
    |--------------------------------------------------------------------------
    | ROL ETİKETİ
    |--------------------------------------------------------------------------
    */

    public function roleLabel(
        string $role
    ): string {
        return match ($role) {
            'owner' =>
                'İşletme Sahibi',

            'manager' =>
                'Yönetici',

            'sales' =>
                'Satış Temsilcisi',

            'support' =>
                'Destek',

            'viewer' =>
                'Görüntüleyici',

            default =>
                $role,
        };
    }
}