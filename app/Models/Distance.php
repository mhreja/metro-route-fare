<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Distance extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * Table name of the model
     *
     * @var string
     */
    protected $table = 'distances';

    /**
     * The attributes that are not mass assignable.
     *
     * @var array
     */
    protected $guarded = [];

    public function fromStation()
    {
        return $this->belongsTo(Station::class, 'from');
    }

    public function toStation()
    {
        return $this->belongsTo(Station::class, 'to');
    }
}
