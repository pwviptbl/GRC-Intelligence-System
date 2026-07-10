#!/usr/bin/env python3
from __future__ import annotations

import os

import uvicorn

from grc_proxy import build_server

mcp = build_server()
app = mcp.streamable_http_app()

if __name__ == "__main__":
    uvicorn.run(
        app,
        host=os.getenv("GRC_PROXY_HOST", "127.0.0.1"),
        port=int(os.getenv("GRC_PROXY_PORT", "8775")),
        log_level=os.getenv("GRC_PROXY_LOG_LEVEL", "info"),
    )
