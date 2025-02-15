<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StudyLevel extends Model
{
    use HasFactory;
    protected $table = 'study_levels';
    protected $primaryKey = "code";
    public $incrementing = false;
    protected $fillable = [
        'code',
        'name'
    ];
}
