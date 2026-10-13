# Scripts auxiliares

## `host/`: scripts do host Proxmox

`host/grc-backup-pull.sh`, `.service` e `.timer` rodam **no host Proxmox**, fora do container. Todo dia o host busca os backups criptografados do LXC 104 e guarda em `/mnt/storage/backups/grc-intel` (disco `sda`), sem nunca sobrescrever cópias existentes. Instalação:

```bash
install -m 750 scripts/host/grc-backup-pull.sh /usr/local/sbin/
install -m 644 scripts/host/grc-backup-pull.{service,timer} /etc/systemd/system/
systemctl daemon-reload && systemctl enable --now grc-backup-pull.timer
```

Para restaurar, a senha `BACKUP_PASSWORD` é obrigatória. Há uma cópia dela em `/root/grc-backup-password.txt` no host. O procedimento completo está em `docs/RECUPERACAO_BACKUP.md`.

## Tarefas comuns (via Artisan)

Os antigos scripts Python (`popular_banco.py`, `resetar_banco.py`, `reset_senha.py`) foram substituídos por comandos do Laravel. Em produção, rode-os no container `php`; o README principal, na seção "Produção", mostra como.

| Tarefa | Comando |
|---|---|
| Dados de demonstração | `php artisan db:seed` |
| Resetar o banco com dados iniciais (pede senha) | `php artisan grc:reset` |
| Trocar a senha de um usuário | tela **Usuários** (admin) ou `php artisan tinker` |
| Gerar e verificar backup | `php artisan backups:run` / `php artisan backups:verify` |
