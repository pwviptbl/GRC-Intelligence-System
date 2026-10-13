# Recuperação de backup — GRC Intelligence System

Os backups são ZIPs criptografados com **AES-256**. **Sem a senha (`BACKUP_PASSWORD`) não há recuperação.**

## Onde estão as coisas

| Item | Local |
|---|---|
| Backups (origem) | LXC 104: `storage/app/private/backups/` (semanal, segunda 02:00, retenção de 8) |
| Backups (cópia) | Host Proxmox: `/mnt/storage/backups/grc-intel/`, no disco `sda` (cópia diária às 04:00, retenção de 180 dias) |
| Senha dos backups | `.env` do container (`BACKUP_PASSWORD`) e host `/root/grc-backup-password.txt`. **Os dois ficam no SSD NVMe.** |
| Senha (cópia obrigatória) | **Gerenciador de senhas do responsável, fora do servidor.** Sem essa cópia, uma falha do NVMe torna os backups inúteis. |

O `APP_KEY` **não** é necessário para recuperar os dados: o sistema não usa colunas criptografadas. Um ambiente novo pode gerar outro `APP_KEY`. Os únicos efeitos são que as sessões ativas caem e o login precisa ser feito de novo.

O `.env` **não** faz parte do backup. A chave do Gemini, a configuração do Auth0 e outros segredos precisam ser reconfigurados no ambiente novo.

## Opção A — pelo próprio sistema (ambiente novo instalado)

1. Instale o sistema conforme o `README.md` (composer, migrate, `AdminSeeder`).
2. No `.env`, defina `BACKUP_PASSWORD=<a senha original>` e rode `php artisan config:clear`.
3. Entre como admin → **Backups** → **Restaurar** e envie o `.zip`.
   O sistema descriptografa, restaura o banco e restaura os arquivos em `storage/app/public` e `storage/app/private`.

## Opção B — manual, sem o sistema (só para extrair os dados)

Requer o 7-Zip (`apt install 7zip`; no Windows, o 7-Zip normal). O `unzip` padrão **não** abre ZIP com AES.

```bash
7z x -p'<senha>' grc-backup-AAAAMMDD_HHMMSS.zip -o./restore
# ./restore/database.sql  -> dump PostgreSQL (pg_dump --clean --if-exists --no-owner)
# ./restore/storage/...   -> evidências, anexos e scans

psql -h <host> -U <usuario> -d grc_db -v ON_ERROR_STOP=1 -f ./restore/database.sql
cp -a ./restore/storage/app/. <projeto>/storage/app/
chown -R 1000:1000 <projeto>/storage
```

## Teste periódico de recuperação

Faça o teste a cada trimestre e sempre que mudar algo no processo de backup:

1. Extraia o backup mais recente do `sda` com o 7-Zip e a senha guardada **no gerenciador de senhas**, não a do servidor.
2. Restaure o `database.sql` em um Postgres temporário isolado (`docker run --rm --network none postgres:18-alpine`).
3. Compare a contagem de linhas de `users`, `clientes`, `findings`, `incidentes` e `controle_eventos` com a produção.

Último teste: **2026-10-13**. Backup `grc-backup-20261013_143637.zip`, restaurado com sucesso e contagens idênticas à produção.
