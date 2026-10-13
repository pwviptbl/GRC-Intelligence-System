<?php

return [
    'retention' => (int) env('BACKUP_RETENTION', 8),

    // Senha usada para criptografar (AES-256) os arquivos de backup. Sem ela o ZIP é gerado sem criptografia.
    // Guarde uma cópia fora do servidor: sem a senha, os backups não podem ser restaurados.
    'password' => env('BACKUP_PASSWORD'),
];
