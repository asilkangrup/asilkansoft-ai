<x-filament-panels::page>

    <style>
        .team-page {
            display: flex;
            flex-direction: column;
            gap: 22px;
        }

        .team-hero {
            display: grid;
            grid-template-columns:
                minmax(0, 1.6fr)
                minmax(280px, .7fr);
            gap: 18px;
        }

        .team-card {
            border-radius: 22px;
            border: 1px solid rgba(148, 163, 184, .18);
            background: #fff;
            box-shadow: 0 10px 35px rgba(15, 23, 42, .05);
        }

        .team-org-card {
            padding: 24px;
        }

        .team-org-card h2 {
            margin: 0;
            font-size: 24px;
            font-weight: 900;
            color: #0f172a;
        }

        .team-org-card p {
            margin: 8px 0 0;
            color: #64748b;
            font-size: 14px;
        }

        .team-seat-card {
            padding: 22px;
            background:
                linear-gradient(
                    135deg,
                    #111827,
                    #1e293b
                );
            color: #fff;
        }

        .team-seat-card small {
            opacity: .75;
            font-weight: 700;
        }

        .team-seat-card strong {
            display: block;
            margin-top: 8px;
            font-size: 30px;
            font-weight: 900;
        }

        .team-progress {
            width: 100%;
            height: 9px;
            margin-top: 18px;
            border-radius: 999px;
            background: rgba(255,255,255,.14);
            overflow: hidden;
        }

        .team-progress > span {
            display: block;
            height: 100%;
            border-radius: inherit;
            background: #22c55e;
        }

        .team-actions {
            display: flex;
            justify-content: flex-end;
        }

        .team-btn {
            border: 0;
            border-radius: 12px;
            padding: 11px 16px;
            font-weight: 800;
            cursor: pointer;
        }

        .team-btn-primary {
            background: #2563eb;
            color: #fff;
        }

        .team-btn-soft {
            background: #f1f5f9;
            color: #334155;
        }

        .team-form {
            padding: 22px;
        }

        .team-form-grid {
            display: grid;
            grid-template-columns:
                repeat(2, minmax(0, 1fr));
            gap: 14px;
        }

        .team-field label {
            display: block;
            margin-bottom: 7px;
            font-size: 12px;
            font-weight: 800;
            color: #475569;
        }

        .team-field input,
        .team-field select {
            width: 100%;
            border: 1px solid #dbe3ef;
            border-radius: 12px;
            padding: 11px 12px;
            background: #fff;
            color: #0f172a;
        }

        .team-form-actions {
            display: flex;
            gap: 10px;
            justify-content: flex-end;
            margin-top: 16px;
        }

        .team-table-wrap {
            overflow-x: auto;
        }

        .team-table {
            width: 100%;
            min-width: 950px;
            border-collapse: collapse;
        }

        .team-table th {
            text-align: left;
            padding: 14px 16px;
            background: #f8fafc;
            font-size: 12px;
            color: #475569;
            font-weight: 800;
            border-bottom: 1px solid #e2e8f0;
        }

        .team-table td {
            padding: 15px 16px;
            border-bottom: 1px solid #eef2f7;
            color: #334155;
            font-size: 13px;
        }

        .team-member {
            display: flex;
            flex-direction: column;
            gap: 3px;
        }

        .team-member strong {
            color: #0f172a;
            font-size: 14px;
        }

        .team-member span {
            color: #94a3b8;
            font-size: 12px;
        }

        .team-badge {
            display: inline-flex;
            align-items: center;
            border-radius: 999px;
            padding: 5px 9px;
            font-size: 11px;
            font-weight: 800;
        }

        .team-badge-active {
            background: rgba(34,197,94,.12);
            color: #15803d;
        }

        .team-badge-inactive {
            background: rgba(148,163,184,.15);
            color: #64748b;
        }

        .team-role-select {
            min-width: 160px;
            border: 1px solid #dbe3ef;
            border-radius: 10px;
            padding: 8px 10px;
            background: #fff;
            color: #0f172a;
        }

        .team-empty {
            padding: 45px 20px;
            text-align: center;
            color: #64748b;
        }

        .team-admin-selector {
            padding: 20px;
        }

        .team-admin-selector-inner {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
            flex-wrap: wrap;
        }

        .team-admin-kicker {
            font-size: 12px;
            font-weight: 800;
            color: #64748b;
            margin-bottom: 5px;
        }

        .team-admin-title {
            font-size: 18px;
            font-weight: 900;
            color: #0f172a;
        }

        .team-admin-select-wrap {
            min-width: 320px;
            flex: 0 1 420px;
        }

        .team-admin-select {
            width: 100%;
            min-width: 320px;
            padding: 11px 12px;
            border: 1px solid #dbe3ef;
            border-radius: 12px;
            background: #fff;
            color: #0f172a;
            font-weight: 700;
        }

        @media (max-width: 900px) {
            .team-hero {
                grid-template-columns: 1fr;
            }

            .team-form-grid {
                grid-template-columns: 1fr;
            }

            .team-admin-select-wrap {
                min-width: 100%;
                width: 100%;
            }

            .team-admin-select {
                min-width: 100%;
            }
        }
    </style>

    @php
        $organization = $this->organization;
        $members = $this->members;
        $seat = $this->seatUsage;
    @endphp

    <div class="team-page">

        @if(auth()->user()?->is_admin)

            <section class="team-card team-admin-selector">

                <div class="team-admin-selector-inner">

                    <div>

                        <div class="team-admin-kicker">
                            Sistem Admini
                        </div>

                        <div class="team-admin-title">
                            İşletme Seç
                        </div>

                    </div>

                    <div class="team-admin-select-wrap">

                        <select
                            class="team-admin-select"
                            wire:change="
                                selectOrganization(
                                    $event.target.value
                                )
                            "
                        >

                            @foreach(
                                $this->adminOrganizations
                                as $adminOrganization
                            )

                                <option
                                    value="{{ $adminOrganization->id }}"
                                    @selected(
                                        $organization?->id
                                        ===
                                        $adminOrganization->id
                                    )
                                >
                                    {{ $adminOrganization->name }}

                                    @if($adminOrganization->owner)
                                        —
                                        {{ $adminOrganization->owner->email }}
                                    @endif
                                </option>

                            @endforeach

                        </select>

                    </div>

                </div>

            </section>

        @endif

        @if(!$organization)

            <div class="team-card team-empty">
                Bu hesap için aktif organizasyon bulunamadı.
            </div>

        @else

            <section class="team-hero">

                <article class="team-card team-org-card">

                    <h2>
                        {{ $organization->name }}
                    </h2>

                    <p>
                        Paket:
                        <strong>
                            {{ strtoupper($organization->plan) }}
                        </strong>
                        ·
                        Ekip üyelerinizi ve erişim rollerini buradan yönetin.
                    </p>

                </article>

                <article class="team-card team-seat-card">

                    <small>
                        Kullanılan Koltuk
                    </small>

                    <strong>
                        {{ $seat['used'] }}
                        /
                        {{ $seat['limit'] }}
                    </strong>

                    <div class="team-progress">

                        <span
                            style="
                                width:
                                {{ $seat['percent'] }}%;
                            "
                        ></span>

                    </div>

                    <small style="margin-top: 10px; display:block;">
                        {{ $seat['remaining'] }}
                        boş koltuk
                    </small>

                </article>

            </section>

            <div class="team-actions">

                <button
                    type="button"
                    class="team-btn team-btn-primary"
                    wire:click="$toggle('showCreateForm')"
                >
                    + Çalışan Ekle
                </button>

            </div>

            @if($showCreateForm)

                <section class="team-card team-form">

                    <div class="team-form-grid">

                        <div class="team-field">

                            <label>
                                Ad Soyad
                            </label>

                            <input
                                type="text"
                                wire:model="name"
                                placeholder="Örn. Ahmet Yılmaz"
                            >

                            @error('name')
                                <div
                                    style="
                                        color:#dc2626;
                                        font-size:12px;
                                        margin-top:5px;
                                    "
                                >
                                    {{ $message }}
                                </div>
                            @enderror

                        </div>

                        <div class="team-field">

                            <label>
                                E-posta
                            </label>

                            <input
                                type="email"
                                wire:model="email"
                                placeholder="ahmet@firma.com"
                            >

                            @error('email')
                                <div
                                    style="
                                        color:#dc2626;
                                        font-size:12px;
                                        margin-top:5px;
                                    "
                                >
                                    {{ $message }}
                                </div>
                            @enderror

                        </div>

                        <div class="team-field">

                            <label>
                                Şifre
                            </label>

                            <input
                                type="password"
                                wire:model="password"
                                autocomplete="new-password"
                                placeholder="En az 8 karakter"
                            >

                            @error('password')
                                <div
                                    style="
                                        color:#dc2626;
                                        font-size:12px;
                                        margin-top:5px;
                                    "
                                >
                                    {{ $message }}
                                </div>
                            @enderror

                        </div>

                        <div class="team-field">

                            <label>
                                Şifre Tekrar
                            </label>

                            <input
                                type="password"
                                wire:model="passwordConfirmation"
                                autocomplete="new-password"
                                placeholder="Şifreyi tekrar girin"
                            >

                        </div>

                        <div class="team-field">

                            <label>
                                Rol
                            </label>

                            <select wire:model="role">

                                <option value="manager">
                                    Yönetici
                                </option>

                                <option value="sales">
                                    Satış Temsilcisi
                                </option>

                                <option value="support">
                                    Destek
                                </option>

                                <option value="viewer">
                                    Görüntüleyici
                                </option>

                            </select>

                        </div>

                    </div>

                    <div class="team-form-actions">

                        <button
                            type="button"
                            class="team-btn team-btn-soft"
                            wire:click="$set('showCreateForm', false)"
                        >
                            Vazgeç
                        </button>

                        <button
                            type="button"
                            class="team-btn team-btn-primary"
                            wire:click="createMember"
                        >
                            Çalışanı Kaydet
                        </button>

                    </div>

                </section>

            @endif

            <section class="team-card">

                @if($members->isEmpty())

                    <div class="team-empty">
                        Henüz ekip üyesi yok.
                    </div>

                @else

                    <div class="team-table-wrap">

                        <table class="team-table">

                            <thead>
                                <tr>
                                    <th>Çalışan</th>
                                    <th>Rol</th>
                                    <th>Durum</th>
                                    <th>Katılım</th>
                                    <th>İşlem</th>
                                </tr>
                            </thead>

                            <tbody>

                                @foreach($members as $member)

                                    <tr>

                                        <td>

                                            <div class="team-member">

                                                <strong>
                                                    {{ $member->name }}
                                                </strong>

                                                <span>
                                                    {{ $member->email }}
                                                </span>

                                            </div>

                                        </td>

                                        <td>

                                            @if(
                                                $member->pivot->role
                                                ===
                                                'owner'
                                            )

                                                <strong>
                                                    İşletme Sahibi
                                                </strong>

                                            @else

                                                <select
                                                    class="team-role-select"
                                                    wire:change="
                                                        changeRole(
                                                            {{ $member->id }},
                                                            $event.target.value
                                                        )
                                                    "
                                                >

                                                    @foreach([
                                                        'manager' => 'Yönetici',
                                                        'sales' => 'Satış Temsilcisi',
                                                        'support' => 'Destek',
                                                        'viewer' => 'Görüntüleyici',
                                                    ] as $value => $label)

                                                        <option
                                                            value="{{ $value }}"
                                                            @selected(
                                                                $member->pivot->role
                                                                ===
                                                                $value
                                                            )
                                                        >
                                                            {{ $label }}
                                                        </option>

                                                    @endforeach

                                                </select>

                                            @endif

                                        </td>

                                        <td>

                                            <span
                                                class="
                                                    team-badge
                                                    {{
                                                        $member->pivot->status
                                                        ===
                                                        'active'
                                                            ? 'team-badge-active'
                                                            : 'team-badge-inactive'
                                                    }}
                                                "
                                            >
                                                {{
                                                    $member->pivot->status
                                                    ===
                                                    'active'
                                                        ? 'Aktif'
                                                        : 'Pasif'
                                                }}
                                            </span>

                                        </td>

                                        <td>

                                            {{
                                                $member->pivot->joined_at
                                                    ? \Carbon\Carbon::parse(
                                                        $member->pivot->joined_at
                                                    )->format('d.m.Y')
                                                    : '-'
                                            }}

                                        </td>

                                        <td>

                                            @if(
                                                $member->pivot->role
                                                !==
                                                'owner'
                                            )

                                                <button
                                                    type="button"
                                                    class="team-btn team-btn-soft"
                                                    wire:click="
                                                        toggleStatus(
                                                            {{ $member->id }}
                                                        )
                                                    "
                                                >
                                                    {{
                                                        $member->pivot->status
                                                        ===
                                                        'active'
                                                            ? 'Pasif Yap'
                                                            : 'Aktifleştir'
                                                    }}
                                                </button>

                                            @else

                                                <span style="color:#94a3b8;">
                                                    -
                                                </span>

                                            @endif

                                        </td>

                                    </tr>

                                @endforeach

                            </tbody>

                        </table>

                    </div>

                @endif

            </section>

        @endif

    </div>

</x-filament-panels::page>