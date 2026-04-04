"""
Serviço de integração com a API do Google Gemini 2.5 Flash Lite.
Responsável por interpretar mensagens do usuário e executar ações no banco de dados.
"""
import os
import json
import re
import google.generativeai as genai
from app.database import get_db

# ─── Configuração ─────────────────────────────────────────────────────────────

def _configurar_gemini():
    """Configura a API do Gemini com a chave do arquivo .env."""
    api_key = os.getenv("GEMINI_API_KEY")
    if not api_key or api_key == "sua_chave_api_aqui":
        raise EnvironmentError(
            "GEMINI_API_KEY não configurada. "
            "Crie um arquivo .env na raiz do projeto com sua chave."
        )
    genai.configure(api_key=api_key)
    return genai.GenerativeModel(
        model_name="gemini-2.5-flash-lite",
        system_instruction=_build_system_prompt()
    )


def _build_system_prompt() -> str:
    """
    Constrói o system prompt completo enviado ao Gemini.
    Descreve o esquema do banco, os modos de operação e o formato de resposta esperado.
    """
    return """
Você é o assistente de IA do **GRC Intelligence System**, uma ferramenta de Governança, Risco e Conformidade da DBSeller.
Seu papel é ajudar o Analista de Segurança a gerenciar clientes, softwares e suas relações, além de fornecer análises de risco.

## Banco de Dados SQLite — Esquema Completo

### Tabela: clientes
- id (INTEGER PK)
- nome (TEXT) — Ex: "Niterói", "Volta Redonda", "Resende"
- criado_em (DATETIME)

### Tabela: softwares
- id (INTEGER PK)
- nome (TEXT) — Ex: "e-Cidade 7.4", "e-Cidade 5.6", "Portal Transparência"
- git_url (TEXT) — URL do repositório Git
- tecnologia (TEXT) — Ex: "PHP 5.6", "PHP 8.2", "Java 11"
- criado_em (DATETIME)

### Tabela: instancias_cliente
- id (INTEGER PK)
- cliente_id (INTEGER FK → clientes.id)
- software_id (INTEGER FK → softwares.id)
- git_custom_url (TEXT) — Fork específico do cliente (opcional)
- branch (TEXT) — Ex: "master", "homolog", "producao", "v1.2-niteroi"
- criado_em (DATETIME)

## Modos de Operação

Você deve identificar automaticamente o modo com base na mensagem do usuário:

### MODO 1: CONSULTA (Query)
Para perguntas sobre dados existentes.
**Exemplos:** "Quais clientes usam...", "Liste os softwares...", "Quantas instâncias..."

**Formato de resposta OBRIGATÓRIO (JSON):**
```json
{
  "tipo": "consulta",
  "sql": "SELECT ... FROM ... JOIN ... WHERE ...",
  "descricao": "Breve descrição do que foi consultado"
}
```
REGRAS:
- Use APENAS SELECT. NUNCA gere INSERT/UPDATE/DELETE em modo consulta.
- Use JOINs quando necessário para trazer nomes (não apenas IDs).
- O SQL deve ser válido para SQLite 3.

### MODO 2: CADASTRO (Insert)
Para registrar novos dados.
**Exemplos:** "Adicione o cliente...", "Cadastre o software...", "Vincule o cliente X ao software Y na branch Z"

**Formato de resposta OBRIGATÓRIO (JSON):**
```json
{
  "tipo": "cadastro",
  "operacao": "insert_cliente" | "insert_software" | "insert_instancia",
  "dados": {
    "nome": "...",
    "git_url": "...",
    "tecnologia": "...",
    "cliente_nome": "...",
    "software_nome": "...",
    "branch": "...",
    "git_custom_url": "..."
  },
  "descricao": "Confirmação do que será cadastrado"
}
```
REGRAS:
- Para insert_instancia, use cliente_nome e software_nome (o sistema vai resolver os IDs).
- Inclua APENAS os campos relevantes na chave "dados".

### MODO 3: ANÁLISE DE RISCO (Analysis)
Para pedidos de recomendação, avaliação de risco ou sugestões estratégicas.
**Exemplos:** "Com base nos softwares desatualizados...", "Qual o risco de...", "O que devo priorizar..."

**Formato de resposta OBRIGATÓRIO (JSON):**
```json
{
  "tipo": "analise",
  "sql_contexto": "SELECT opcional para buscar dados relevantes antes de analisar",
  "analise": "Sua análise completa em Markdown, com recomendações priorizadas"
}
```

### MODO 4: CONVERSA GERAL
Para saudações, dúvidas sobre o sistema, ou mensagens que não se encaixam nos modos acima.

**Formato de resposta OBRIGATÓRIO (JSON):**
```json
{
  "tipo": "geral",
  "resposta": "Sua resposta em Markdown"
}
```

## Regras Gerais
1. SEMPRE responda em JSON válido — nunca em texto puro.
2. NUNCA invente dados que não foram fornecidos pelo usuário.
3. Seja preciso com nomes de clientes e softwares (use os exatos como fornecidos).
4. Para análises de risco, priorize recomendações baseadas em evidências concretas.
5. Responda SEMPRE em Português do Brasil.
6. Use emojis moderadamente para melhorar a legibilidade das análises.
"""


# ─── Execução das Ações ───────────────────────────────────────────────────────

