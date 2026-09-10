import { spawn } from "node:child_process";
import { THEME_ROOT } from "../config.js";

export type CommandResult = {
  command: string;
  cwd: string;
  exitCode: number;
  stdout: string;
  stderr: string;
};

export async function runCommand(
  command: string,
  args: string[],
  options: { cwd?: string; timeoutMs?: number } = {},
): Promise<CommandResult> {
  const cwd = options.cwd ?? THEME_ROOT;
  const timeoutMs = options.timeoutMs ?? 10 * 60 * 1000;

  return new Promise((resolve, reject) => {
    const child = spawn(command, args, {
      cwd,
      env: process.env,
      shell: false,
    });

    let stdout = "";
    let stderr = "";
    let settled = false;

    const timer = setTimeout(() => {
      if (!settled) {
        settled = true;
        child.kill("SIGTERM");
        reject(new Error(`Command timed out after ${timeoutMs}ms: ${command} ${args.join(" ")}`));
      }
    }, timeoutMs);

    child.stdout.on("data", (chunk: Buffer | string) => {
      stdout += chunk.toString();
    });

    child.stderr.on("data", (chunk: Buffer | string) => {
      stderr += chunk.toString();
    });

    child.on("error", (error) => {
      if (!settled) {
        settled = true;
        clearTimeout(timer);
        reject(error);
      }
    });

    child.on("close", (code) => {
      if (settled) {
        return;
      }
      settled = true;
      clearTimeout(timer);
      resolve({
        command: [command, ...args].join(" "),
        cwd,
        exitCode: code ?? 1,
        stdout,
        stderr,
      });
    });
  });
}

export async function runNpmScript(
  script: string,
  options: { timeoutMs?: number } = {},
): Promise<CommandResult> {
  return runCommand("npm", ["run", script], options);
}

export function formatCommandResult(result: CommandResult): string {
  const sections = [
    `command: ${result.command}`,
    `cwd: ${result.cwd}`,
    `exitCode: ${result.exitCode}`,
  ];

  if (result.stdout.trim()) {
    sections.push("", "stdout:", result.stdout.trimEnd());
  }

  if (result.stderr.trim()) {
    sections.push("", "stderr:", result.stderr.trimEnd());
  }

  return sections.join("\n");
}
