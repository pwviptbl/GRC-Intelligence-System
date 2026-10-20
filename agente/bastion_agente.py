#!/usr/bin/env python3
"""Agente do Bastion: busca os alvos, roda o Nuclei num perfil conservador e envia o resultado.

Configuração por variável de ambiente:
  BASTION_URL           ex.: http://192.168.1.19:8088
  BASTION_AGENTE_TOKEN  token gerado em Administração > Agentes

Uso:
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
import urllib.error
import urllib.request
import uuid

VERSAO = "bastion-agente/1.0"

# Perfil conservador: nada de DoS, fuzzing, templates intrusivos ou tentativa de login;
# sem interactsh (nenhum callback para servidor externo) e com limite de requisições.
TAGS_EXCLUIDAS = "dos,fuzz,fuzzing,intrusive,bruteforce,brute-force,default-login,headless,osint"
SEVERIDADES = "low,medium,high,critical"


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


def enviar(base, token, alvo_id, caminho, ferramenta="nuclei"):
    with open(caminho, "rb") as f:
        corpo, tipo = multipart({"ferramenta": ferramenta}, os.path.basename(caminho), f.read())
    return api(base, token, "POST", f"/api/agente/v1/alvos/{alvo_id}/resultados", corpo, tipo)


def comando_nuclei(lista_urls, saida, args):
    return [
        args.nuclei, "-l", lista_urls, "-jsonl", "-o", saida, "-silent", "-no-color",
        "-severity", SEVERIDADES, "-etags", TAGS_EXCLUIDAS, "-ni",
        "-rl", str(args.rate_limit), "-c", str(args.concorrencia), "-timeout", "10",
    ]


def main():
    p = argparse.ArgumentParser(description="Agente de varredura do Bastion")
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

    if args.enviar:
        status, resp = enviar(base, token, int(args.enviar[0]), args.enviar[1])
        print(status, json.dumps(resp, ensure_ascii=False))
        return 0 if status == 201 else 1

    status, resp = api(base, token, "GET", "/api/agente/v1/alvos")
    if status != 200:
        sys.exit(f"Falha ao buscar alvos ({status}): {resp.get('message')}")

    alvos = [a for a in resp["alvos"] if not args.alvo or a["id"] in args.alvo]
    print(f"{len(alvos)} alvo(s) de {resp['total']} para o agente {resp['agente']}")

    if not args.dry_run and not shutil.which(args.nuclei):
        sys.exit(f"Nuclei não encontrado ({args.nuclei}).")

    falhas = 0
    for alvo in alvos:
        rotulo = f"#{alvo['id']} {alvo['organizacao']} | {alvo['software']} | {alvo['host']}"
        if not alvo["urls"]:
            print(f"{rotulo}: sem porta web, pulando")
            continue
        with tempfile.TemporaryDirectory(prefix="bastion-") as tmp:
            lista = os.path.join(tmp, "urls.txt")
            saida = os.path.join(tmp, f"nuclei-{alvo['id']}.jsonl")
            with open(lista, "w") as f:
                f.write("\n".join(alvo["urls"]) + "\n")
            cmd = comando_nuclei(lista, saida, args)
            if args.dry_run:
                print(f"{rotulo}: {' '.join(alvo['urls'])}")
                continue
            print(f"{rotulo}: varrendo {len(alvo['urls'])} URL(s)")
            proc = subprocess.run(cmd, stdout=subprocess.DEVNULL, stderr=subprocess.PIPE, text=True)
            if proc.returncode != 0:
                # Varredura com erro não é enviada: um resultado incompleto fecharia achados que continuam lá
                print(f"  nuclei falhou ({proc.returncode}): {proc.stderr.strip()[-300:]}")
                falhas += 1
                continue
            open(saida, "a").close()
            status, resp = enviar(base, token, alvo["id"], saida)
            if status == 201:
                print(f"  enviado: {resp['novos']} novo(s), {resp['repetidos']} repetido(s), "
                      f"{resp['reabertos']} reaberto(s), {resp['fechados']} fechado(s)")
            else:
                print(f"  envio falhou ({status}): {resp.get('message')}")
                falhas += 1

    return 1 if falhas else 0


if __name__ == "__main__":
    sys.exit(main())
