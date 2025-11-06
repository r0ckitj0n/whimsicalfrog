module.exports = {
  apps: [
    {
      name: 'wf-vite',
      script: './scripts/dev/run-vite-5176.sh',
      interpreter: '/bin/bash',
      watch: false,
      autorestart: true,
      max_restarts: 10,
      restart_delay: 2000,
      env: {
        VITE_DEV_PORT: process.env.VITE_DEV_PORT || '5176',
        VITE_HMR_PORT: process.env.VITE_HMR_PORT || '5176',
        // Allow IPv6 literal origin like http://[::1]:5176
        WF_VITE_ORIGIN: process.env.WF_VITE_ORIGIN || 'http://localhost:5176'
      },
      out_file: './logs/vite_server.log',
      error_file: './logs/vite_server.log',
      time: true
    }
  ]
};
