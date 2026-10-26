import tailwindcss from '@tailwindcss/vite';
import path from 'path';
import { fileURLToPath } from 'url';
import { defineConfig } from 'vite';
import { spawn, execSync } from 'node:child_process';

const __dirname = path.dirname(fileURLToPath(import.meta.url));

let phpServer = null;

function ensureBackend() {
  try {
    execSync('/etc/init.d/mariadb status >/dev/null 2>&1 || /etc/init.d/mariadb start >/dev/null 2>&1', { stdio: 'ignore' });
  } catch (_) {}

  if (!phpServer) {
    try {
      phpServer = spawn('php', ['-S', '127.0.0.1:8088', '-t', '.'], {
        cwd: path.resolve(__dirname),
        env: {
          ...process.env,
          DB_FALLBACK_SQLITE: 'true',
        },
        stdio: 'ignore',
      });
      phpServer.on('exit', () => {
        phpServer = null;
      });
    } catch (_) {}
  }
}

export default defineConfig(() => {
  ensureBackend();

  return {
    plugins: [
      tailwindcss(),
      {
        name: 'php-backend-starter',
        configureServer() {
          ensureBackend();
        },
      },
    ],
    resolve: {
      alias: {
        '@': path.resolve(__dirname, '.'),
      },
    },
    server: {
      port: 3000,
      host: '0.0.0.0',
      hmr: process.env.DISABLE_HMR !== 'true',
      watch: process.env.DISABLE_HMR === 'true' ? null : {},
      proxy: {
        '^/$': {
          target: 'http://127.0.0.1:8088',
          rewrite: () => '/index.php',
          changeOrigin: true,
        },
        '^/.*\\.php': {
          target: 'http://127.0.0.1:8088',
          changeOrigin: true,
        },
        '^/api/.*': {
          target: 'http://127.0.0.1:8088',
          changeOrigin: true,
        },
        '^/actions/.*': {
          target: 'http://127.0.0.1:8088',
          changeOrigin: true,
        },
        '^/uploads/.*': {
          target: 'http://127.0.0.1:8088',
          changeOrigin: true,
        },
      },
    },
  };
});