def _executar_consulta(sql: str) -> str:
    """Executa um SELECT SQL e formata o resultado como Markdown."""
    # Segurança: bloqueia qualquer SQL não-SELECT
    sql_limpo = sql.strip().upper()
    if not sql_limpo.startswith("SELECT"):
        return "⚠️ Apenas consultas SELECT são permitidas neste modo."

    with get_db() as conn:
        try:
            rows = conn.execute(sql).fetchall()
        except Exception as e:
            return f"❌ Erro ao executar consulta: `{e}`"

    if not rows:
        return "Nenhum resultado encontrado para esta consulta."

    # Formata como tabela Markdown
    colunas = rows[0].keys()
    header = " | ".join(colunas)
    separador = " | ".join(["---"] * len(colunas))
    linhas = [" | ".join(str(r[c]) for c in colunas) for r in rows]

    return f"| {header} |\n| {separador} |\n" + "\n".join(f"| {l} |" for l in linhas)


def _executar_cadastro(operacao: str, dados: dict) -> str:
    """Executa a operação de INSERT com base na ação retornada pela IA."""
    with get_db() as conn:
        try:
            if operacao == "insert_cliente":
                nome = dados.get("nome", "").strip()
                conn.execute("INSERT INTO clientes (nome) VALUES (?)", (nome,))
                return f"✅ Cliente **{nome}** cadastrado com sucesso!"

            elif operacao == "insert_software":
                nome = dados.get("nome", "").strip()
                git_url = dados.get("git_url")
                tecnologia = dados.get("tecnologia")
                conn.execute(
                    "INSERT INTO softwares (nome, git_url, tecnologia) VALUES (?, ?, ?)",
                    (nome, git_url, tecnologia)
                )
                return f"✅ Software **{nome}** cadastrado com sucesso!"

            elif operacao == "insert_instancia":
                cliente_nome = dados.get("cliente_nome", "").strip()
                software_nome = dados.get("software_nome", "").strip()
                branch = dados.get("branch", "master").strip()
                git_custom_url = dados.get("git_custom_url")

                # Resolve IDs por nome (busca case-insensitive)
                cliente = conn.execute(
                    "SELECT id FROM clientes WHERE LOWER(nome) = LOWER(?)",
                    (cliente_nome,)
                ).fetchone()
                if not cliente:
                    return f"❌ Cliente **{cliente_nome}** não encontrado. Cadastre-o primeiro."

                software = conn.execute(
                    "SELECT id FROM softwares WHERE LOWER(nome) = LOWER(?)",
                    (software_nome,)
                ).fetchone()
                if not software:
                    return f"❌ Software **{software_nome}** não encontrado. Cadastre-o primeiro."

                conn.execute(
                    """INSERT INTO instancias_cliente
                       (cliente_id, software_id, git_custom_url, branch)
                       VALUES (?, ?, ?, ?)""",
                    (cliente["id"], software["id"], git_custom_url, branch)
                )
                return (
                    f"✅ Instância criada: **{cliente_nome}** → **{software_nome}** "
                    f"na branch `{branch}`"
                )

            else:
                return f"⚠️ Operação desconhecida: `{operacao}`"

        except Exception as e:
            if "UNIQUE" in str(e):
                return f"⚠️ Este registro já existe no banco de dados."
            return f"❌ Erro ao cadastrar: `{e}`"


def _executar_analise(sql_contexto: str, analise: str) -> str:
    """Executa SQL de contexto opcional e retorna a análise da IA."""
    contexto_dados = ""
    if sql_contexto and sql_contexto.strip().upper().startswith("SELECT"):
        contexto_dados = _executar_consulta(sql_contexto)
        if contexto_dados and not contexto_dados.startswith("Nenhum"):
            contexto_dados = f"\n\n**Dados analisados:**\n{contexto_dados}\n\n---\n"

    return f"{contexto_dados}{analise}"


# ─── Função Principal ─────────────────────────────────────────────────────────

def processar_mensagem(mensagem: str) -> dict:
    """
    Processa uma mensagem do usuário via Gemini e executa a ação correspondente.
    Retorna um dict com 'resposta' e 'tipo'.
    """
    try:
        modelo = _configurar_gemini()
    except EnvironmentError as e:
        return {
            "resposta": f"⚠️ **Configuração necessária:** {e}",
            "tipo": "erro"
        }

    try:
        result = modelo.generate_content(mensagem)
        texto_bruto = result.text.strip()

        # Remove blocos de código markdown se presentes (```json ... ```)
        texto_limpo = re.sub(r"```(?:json)?\s*", "", texto_bruto).replace("```", "").strip()

        resposta_ia = json.loads(texto_limpo)
        tipo = resposta_ia.get("tipo", "geral")

        if tipo == "consulta":
            sql = resposta_ia.get("sql", "")
            descricao = resposta_ia.get("descricao", "")
            resultado = _executar_consulta(sql)
            return {
                "resposta": f"**{descricao}**\n\n{resultado}",
                "tipo": "consulta"
            }

        elif tipo == "cadastro":
            operacao = resposta_ia.get("operacao", "")
            dados = resposta_ia.get("dados", {})
            descricao = resposta_ia.get("descricao", "")
            resultado = _executar_cadastro(operacao, dados)
            return {
                "resposta": f"{resultado}\n\n> {descricao}",
                "tipo": "cadastro"
            }

        elif tipo == "analise":
            sql_contexto = resposta_ia.get("sql_contexto", "")
            analise = resposta_ia.get("analise", "")
            resultado = _executar_analise(sql_contexto, analise)
            return {
                "resposta": resultado,
                "tipo": "analise"
            }

        else:  # geral
            return {
                "resposta": resposta_ia.get("resposta", texto_limpo),
                "tipo": "geral"
            }

    except json.JSONDecodeError:
        # IA retornou texto puro em vez de JSON — exibe diretamente
        return {
            "resposta": texto_bruto,
            "tipo": "geral"
        }
    except Exception as e:
        return {
            "resposta": f"❌ Erro ao processar mensagem: `{e}`",
            "tipo": "erro"
        }
