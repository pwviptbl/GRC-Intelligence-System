#!/usr/bin/env python3
from __future__ import annotations

import os
from typing import Any

import uvicorn
from mcp.server.fastmcp import FastMCP
from mcp.types import ToolAnnotations

from host_agent import HostAgent


HOST = os.getenv("HOST_MCP_HOST", "127.0.0.1")
PORT = int(os.getenv("HOST_MCP_PORT", "8765"))
TOKEN = os.getenv("HOST_MCP_TOKEN", "")

mcp = FastMCP(
    name="DBSeller Host Agent",
    instructions=(
        "Opera o computador Linux onde o servidor esta executando. Use run_command para tarefas curtas "
        "e start_command/poll_command para testes, builds e analises demoradas."
    ),
    host=HOST,
    port=PORT,
    streamable_http_path="/mcp",
    stateless_http=True,
    json_response=True,
)
agent = HostAgent()

READ_ONLY = ToolAnnotations(readOnlyHint=True, destructiveHint=False)
WRITE = ToolAnnotations(readOnlyHint=False, destructiveHint=True)


@mcp.tool(annotations=WRITE)
def run_command(command: str, cwd: str | None = None, timeout: int = 120, output_limit: int = 200_000) -> dict[str, Any]:
    """Executa um comando shell no host e aguarda o resultado."""
    return agent.run_command(command, cwd, timeout, output_limit)


@mcp.tool(annotations=WRITE)
def start_command(command: str, cwd: str | None = None) -> dict[str, Any]:
    """Inicia um comando longo em background e retorna um job_id."""
    return agent.start_command(command, cwd)


@mcp.tool(annotations=READ_ONLY)
def poll_command(job_id: str, output_limit: int = 200_000) -> dict[str, Any]:
    """Consulta estado e saida atual de um comando em background."""
    return agent.poll_command(job_id, output_limit)


@mcp.tool(annotations=WRITE)
def stop_command(job_id: str) -> dict[str, Any]:
    """Interrompe um comando em background."""
    return agent.stop_command(job_id)


@mcp.tool(annotations=READ_ONLY)
def read_file(path: str, offset: int = 0, limit: int = 200_000) -> dict[str, Any]:
    """Le um trecho de arquivo textual do host."""
    return agent.read_file(path, offset, limit)


@mcp.tool(annotations=WRITE)
def write_file(path: str, content: str, append: bool = False) -> dict[str, Any]:
    """Cria, substitui ou acrescenta texto em um arquivo do host."""
    return agent.write_file(path, content, append)


@mcp.tool(annotations=READ_ONLY)
def list_directory(path: str, recursive: bool = False, limit: int = 1000) -> dict[str, Any]:
    """Lista arquivos e diretorios do host."""
    return agent.list_directory(path, recursive, limit)


@mcp.tool(annotations=READ_ONLY)
def stat_path(path: str) -> dict[str, Any]:
    """Retorna metadados de arquivo ou diretorio no host."""
    return agent.stat_path(path)


@mcp.tool(annotations=READ_ONLY)
def find_files(path: str, pattern: str = "*", limit: int = 1000) -> dict[str, Any]:
    """Localiza arquivos e diretorios por padrao de nome."""
    return agent.find_files(path, pattern, limit)


@mcp.tool(annotations=READ_ONLY)
def search_text(query: str, path: str, glob: str | None = None, limit: int = 500) -> dict[str, Any]:
    """Pesquisa texto em arquivos usando ripgrep."""
    return agent.search_text(query, path, glob, limit)


class BearerTokenMiddleware:
    def __init__(self, app: Any, token: str) -> None:
        self.app = app
        self.token = token

    async def __call__(self, scope: dict[str, Any], receive: Any, send: Any) -> None:
        if self.token and scope.get("type") == "http":
            headers = {key.lower(): value for key, value in scope.get("headers", [])}
            expected = f"Bearer {self.token}".encode()
            if headers.get(b"authorization") != expected:
                await send({"type": "http.response.start", "status": 401, "headers": [(b"content-type", b"application/json")]})
                await send({"type": "http.response.body", "body": b'{"error":"unauthorized"}'})
                return
        await self.app(scope, receive, send)


app = BearerTokenMiddleware(mcp.streamable_http_app(), TOKEN)


if __name__ == "__main__":
    uvicorn.run(app, host=HOST, port=PORT, log_level=os.getenv("HOST_MCP_LOG_LEVEL", "info"))
