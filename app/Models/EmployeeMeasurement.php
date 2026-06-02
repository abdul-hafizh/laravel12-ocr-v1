<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EmployeeMeasurement extends Model
{
    protected $table = 'employee_measurements';

    protected $fillable = [
        'employee_id',
        'employee_name',
        'phone',
        'waist_cm',
        'weight_kg',
        'height_cm',
        'bmi',
        'selisih',
        'ket',
        'periode',
    ];

    protected $casts = [
        'periode' => 'date',
        'created_at' => 'datetime',
    ];

    public $timestamps = false;
}