from __future__ import annotations

import inspect
import json
import os
import urllib.error
import urllib.request
from typing import Any

from mcp.server.fastmcp import FastMCP
from mcp.server.transport_security import TransportSecuritySettings
from mcp.types import ToolAnnotations

PROTOCOL_VERSION = "2025-11-25"


class GrcMcpClient:
    def __init__(self, endpoint: str, token: str = "") -> None:
        self.endpoint = endpoint
        self.token = token
        self._request_id = 0

    def initialize(self) -> dict[str, Any]:
        return self._call(
            "initialize",
            {
                "protocolVersion": PROTOCOL_VERSION,
                "capabilities": {},
                "clientInfo": {
                    "name": "grc-claude-proxy",
                    "version": "1.0",
                },
            },
            include_protocol_header=False,
        )

    def list_tools(self) -> list[dict[str, Any]]:
        response = self._call("tools/list", {}, include_protocol_header=True)
        return response.get("result", {}).get("tools", [])

    def call_tool(self, name: str, arguments: dict[str, Any]) -> dict[str, Any]:
        return self._call(
            "tools/call",
            {
                "name": name,
                "arguments": arguments,
            },
            include_protocol_header=True,
        ).get("result", {})

    def _call(self, method: str, params: dict[str, Any], include_protocol_header: bool) -> dict[str, Any]:
        self._request_id += 1
        payload = json.dumps(
            {
                "jsonrpc": "2.0",
                "id": self._request_id,
                "method": method,
                "params": params,
            }
        ).encode("utf-8")

        headers = {
            "Content-Type": "application/json",
            "Accept": "application/json, text/event-stream",
        }
        if include_protocol_header:
            headers["MCP-Protocol-Version"] = PROTOCOL_VERSION
        if self.token:
            headers["Authorization"] = f"Bearer {self.token}"

        request = urllib.request.Request(self.endpoint, data=payload, headers=headers, method="POST")

        try:
            with urllib.request.urlopen(request, timeout=30) as response:
                raw = response.read().decode("utf-8")
        except urllib.error.HTTPError as error:
            body = error.read().decode("utf-8", errors="replace")
            raise RuntimeError(f"GRC MCP retornou HTTP {error.code}: {body}") from error
        except urllib.error.URLError as error:
            raise RuntimeError(f"Falha ao conectar no GRC MCP: {error}") from error

        decoded = json.loads(raw)
        if "error" in decoded:
            raise RuntimeError(f"GRC MCP erro {decoded['error'].get('code')}: {decoded['error'].get('message')}")
        return decoded


def _annotation_from_schema(schema: dict[str, Any]) -> Any:
    schema_type = schema.get("type")
    if schema_type == "integer":
        return int
    if schema_type == "number":
        return float
    if schema_type == "boolean":
        return bool
    if schema_type == "array":
        return list
    if schema_type == "object":
        return dict
    return str


def _make_tool_function(client: GrcMcpClient, tool: dict[str, Any]) -> tuple[Any, ToolAnnotations]:
    schema = tool.get("inputSchema", {})
    properties = schema.get("properties", {}) or {}
    required = set(schema.get("required", []) or [])

    parameters: list[inspect.Parameter] = []
    annotations: dict[str, Any] = {}

    for name, prop_schema in properties.items():
        annotation = _annotation_from_schema(prop_schema)
        annotations[name] = annotation
        default = inspect._empty if name in required else None
        parameters.append(
            inspect.Parameter(
                name,
                inspect.Parameter.KEYWORD_ONLY,
                default=default,
                annotation=annotation,
            )
        )

    def dynamic_tool(**kwargs: Any) -> dict[str, Any]:
        result = client.call_tool(tool["name"], kwargs)
        structured = result.get("structuredContent")
        if structured is not None:
            return structured
        return result

    dynamic_tool.__name__ = tool["name"]
    dynamic_tool.__doc__ = tool.get("description", tool["name"])
    dynamic_tool.__annotations__ = annotations
    dynamic_tool.__signature__ = inspect.Signature(parameters=parameters, return_annotation=dict[str, Any])

    description = tool.get("description", "").lower()
    read_only = "grava" not in description and "escrita" not in description and "atualiza" not in description and "cria" not in description
    tool_annotations = ToolAnnotations(readOnlyHint=read_only, destructiveHint=not read_only)

    return dynamic_tool, tool_annotations


def build_server() -> FastMCP:
    endpoint = os.getenv("GRC_PROXY_TARGET", "http://127.0.0.1:8088/mcp")
    token = os.getenv("GRC_PROXY_TARGET_TOKEN", "")
    host = os.getenv("GRC_PROXY_HOST", "127.0.0.1")
    port = int(os.getenv("GRC_PROXY_PORT", "8775"))

    client = GrcMcpClient(endpoint=endpoint, token=token)
    client.initialize()
    tools = client.list_tools()

    mcp = FastMCP(
        name="GRC Claude Proxy",
        instructions="Proxy MCP do GRC para clientes remotos. Ferramentas de escrita do GRC continuam em dry-run por padrao e exigem confirm=true.",
        host=host,
        port=port,
        streamable_http_path="/mcp",
        stateless_http=True,
        json_response=True,
        transport_security=TransportSecuritySettings(enable_dns_rebinding_protection=False),
    )

    for tool in tools:
        dynamic_tool, annotations = _make_tool_function(client, tool)
        mcp.add_tool(
            dynamic_tool,
            name=tool["name"],
            title=tool.get("title"),
            description=tool.get("description"),
            annotations=annotations,
            structured_output=True,
        )

    return mcp
