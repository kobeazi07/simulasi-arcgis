<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ArcGISConnection extends Model
{
    protected $table = 'arcgis_connections';

    // protected $fillable = [
    //     'username',
    //     'access_token',
    //     'refresh_token',
    //     'expires_at',
    // ];

    // protected $casts = [
    //     'expires_at' => 'datetime',
    // ];

    protected $fillable = [
        'username',
        'access_token',
        'refresh_token',
        'expires_at',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
    ];
}
