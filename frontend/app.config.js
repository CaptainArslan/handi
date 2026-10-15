// Extends app.json. Adds Firebase (push notifications on Android) only when
// frontend/google-services.json exists, so builds without it still work.
const fs = require('fs');
const path = require('path');

module.exports = ({ config }) => {
  const googleServices = path.join(__dirname, 'google-services.json');
  if (fs.existsSync(googleServices)) {
    config.android = { ...config.android, googleServicesFile: './google-services.json' };
  }
  return config;
};
