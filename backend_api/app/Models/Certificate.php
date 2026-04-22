<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Certificate extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'course_id',
        'template_id',
        'certificate_code',
        'student_name',
        'course_name',
        'final_score',
        'issued_at',
        'expiry_date',
        'download_url',
        'pdf_path',
        'verification_url',
        'issued_via',
        'metadata',
    ];

    protected $casts = [
        'issued_at' => 'datetime',
        'expiry_date' => 'date',
        'final_score' => 'decimal:2',
        'metadata' => 'array',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function course()
    {
        return $this->belongsTo(Course::class);
    }

    public function template()
    {
        return $this->belongsTo(CertificateTemplate::class, 'template_id');
    }
}
