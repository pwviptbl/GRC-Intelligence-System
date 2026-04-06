<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InstanciaCliente extends Model
{
    protected $fillable = ['cliente_id', 'software_id', 'git_custom_url', 'branch'];

    public function cliente()
    {
        return $this->belongsTo(Cliente::class);
    }

    public function software()
    {
        return $this->belongsTo(Software::class);
    }
}
