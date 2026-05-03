<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class File extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'path',
        'size',
        'type',
        'status',
        'user_id',
        'extracted_text',
        'error_message',
    ];

    const STATUSES = [
        0 => 'pending',
        1 => 'processing',
        2 => 'completed',
        3 => 'failed',
    ];

    // علاقة: الملف يملك نتائج تحليل كثيرة
    public function analysisResults()
    {
        return $this->hasMany(AnalysisResult::class);
    }

    // علاقة: الملف تابع لمستخدم
    public function user()
    {
        return $this->belongsTo(User::class);
    }


    public function getStatusAttribute($value)
    {
        return self::STATUSES[$value] ?? 'unknown';
    }


    public function setStatusAttribute($value)
    {
        $statuses = array_flip(self::STATUSES);
        $this->attributes['status'] = $statuses[$value] ?? 0;
    }
}
