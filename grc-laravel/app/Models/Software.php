<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Software extends Model
{
    protected $table = 'software'; // Corrigindo para o nome gerado na migration pluralizada
    protected $fillable = ['nome', 'git_url', 'tecnologia'];

    public function instancias()
    {
        return $this->hasMany(InstanciaCliente::class);
    }
}
