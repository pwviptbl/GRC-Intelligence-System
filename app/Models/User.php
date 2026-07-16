<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'nivel_operacional',
        'capacidade_semanal_horas',
        'disponivel_para_tarefas',
        'areas_atuacao',
        'active',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'active' => 'boolean',
            'disponivel_para_tarefas' => 'boolean',
            'capacidade_semanal_horas' => 'decimal:1',
        ];
    }

    public function tarefasComoExecutor()
    {
        return $this->hasMany(ControleEvento::class, 'executor_id');
    }

    public function tarefasComoRevisor()
    {
        return $this->hasMany(ControleEvento::class, 'revisor_id');
    }

    // Helper para verificar se é admin
    public function isAdmin()
    {
        return $this->role === 'admin';
    }
}
