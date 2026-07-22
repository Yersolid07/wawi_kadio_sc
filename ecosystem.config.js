module.exports = {
  apps: [
    {
      name: 'wawi-kadio-web',
      script: 'artisan',
      interpreter: 'php',
      args: 'serve --host=127.0.0.1 --port=8000',
      instances: 1,
      autorestart: true,
      watch: false,
      max_memory_restart: '1G',
      env: {
        APP_ENV: 'production',
      }
    },
    {
      name: 'wawi-kadio-queue',
      script: 'artisan',
      interpreter: 'php',
      args: 'queue:work --tries=3 --timeout=90',
      instances: 1,
      autorestart: true,
      watch: false,
      max_memory_restart: '512M',
    },
    {
      name: 'wawi-kadio-reverb',
      script: 'artisan',
      interpreter: 'php',
      args: 'reverb:start --host=0.0.0.0 --port=8080',
      instances: 1,
      autorestart: true,
      watch: false,
      max_memory_restart: '512M',
    }
  ]
};
