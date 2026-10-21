#!/usr/bin/env python3
"""Agente do Bastion: busca os alvos, roda o Nuclei num perfil conservador e envia o resultado.

Configuração por variável de ambiente:
  BASTION_URL           ex.: http://192.168.1.19:8088
  BASTION_AGENTE_TOKEN  token gerado em Administração > Agentes

Uso:
  bastion_agente.py --fila               fica no ar consultando a fila do painel (manual ou agendada)
  bastion_agente.py --fila --uma-vez     consulta a fila uma vez e sai
  bastion_agente.py --dry-run            lista os alvos e o comando, sem varrer
  bastion_agente.py                      varre todos os alvos
  bastion_agente.py --alvo 10 --alvo 31  varre só esses ambientes
  bastion_agente.py --enviar 10 arq.jsonl  envia um resultado já existente
"""
import argparse
import json
import os
import shutil
import subprocess
import sys
import tempfile
import time
import urllib.error
import urllib.request
import uuid

VERSAO = "bastion-agente/1.1"

# Perfil conservador: nada de DoS, fuzzing, templates intrusivos ou tentativa de login;
# sem interactsh (nenhum callback para servidor externo) e com limite de requisições.
TAGS_EXCLUIDAS = "dos,fuzz,fuzzing,intrusive,bruteforce,brute-force,default-login,headless,osint"
SEVERIDADES = "low,medium,high,critical"


def log(msg):
    print(time.strftime("%Y-%m-%d %H:%M:%S"), msg, flush=True)


def api(base, token, metodo, caminho, corpo=None, tipo=None):
    req = urllib.request.Request(base.rstrip("/") + caminho, data=corpo, method=metodo)
    req.add_header("Authorization", "Bearer " + token)
    req.add_header("Accept", "application/json")
    req.add_header("X-Agente-Versao", VERSAO)
    if tipo:
        req.add_header("Content-Type", tipo)
    try:
        with urllib.request.urlopen(req, timeout=120) as resp:
            return resp.status, json.loads(resp.read() or b"{}")
    except urllib.error.HTTPError as e:
        try:
            return e.code, json.loads(e.read() or b"{}")
        except ValueError:
            return e.code, {"message": "resposta não JSON"}


def multipart(campos, nome_arquivo, conteudo):
    limite = uuid.uuid4().hex
    partes = []
    for chave, valor in campos.items():
        partes.append(f'--{limite}\r\nContent-Disposition: form-data; name="{chave}"\r\n\r\n{valor}\r\n'.encode())
    partes.append(
        f'--{limite}\r\nContent-Disposition: form-data; name="arquivo"; filename="{nome_arquivo}"\r\n'
        f"Content-Type: application/octet-stream\r\n\r\n".encode() + conteudo + b"\r\n"
    )
    partes.append(f"--{limite}--\r\n".encode())
    return b"".join(partes), "multipart/form-data; boundary=" + limite


def enviar(base, token, alvo_id, caminho, ferramenta="nuclei", execucao_id=None):
    campos = {"ferramenta": ferramenta}
    if execucao_id:
        campos["execucao_id"] = execucao_id
    with open(caminho, "rb") as f:
        corpo, tipo = multipart(campos, os.path.basename(caminho), f.read())
    return api(base, token, "POST", f"/api/agente/v1/alvos/{alvo_id}/resultados", corpo, tipo)


def comando_nuclei(lista_urls, saida, args):
    return [
        args.nuclei, "-l", lista_urls, "-jsonl", "-o", saida, "-silent", "-no-color",
        "-severity", SEVERIDADES, "-etags", TAGS_EXCLUIDAS, "-ni",
        "-rl", str(args.rate_limit), "-c", str(args.concorrencia), "-timeout", "10",
    ]


def varrer_alvo(base, token, alvo, args, execucao_id=None):
    """Varre um alvo e envia o resultado. Devolve (ok, mensagem)."""
    rotulo = f"#{alvo['id']} {alvo['organizacao']} | {alvo['software']} | {alvo['host']}"
    if not alvo["urls"]:
        log(f"{rotulo}: sem porta web, pulando")
        return True, "sem porta web"

    with tempfile.TemporaryDirectory(prefix="bastion-") as tmp:
        lista = os.path.join(tmp, "urls.txt")
        saida = os.path.join(tmp, f"nuclei-{alvo['id']}.jsonl")
        with open(lista, "w") as f:
            f.write("\n".join(alvo["urls"]) + "\n")

        if args.dry_run:
            log(f"{rotulo}: {' '.join(alvo['urls'])}")
            return True, "dry-run"

        log(f"{rotulo}: varrendo {len(alvo['urls'])} URL(s)")
        proc = subprocess.run(comando_nuclei(lista, saida, args), stdout=subprocess.DEVNULL, stderr=subprocess.PIPE, text=True)
        if proc.returncode != 0:
            # Varredura com erro não é enviada: um resultado incompleto fecharia achados que continuam lá
            msg = f"nuclei falhou ({proc.returncode}): {proc.stderr.strip()[-300:]}"
            log(f"  {msg}")
            return False, msg

        open(saida, "a").close()
        status, resp = enviar(base, token, alvo["id"], saida, execucao_id=execucao_id)
        if status == 201:
            log(f"  enviado: {resp['novos']} novo(s), {resp['repetidos']} repetido(s), "
                f"{resp['reabertos']} reaberto(s), {resp['fechados']} fechado(s)")
            return True, "ok"

        msg = f"envio falhou ({status}): {resp.get('message')}"
        log(f"  {msg}")
        return False, msg


