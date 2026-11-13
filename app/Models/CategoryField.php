<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CategoryField extends Model
{
    /** Input types an admin can pick, with the validation each one enforces. */
    public const TYPES = [
        'imei' => ['label' => 'IMEI', 'rules' => ['digits:15'], 'hint' => '15 digits'],
        'sn' => ['label' => 'Serial Number (SN)', 'rules' => ['string', 'max:64', 'regex:/^[A-Za-z0-9\-]+$/'], 'hint' => 'Letters, numbers and dashes'],
        'email' => ['label' => 'Email', 'rules' => ['email', 'max:191'], 'hint' => 'A valid email address'],
        'lock_code' => ['label' => 'Lock Code', 'rules' => ['string', 'max:64'], 'hint' => 'Up to 64 characters'],
        'text' => ['label' => 'Text', 'rules' => ['string', 'max:255'], 'hint' => 'Up to 255 characters'],
        'number' => ['label' => 'Number', 'rules' => ['numeric'], 'hint' => 'Numbers only'],
    ];

    protected $fillable = ['service_category_id', 'label', 'input_type', 'placeholder', 'is_required', 'sort_order'];

    protected function casts(): array
    {
        return ['is_required' => 'boolean'];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(ServiceCategory::class, 'service_category_id');
    }

    public function typeLabel(): string
    {
        return self::TYPES[$this->input_type]['label'] ?? ucfirst($this->input_type);
    }

    public function hint(): string
    {
        return self::TYPES[$this->input_type]['hint'] ?? '';
    }

    public function rules(): array
    {
        return array_merge(
            [$this->is_required ? 'required' : 'nullable'],
            self::TYPES[$this->input_type]['rules'] ?? ['string', 'max:255']
        );
    }
}
