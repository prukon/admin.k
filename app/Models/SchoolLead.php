<?php

namespace App\Models;

use App\Enums\SchoolLeadSource;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class SchoolLead extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $table = 'school_leads';

    protected $guarded = [];

    protected $casts = [
        'source' => SchoolLeadSource::class,
        'consent_accepted_at' => 'datetime',
        'child_birthday' => 'date',
        'is_individual_traits' => 'boolean',
        'is_on_medical_register' => 'boolean',
        'is_with_disability' => 'boolean',
        'needs_contact_help' => 'boolean',
    ];

    public function schoolLeadStatus(): BelongsTo
    {
        return $this->belongsTo(SchoolLeadStatus::class, 'school_lead_status_id');
    }

    public function partner(): BelongsTo
    {
        return $this->belongsTo(Partner::class);
    }

    public function partnerWidget(): BelongsTo
    {
        return $this->belongsTo(PartnerWidget::class);
    }

    public function district(): BelongsTo
    {
        return $this->belongsTo(District::class);
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function sportType(): BelongsTo
    {
        return $this->belongsTo(SportType::class);
    }

    public function getParentFullNameAttribute(): string
    {
        return trim(collect([
            $this->parent_lastname,
            $this->parent_firstname,
            $this->parent_middlename,
        ])->filter()->implode(' '));
    }

    public function getChildFullNameAttribute(): string
    {
        return trim(collect([
            $this->child_lastname,
            $this->child_firstname,
            $this->child_middlename,
        ])->filter()->implode(' '));
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return array{lastname: string, firstname: string, middlename: string}
     */
    public function resolvedParentNameParts(): array
    {
        if ($this->parent_lastname || $this->parent_firstname || $this->parent_middlename) {
            return [
                'lastname'   => (string) ($this->parent_lastname ?? ''),
                'firstname'  => (string) ($this->parent_firstname ?? ''),
                'middlename' => (string) ($this->parent_middlename ?? ''),
            ];
        }

        $fallbackName = $this->parent_full_name !== '' ? $this->parent_full_name : (string) $this->name;

        return self::splitFullName($fallbackName);
    }

    /**
     * @return array{lastname: string, firstname: string, middlename: string}
     */
    public function resolvedChildNameParts(): array
    {
        if ($this->child_lastname || $this->child_firstname || $this->child_middlename) {
            return [
                'lastname'   => (string) ($this->child_lastname ?? ''),
                'firstname'  => (string) ($this->child_firstname ?? ''),
                'middlename' => (string) ($this->child_middlename ?? ''),
            ];
        }

        if ($this->child_full_name !== '') {
            return self::splitFullName($this->child_full_name);
        }

        return [
            'lastname'   => '',
            'firstname'  => '',
            'middlename' => '',
        ];
    }

    /**
     * @return array{lastname: string, firstname: string, middlename: string}
     */
    public static function splitFullName(?string $fullName): array
    {
        $fullName = trim(preg_replace('/\s+/u', ' ', (string) $fullName));

        if ($fullName === '') {
            return [
                'lastname'   => '',
                'firstname'  => '',
                'middlename' => '',
            ];
        }

        $parts = explode(' ', $fullName);

        if (count($parts) === 1) {
            return [
                'lastname'   => '',
                'firstname'  => $parts[0],
                'middlename' => '',
            ];
        }

        if (count($parts) === 2) {
            return [
                'lastname'   => $parts[0],
                'firstname'  => $parts[1],
                'middlename' => '',
            ];
        }

        return [
            'lastname'   => array_shift($parts),
            'firstname'  => array_shift($parts),
            'middlename' => implode(' ', $parts),
        ];
    }
}
