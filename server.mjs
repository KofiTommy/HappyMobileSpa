import { spawn } from 'node:child_process';

const args = process.argv.slice(2);
const value = (long, short) => {
  const index = args.findIndex(arg => arg === long || arg === short);
  return index >= 0 ? args[index + 1] : undefined;
};
const host = value('--host', '-H') || '127.0.0.1';
const port = Number(value('--port', '-p') || process.env.PORT || 7100);
if (!Number.isInteger(port) || port < 1 || port > 65535) throw new Error('Invalid port');
const php = process.env.SPA_PHP || 'C:/xampp/php/php.exe';
const child = spawn(php, ['-S', `${host}:${port}`, 'router.php'], { stdio: 'inherit', shell: false });
for (const signal of ['SIGINT', 'SIGTERM']) process.on(signal, () => child.kill(signal));
child.on('error', error => { console.error(error.message); process.exitCode = 1; });
child.on('exit', code => { process.exitCode = code ?? 1; });
