// Extends app.json. Adds Firebase (push notifications on Android) only when
// frontend/google-services.json exists, so builds without it still work.
// For the web build it also serves the app under /app.
const fs = require('fs');
const path = require('path');

module.exports = ({ config }) => {
  const googleServices = path.join(__dirname, 'google-services.json');
  if (fs.existsSync(googleServices)) {
    config.android = { ...config.android, googleServicesFile: './google-services.json' };
  }
  // Web build for the website (npm run build:web): served by Laravel under /app.
  if (process.env.HANDI_WEB_BUILD) {
    config.experiments = { ...config.experiments, baseUrl: '/app' };
  }
  return config;
};
