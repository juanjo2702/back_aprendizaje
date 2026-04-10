<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CertificateTemplate extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'background_image',
        'template_config',
        'is_default',
    ];

    protected $casts = [
        'template_config' => 'array',
    ];

    public function certificates()
    {
        return $this->hasMany(Certificate::class, 'template_id');
    }
}
