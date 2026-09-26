// Base SQLite y directorio de fotos exclusivos de esta ejecución; nunca usa Azure.
import { spawn, spawnSync } from 'node:child_process';
import { existsSync, mkdirSync, writeFileSync } from 'node:fs';
import path from 'node:path';
const root = process.cwd();
const directory = path.join(root, 'storage/framework/testing', 'browser-' + process.pid);
mkdirSync(directory, { recursive: true });
const database = path.join(directory, 'test.sqlite'); writeFileSync(database, '');
const php = process.env.PHP_BIN || (process.platform === 'win32' && existsSync('C:/xampp/php/php.exe') ? 'C:/xampp/php/php.exe' : 'php');
const env = { ...process.env, APP_ENV: 'testing', APP_KEY: 'base64:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA=', APP_DEBUG: 'false',
  DB_CONNECTION: 'sqlite', DB_DATABASE: database, DB_URL: '', CACHE_STORE: 'array', SESSION_DRIVER: 'array',
  APP_URL: 'http://127.0.0.1:8010', VEHICLE_PHOTO_ROOT: path.join(directory, 'photos'), REALTIME_PUSHER_ENABLED: 'false' };
for (const command of [['artisan', 'migrate', '--force', '--env=testing'], ['artisan', 'db:seed', '--force', '--env=testing']]) {
  const result = spawnSync(php, command, { cwd: root, env, stdio: 'inherit', windowsHide: true });
  if (result.status !== 0) process.exit(result.status || 1);
}
const server = spawn(php, ['-S', '127.0.0.1:8010', '-t', path.join(root, 'public'), path.join(root, 'vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php')], { cwd: path.join(root, 'public'), env, stdio: 'inherit', windowsHide: true });
const worker = spawn(php, ['artisan', 'subastas:sincronizar', '--loop', '--env=testing'], { cwd: root, env, stdio: 'inherit', windowsHide: true });
function close() { worker.kill(); server.kill(); }
process.on('SIGINT', close); process.on('SIGTERM', close); process.on('exit', close);
server.on('exit', code => { worker.kill(); process.exit(code || 0); });