def processar_fila(base, token, args):
    """Busca a próxima execução no painel e a executa. Devolve True se havia execução."""
    status, resp = api(base, token, "GET", "/api/agente/v1/tarefa")
    if status != 200:
        log(f"Falha ao consultar a fila ({status}): {resp.get('message')}")
        return False

    execucao = resp.get("execucao")
    if not execucao:
        return False

    eid = execucao["id"]
    alvos = execucao["alvos"]
    log(f"Execução #{eid} ({execucao['origem']}): {len(alvos)} alvo(s)")

    falhas = []
    cancelada = False
    for alvo in alvos:
        # Antes de cada alvo, confere se o painel cancelou a execução
        status, situacao = api(base, token, "GET", f"/api/agente/v1/execucoes/{eid}")
        if status == 200 and not situacao.get("continuar", True):
            log(f"Execução #{eid} encerrada pelo painel ({situacao.get('status')}), parando")
            cancelada = True
            break

        ok, msg = varrer_alvo(base, token, alvo, args, execucao_id=eid)
        if not ok:
            falhas.append(f"#{alvo['id']} {alvo['host']}: {msg}")

    if not cancelada:
        api(
            base, token, "POST", f"/api/agente/v1/execucoes/{eid}/concluir",
            json.dumps({"sucesso": not falhas, "mensagem": "\n".join(falhas) or None}).encode(),
            "application/json",
        )
        log(f"Execução #{eid} concluída" + (f" com {len(falhas)} falha(s)" if falhas else ""))

    return True


def modo_fila(base, token, args):
    if not args.dry_run and not shutil.which(args.nuclei):
        sys.exit(f"Nuclei não encontrado ({args.nuclei}).")

    log(f"Agente {VERSAO} consultando a fila a cada {args.intervalo}s")
    while True:
        try:
            # Sem pausa entre execuções seguidas; dorme só quando a fila está vazia
            while processar_fila(base, token, args):
                pass
        except Exception as e:  # rede fora do ar não derruba o agente
            log(f"Erro: {e}")

        if args.uma_vez:
            return 0
        time.sleep(args.intervalo)


def main():
    p = argparse.ArgumentParser(description="Agente de varredura do Bastion")
    p.add_argument("--fila", action="store_true", help="consulta a fila do painel e executa os pedidos")
    p.add_argument("--uma-vez", action="store_true", help="com --fila: consulta uma vez e sai")
    p.add_argument("--intervalo", type=int, default=30, help="com --fila: segundos entre consultas (padrão 30)")
    p.add_argument("--dry-run", action="store_true", help="só lista os alvos e o comando")
    p.add_argument("--alvo", type=int, action="append", help="id do ambiente (pode repetir)")
    p.add_argument("--enviar", nargs=2, metavar=("ALVO_ID", "ARQUIVO"), help="envia um resultado já existente")
    p.add_argument("--nuclei", default=os.environ.get("NUCLEI_BIN", "nuclei"))
    p.add_argument("--rate-limit", type=int, default=20, help="requisições por segundo (padrão 20)")
    p.add_argument("--concorrencia", type=int, default=5)
    args = p.parse_args()

    base = os.environ.get("BASTION_URL")
    token = os.environ.get("BASTION_AGENTE_TOKEN")
    if not base or not token:
        sys.exit("Defina BASTION_URL e BASTION_AGENTE_TOKEN.")

    if args.fila:
        return modo_fila(base, token, args)

    if args.enviar:
        status, resp = enviar(base, token, int(args.enviar[0]), args.enviar[1])
        print(status, json.dumps(resp, ensure_ascii=False))
        return 0 if status == 201 else 1

    status, resp = api(base, token, "GET", "/api/agente/v1/alvos")
    if status != 200:
        sys.exit(f"Falha ao buscar alvos ({status}): {resp.get('message')}")

    alvos = [a for a in resp["alvos"] if not args.alvo or a["id"] in args.alvo]
    log(f"{len(alvos)} alvo(s) de {resp['total']} para o agente {resp['agente']}")

    if not args.dry_run and not shutil.which(args.nuclei):
        sys.exit(f"Nuclei não encontrado ({args.nuclei}).")

    falhas = 0
    for alvo in alvos:
        ok, _ = varrer_alvo(base, token, alvo, args)
        falhas += 0 if ok else 1

    return 1 if falhas else 0


if __name__ == "__main__":
    sys.exit(main())
