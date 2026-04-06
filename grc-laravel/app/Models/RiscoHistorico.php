<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RiscoHistorico extends Model
{
    protected $fillable = ['risco_id', 'comentario', 'autor'];

    public function risco()
    {
        return $this->belongsTo(Risco::class);
    }
}
