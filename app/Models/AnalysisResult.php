<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AnalysisResult extends Model
{
    use HasFactory;

    protected $fillable = [
        'file_id',
        'chunk_index',
        'simple_explanation',
        'key_ideas',
        'historical_context',
        'market_usage',
        'related_jobs',
        'chunk_text',
        'analyzed_at',
    ];

    protected $casts = [
        'key_ideas' => 'array',
        'related_jobs' => 'array',
    ];

    // علاقة: النتيجة تعود لملف واحد
    public function file()
    {
        return $this->belongsTo(File::class);
    }
}
