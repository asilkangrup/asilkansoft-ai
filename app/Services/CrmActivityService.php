<?php

namespace App\Services;

use App\Models\ConversationControl;
use App\Models\CrmActivity;
use App\Models\User;

class CrmActivityService
{
    /*
    |--------------------------------------------------------------------------
    | GENEL AKTİVİTE KAYDI
    |--------------------------------------------------------------------------
    */

    public function log(
        ConversationControl $conversation,
        string $type,
        string $title,
        ?string $description = null,
        mixed $oldValue = null,
        mixed $newValue = null,
        ?User $performedBy = null,
        array $meta = []
    ): CrmActivity {
        return CrmActivity::create([
            'user_id' =>
                $conversation->user_id,

            'ai_bot_id' =>
                $conversation->ai_bot_id,

            'conversation_control_id' =>
                $conversation->id,

            'performed_by_user_id' =>
                $performedBy?->id,

            'type' =>
                $type,

            'title' =>
                $title,

            'description' =>
                $description,

            'old_value' =>
                $this->normalizeValue(
                    $oldValue
                ),

            'new_value' =>
                $this->normalizeValue(
                    $newValue
                ),

            'meta' =>
                $meta !== []
                    ? $meta
                    : null,
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | LEAD DURUMU DEĞİŞTİ
    |--------------------------------------------------------------------------
    */

    public function leadStatusChanged(
        ConversationControl $conversation,
        ?string $oldStatus,
        ?string $newStatus,
        ?User $performedBy = null
    ): ?CrmActivity {
        if ($oldStatus === $newStatus) {
            return null;
        }

        return $this->log(
            conversation: $conversation,
            type: $performedBy
                ? 'lead_status'
                : 'ai_status',
            title: 'Satış aşaması değiştirildi',
            description:
                $this->leadStatusLabel($oldStatus)
                .' → '
                .$this->leadStatusLabel($newStatus),
            oldValue: $oldStatus,
            newValue: $newStatus,
            performedBy: $performedBy,
        );
    }

    /*
    |--------------------------------------------------------------------------
    | LEAD PUANI DEĞİŞTİ
    |--------------------------------------------------------------------------
    */

    public function leadScoreChanged(
        ConversationControl $conversation,
        int $oldScore,
        int $newScore,
        ?User $performedBy = null
    ): ?CrmActivity {
        if ($oldScore === $newScore) {
            return null;
        }

        return $this->log(
            conversation: $conversation,
            type: $performedBy
                ? 'lead_score'
                : 'ai_score',
            title: 'Lead puanı güncellendi',
            description:
                $oldScore
                .'/100 → '
                .$newScore
                .'/100',
            oldValue: $oldScore,
            newValue: $newScore,
            performedBy: $performedBy,
        );
    }

    /*
    |--------------------------------------------------------------------------
    | SICAKLIK DEĞİŞTİ
    |--------------------------------------------------------------------------
    */

    public function temperatureChanged(
        ConversationControl $conversation,
        ?string $oldTemperature,
        ?string $newTemperature,
        ?User $performedBy = null
    ): ?CrmActivity {
        if (
            $oldTemperature ===
            $newTemperature
        ) {
            return null;
        }

        return $this->log(
            conversation: $conversation,
            type: $performedBy
                ? 'lead_temperature'
                : 'ai_score',
            title: 'Lead sıcaklığı değişti',
            description:
                $this->temperatureLabel(
                    $oldTemperature
                )
                .' → '
                .$this->temperatureLabel(
                    $newTemperature
                ),
            oldValue: $oldTemperature,
            newValue: $newTemperature,
            performedBy: $performedBy,
        );
    }

    /*
    |--------------------------------------------------------------------------
    | PERSONEL ATANDI
    |--------------------------------------------------------------------------
    */

    public function assignmentChanged(
        ConversationControl $conversation,
        ?int $oldUserId,
        ?int $newUserId,
        ?User $performedBy = null
    ): ?CrmActivity {
        if ($oldUserId === $newUserId) {
            return null;
        }

        $oldName =
            $oldUserId
                ? User::query()
                    ->whereKey($oldUserId)
                    ->value('name')
                : null;

        $newName =
            $newUserId
                ? User::query()
                    ->whereKey($newUserId)
                    ->value('name')
                : null;

        return $this->log(
            conversation: $conversation,
            type: 'assignment',
            title: 'Sorumlu personel değiştirildi',
            description:
                ($oldName ?: 'Atanmamış')
                .' → '
                .($newName ?: 'Atanmamış'),
            oldValue: $oldUserId,
            newValue: $newUserId,
            performedBy: $performedBy,
            meta: [
                'old_name' =>
                    $oldName,

                'new_name' =>
                    $newName,
            ],
        );
    }

    /*
    |--------------------------------------------------------------------------
    | TAKİP TARİHİ DEĞİŞTİ
    |--------------------------------------------------------------------------
    */

    public function followUpChanged(
        ConversationControl $conversation,
        mixed $oldDate,
        mixed $newDate,
        ?User $performedBy = null
    ): ?CrmActivity {
        $old =
            $this->dateToString(
                $oldDate
            );

        $new =
            $this->dateToString(
                $newDate
            );

        if ($old === $new) {
            return null;
        }

        if ($new === null) {
            return $this->log(
                conversation: $conversation,
                type: 'follow_up',
                title: 'Takip tamamlandı',
                description:
                    $old
                        ? 'Planlanan takip kaldırıldı: '.$old
                        : 'Takip kaydı temizlendi.',
                oldValue: $old,
                newValue: null,
                performedBy: $performedBy,
            );
        }

        return $this->log(
            conversation: $conversation,
            type: 'follow_up',
            title: 'Takip tarihi güncellendi',
            description:
                ($old ?: 'Takip yok')
                .' → '
                .$new,
            oldValue: $old,
            newValue: $new,
            performedBy: $performedBy,
        );
    }

    /*
    |--------------------------------------------------------------------------
    | NOT DEĞİŞTİ
    |--------------------------------------------------------------------------
    */

    public function notesChanged(
        ConversationControl $conversation,
        ?string $oldNotes,
        ?string $newNotes,
        ?User $performedBy = null
    ): ?CrmActivity {
        $oldNotes =
            trim(
                (string) $oldNotes
            );

        $newNotes =
            trim(
                (string) $newNotes
            );

        if ($oldNotes === $newNotes) {
            return null;
        }

        return $this->log(
            conversation: $conversation,
            type: 'note',
            title: 'CRM notu güncellendi',
            description:
                $newNotes !== ''
                    ? 'Müşteri notları güncellendi.'
                    : 'Müşteri notları temizlendi.',
            oldValue: $oldNotes,
            newValue: $newNotes,
            performedBy: $performedBy,
        );
    }

    /*
    |--------------------------------------------------------------------------
    | İNSAN DEVRALDI
    |--------------------------------------------------------------------------
    */

    public function humanTakeover(
        ConversationControl $conversation,
        ?User $performedBy = null
    ): CrmActivity {
        return $this->log(
            conversation: $conversation,
            type: 'human_takeover',
            title: 'Konuşma insan tarafından devralındı',
            description:
                $performedBy
                    ? $performedBy->name
                        .' konuşmayı devraldı.'
                    : 'Konuşma insan kontrolüne geçirildi.',
            oldValue: false,
            newValue: true,
            performedBy: $performedBy,
        );
    }

    /*
    |--------------------------------------------------------------------------
    | AI'YA GERİ VERİLDİ
    |--------------------------------------------------------------------------
    */

    public function aiReleased(
        ConversationControl $conversation,
        ?User $performedBy = null
    ): CrmActivity {
        return $this->log(
            conversation: $conversation,
            type: 'ai_release',
            title: 'Konuşma yapay zekâya geri verildi',
            description:
                $performedBy
                    ? $performedBy->name
                        .' konuşmayı WAI\'ye geri verdi.'
                    : 'Konuşma yapay zekâ kontrolüne geri verildi.',
            oldValue: true,
            newValue: false,
            performedBy: $performedBy,
        );
    }

    /*
    |--------------------------------------------------------------------------
    | SATIŞ KAZANILDI
    |--------------------------------------------------------------------------
    */

    public function won(
        ConversationControl $conversation,
        ?User $performedBy = null
    ): CrmActivity {
        return $this->log(
            conversation: $conversation,
            type: 'won',
            title: 'Satış kazanıldı',
            description:
                'Müşteri kazanıldı olarak işaretlendi.',
            oldValue: null,
            newValue: 'won',
            performedBy: $performedBy,
        );
    }

    /*
    |--------------------------------------------------------------------------
    | SATIŞ KAYBEDİLDİ
    |--------------------------------------------------------------------------
    */

    public function lost(
        ConversationControl $conversation,
        ?string $reason = null,
        ?User $performedBy = null
    ): CrmActivity {
        return $this->log(
            conversation: $conversation,
            type: 'lost',
            title: 'Satış kaybedildi',
            description:
                $reason
                    ? 'Neden: '.trim($reason)
                    : 'Müşteri kaybedildi olarak işaretlendi.',
            oldValue: null,
            newValue: 'lost',
            performedBy: $performedBy,
            meta: [
                'reason' =>
                    $reason,
            ],
        );
    }

    /*
    |--------------------------------------------------------------------------
    | YARDIMCILAR
    |--------------------------------------------------------------------------
    */

    protected function normalizeValue(
        mixed $value
    ): ?string {
        if ($value === null) {
            return null;
        }

        if (is_bool($value)) {
            return $value
                ? '1'
                : '0';
        }

        if (
            is_array($value)
            || is_object($value)
        ) {
            return json_encode(
                $value,
                JSON_UNESCAPED_UNICODE
                | JSON_UNESCAPED_SLASHES
            ) ?: null;
        }

        return (string) $value;
    }

    protected function dateToString(
        mixed $date
    ): ?string {
        if (! $date) {
            return null;
        }

        try {
            return \Carbon\Carbon::parse(
                $date
            )->format(
                'd.m.Y H:i'
            );
        } catch (\Throwable) {
            return (string) $date;
        }
    }

    protected function leadStatusLabel(
        ?string $status
    ): string {
        return match ($status) {
            'contacted' =>
                'Görüşülüyor',

            'qualified' =>
                'Nitelikli',

            'proposal' =>
                'Teklif',

            'won' =>
                'Kazanıldı',

            'lost' =>
                'Kaybedildi',

            default =>
                'Yeni Lead',
        };
    }

    protected function temperatureLabel(
        ?string $temperature
    ): string {
        return match ($temperature) {
            'hot' =>
                'Sıcak',

            'warm' =>
                'Ilık',

            default =>
                'Soğuk',
        };
    }
}