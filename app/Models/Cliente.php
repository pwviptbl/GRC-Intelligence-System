<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Cliente extends Model
{
    protected $fillable = ['nome'];

    public function instancias()
    {
        return $this->hasMany(InstanciaCliente::class);
    }
}
