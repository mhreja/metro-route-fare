<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Station extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * Table name of the model
     *
     * @var string
     */
    protected $table = 'stations';

    /**
     * The attributes that are not mass assignable.
     *
     * @var array
     */
    protected $guarded = [];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [
        'lines' => 'array'
    ];


    public function connectedStations()
    {
        return $this->belongsToMany(Station::class, 'distances', 'from', 'to')
                    ->withPivot('distance', 'travel_time')
                    ->withTimestamps();
    }
}
