"""
Schemas Pydantic para validação de dados de entrada e saída da API.
"""
from pydantic import BaseModel
from typing import Optional
from datetime import datetime


# ─── Clientes ────────────────────────────────────────────────────────────────

class ClienteCreate(BaseModel):
    nome: str


class ClienteResponse(BaseModel):
    id: int
    nome: str
    criado_em: Optional[str] = None


# ─── Softwares ────────────────────────────────────────────────────────────────

class SoftwareCreate(BaseModel):
    nome: str
    git_url: Optional[str] = None
    tecnologia: Optional[str] = None


class SoftwareResponse(BaseModel):
    id: int
    nome: str
    git_url: Optional[str] = None
    tecnologia: Optional[str] = None
    criado_em: Optional[str] = None


# ─── Instâncias de Cliente ────────────────────────────────────────────────────

class InstanciaCreate(BaseModel):
    cliente_id: int
    software_id: int
    git_custom_url: Optional[str] = None
    branch: str = "master"


class InstanciaResponse(BaseModel):
    id: int
    cliente_id: int
    software_id: int
    cliente_nome: Optional[str] = None
    software_nome: Optional[str] = None
    git_custom_url: Optional[str] = None
    branch: str
    criado_em: Optional[str] = None


# ─── Chat IA ──────────────────────────────────────────────────────────────────

class ChatRequest(BaseModel):
    mensagem: str


class ChatResponse(BaseModel):
    resposta: str
    tipo: Optional[str] = None  # "consulta", "cadastro", "analise", "erro"
