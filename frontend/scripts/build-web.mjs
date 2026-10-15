// Builds the web version of the app into ../backend/public/app, where Laravel serves it at
// https://<your domain>/app. Run from frontend/: `npm run build:web`, then commit backend/public/app.
// The build calls the API on the same domain it is opened from, so no URL is baked in.
import { spawnSync } from 'node:child_process';
import { cpSync, existsSync, renameSync, rmSync, writeFileSync } from 'node:fs';
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';

const root = join(dirname(fileURLToPath(import.meta.url)), '..');
const out = join(root, 'dist-web');
const target = join(root, '..', 'backend', 'public', 'app');

const env = { ...process.env, HANDI_WEB_BUILD: '1', EXPO_PUBLIC_API_BASE_URL: 'same-origin', NODE_ENV: 'production' };
rmSync(out, { recursive: true, force: true });

const run = spawnSync('npx', ['expo', 'export', '--platform', 'web', '--output-dir', 'dist-web', '--clear'], {
  cwd: root,
  env,
  stdio: 'inherit',
  shell: process.platform === 'win32',
});
if (run.status !== 0 || !existsSync(join(out, 'index.html'))) {
  console.error('Web build failed.');
  process.exit(run.status || 1);
}

// Laravel serves the page itself (with security headers) for every /app/... route. The file is
// renamed so web servers never hand out a bare index.html for /app/ instead.
renameSync(join(out, 'index.html'), join(out, 'shell.html'));

rmSync(target, { recursive: true, force: true });
cpSync(out, target, { recursive: true });
// Apache: send /app/ (a real folder) to Laravel too, like every other /app/... page.
writeFileSync(join(target, '.htaccess'), [
  '<IfModule mod_rewrite.c>',
  '    RewriteEngine On',
  '    RewriteCond %{REQUEST_FILENAME} !-f',
  '    RewriteRule ^ ../index.php [L]',
  '</IfModule>',
  '',
].join('\n'));
rmSync(out, { recursive: true, force: true });
console.log(`\nWeb app copied to ${target}\nIt is served at /app by Laravel. Commit backend/public/app and deploy.`);
