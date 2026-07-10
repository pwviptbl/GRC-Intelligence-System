from __future__ import annotations

import fnmatch
import os
import signal
import subprocess
import threading
import time
import uuid
from pathlib import Path
from typing import Any


class HostAgent:
    def __init__(self, output_limit: int = 200_000) -> None:
        self.output_limit = output_limit
        self._jobs: dict[str, dict[str, Any]] = {}
        self._lock = threading.Lock()

    def run_command(
        self,
        command: str,
        cwd: str | None = None,
        timeout: int = 120,
        output_limit: int | None = None,
    ) -> dict[str, Any]:
        workdir = self._directory(cwd)
        limit = self._limit(output_limit)
        started = time.monotonic()

        try:
            result = subprocess.run(
                command,
                cwd=workdir,
                shell=True,
                capture_output=True,
                text=True,
                timeout=max(1, timeout),
            )
            return {
                "ok": result.returncode == 0,
                "command": command,
                "cwd": workdir,
                "exit_code": result.returncode,
                "stdout": self._truncate(result.stdout, limit),
                "stderr": self._truncate(result.stderr, limit),
                "duration_seconds": round(time.monotonic() - started, 3),
            }
        except subprocess.TimeoutExpired as error:
            return {
                "ok": False,
                "command": command,
                "cwd": workdir,
                "error": "command_timeout",
                "timeout": timeout,
                "stdout": self._truncate(self._text(error.stdout), limit),
                "stderr": self._truncate(self._text(error.stderr), limit),
            }

    def start_command(self, command: str, cwd: str | None = None) -> dict[str, Any]:
        workdir = self._directory(cwd)
        job_id = uuid.uuid4().hex
        output_dir = Path(os.getenv("HOST_AGENT_JOB_DIR", "/tmp/grc-host-agent"))
        output_dir.mkdir(parents=True, exist_ok=True)
        stdout_path = output_dir / f"{job_id}.stdout"
        stderr_path = output_dir / f"{job_id}.stderr"
        stdout_file = stdout_path.open("wb")
        stderr_file = stderr_path.open("wb")

        process = subprocess.Popen(
            command,
            cwd=workdir,
            shell=True,
            stdout=stdout_file,
            stderr=stderr_file,
            start_new_session=True,
        )
        with self._lock:
            self._jobs[job_id] = {
                "process": process,
                "command": command,
                "cwd": workdir,
                "started_at": time.time(),
                "stdout_path": str(stdout_path),
                "stderr_path": str(stderr_path),
                "stdout_file": stdout_file,
                "stderr_file": stderr_file,
            }

        return {"ok": True, "job_id": job_id, "pid": process.pid, "command": command, "cwd": workdir}

    def poll_command(self, job_id: str, output_limit: int | None = None) -> dict[str, Any]:
        job = self._job(job_id)
        process: subprocess.Popen[bytes] = job["process"]
        exit_code = process.poll()
        if exit_code is not None:
            self._close_job_files(job)

        limit = self._limit(output_limit)
        return {
            "ok": exit_code in (None, 0),
            "job_id": job_id,
            "running": exit_code is None,
            "exit_code": exit_code,
            "pid": process.pid,
            "command": job["command"],
            "cwd": job["cwd"],
            "stdout": self._read_tail(job["stdout_path"], limit),
            "stderr": self._read_tail(job["stderr_path"], limit),
            "duration_seconds": round(time.time() - job["started_at"], 3),
        }

    def stop_command(self, job_id: str) -> dict[str, Any]:
        job = self._job(job_id)
        process: subprocess.Popen[bytes] = job["process"]
        if process.poll() is None:
            os.killpg(process.pid, signal.SIGTERM)
            try:
                process.wait(timeout=5)
            except subprocess.TimeoutExpired:
                os.killpg(process.pid, signal.SIGKILL)
                process.wait(timeout=5)
        self._close_job_files(job)
        return {"ok": True, "job_id": job_id, "running": False, "exit_code": process.returncode}

    def read_file(self, path: str, offset: int = 0, limit: int = 200_000) -> dict[str, Any]:
        target = Path(path).expanduser().resolve()
        if not target.is_file():
            raise FileNotFoundError(f"Arquivo nao encontrado: {target}")
        with target.open("rb") as handle:
            handle.seek(max(0, offset))
            content = handle.read(max(1, limit))
            truncated = handle.read(1) != b""
        return {
            "ok": True,
            "path": str(target),
            "size": target.stat().st_size,
            "offset": max(0, offset),
            "content": content.decode("utf-8", errors="replace"),
            "truncated": truncated,
        }

    def write_file(self, path: str, content: str, append: bool = False) -> dict[str, Any]:
        target = Path(path).expanduser().resolve()
        target.parent.mkdir(parents=True, exist_ok=True)
        mode = "a" if append else "w"
        with target.open(mode, encoding="utf-8") as handle:
            written = handle.write(content)
        return {"ok": True, "path": str(target), "characters_written": written, "append": append}

    def list_directory(self, path: str, recursive: bool = False, limit: int = 1000) -> dict[str, Any]:
        target = Path(path).expanduser().resolve()
        if not target.is_dir():
            raise NotADirectoryError(f"Diretorio nao encontrado: {target}")
        iterator = target.rglob("*") if recursive else target.iterdir()
        items = []
        for item in iterator:
            if len(items) >= max(1, limit):
                break
            stat = item.stat()
            items.append({
                "path": str(item),
                "name": item.name,
                "type": "directory" if item.is_dir() else "file",
                "size": stat.st_size,
                "modified_at": stat.st_mtime,
            })
        return {"ok": True, "path": str(target), "items": items, "truncated": len(items) >= limit}

    def stat_path(self, path: str) -> dict[str, Any]:
        target = Path(path).expanduser().resolve()
        if not target.exists():
            raise FileNotFoundError(f"Caminho nao encontrado: {target}")
        stat = target.stat()
        return {
            "ok": True,
            "path": str(target),
            "type": "directory" if target.is_dir() else "file",
            "size": stat.st_size,
            "mode": oct(stat.st_mode & 0o7777),
            "uid": stat.st_uid,
            "gid": stat.st_gid,
            "modified_at": stat.st_mtime,
        }

    def find_files(self, path: str, pattern: str = "*", limit: int = 1000) -> dict[str, Any]:
        target = Path(path).expanduser().resolve()
        if not target.is_dir():
            raise NotADirectoryError(f"Diretorio nao encontrado: {target}")
        matches = []
        for root, directories, files in os.walk(target):
            for name in directories + files:
                if fnmatch.fnmatch(name, pattern):
                    matches.append(str(Path(root) / name))
                    if len(matches) >= max(1, limit):
                        return {"ok": True, "matches": matches, "truncated": True}
        return {"ok": True, "matches": matches, "truncated": False}

    def search_text(
        self,
        query: str,
        path: str,
        glob: str | None = None,
        limit: int = 500,
    ) -> dict[str, Any]:
        target = Path(path).expanduser().resolve()
        command = ["rg", "--line-number", "--no-heading", "--color", "never", "--max-count", str(limit)]
        if glob:
            command.extend(["--glob", glob])
        command.extend(["--", query, str(target)])
        result = subprocess.run(command, capture_output=True, text=True)
        return {
            "ok": result.returncode in (0, 1),
            "query": query,
            "path": str(target),
            "matches": result.stdout.splitlines()[:limit],
            "stderr": result.stderr,
        }

    def _directory(self, cwd: str | None) -> str:
        target = Path(cwd or os.getenv("HOST_AGENT_DEFAULT_CWD", str(Path.home()))).expanduser().resolve()
        if not target.is_dir():
            raise NotADirectoryError(f"Diretorio de trabalho nao encontrado: {target}")
        return str(target)

    def _job(self, job_id: str) -> dict[str, Any]:
        with self._lock:
            job = self._jobs.get(job_id)
        if job is None:
            raise KeyError(f"Job nao encontrado: {job_id}")
        return job

    def _close_job_files(self, job: dict[str, Any]) -> None:
        for key in ("stdout_file", "stderr_file"):
            handle = job.get(key)
            if handle and not handle.closed:
                handle.close()

    def _read_tail(self, path: str, limit: int) -> str:
        target = Path(path)
        if not target.exists():
            return ""
        with target.open("rb") as handle:
            size = target.stat().st_size
            handle.seek(max(0, size - limit))
            return handle.read().decode("utf-8", errors="replace")

    def _limit(self, value: int | None) -> int:
        return max(256, min(value or self.output_limit, self.output_limit))

    @staticmethod
    def _truncate(value: str, limit: int) -> str:
        if len(value) <= limit:
            return value
        return value[:limit] + f"\n...[truncated {len(value) - limit} chars]"

    @staticmethod
    def _text(value: str | bytes | None) -> str:
        if value is None:
            return ""
        return value.decode("utf-8", errors="replace") if isinstance(value, bytes) else value
