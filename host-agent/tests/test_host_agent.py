import tempfile
import time
import unittest
from pathlib import Path

from host_agent import HostAgent


class HostAgentTest(unittest.TestCase):
    def setUp(self) -> None:
        self.agent = HostAgent()

    def test_reads_writes_and_searches_host_files(self) -> None:
        with tempfile.TemporaryDirectory() as directory:
            path = Path(directory) / "sample.txt"
            self.agent.write_file(str(path), "alpha\nbeta\n")

            result = self.agent.read_file(str(path))
            search = self.agent.search_text("beta", directory)

            self.assertEqual("alpha\nbeta\n", result["content"])
            self.assertEqual(1, len(search["matches"]))

    def test_runs_commands_in_selected_directory(self) -> None:
        with tempfile.TemporaryDirectory() as directory:
            result = self.agent.run_command("pwd", directory)

            self.assertTrue(result["ok"])
            self.assertEqual(str(Path(directory).resolve()), result["stdout"].strip())

    def test_manages_background_commands(self) -> None:
        started = self.agent.start_command("printf background-ok")
        result = self.agent.poll_command(started["job_id"])
        for _ in range(20):
            if not result["running"]:
                break
            time.sleep(0.01)
            result = self.agent.poll_command(started["job_id"])

        self.assertFalse(result["running"])
        self.assertEqual("background-ok", result["stdout"])


if __name__ == "__main__":
    unittest.main()
