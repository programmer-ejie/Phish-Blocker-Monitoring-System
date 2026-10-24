const { test } = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');

const extensionRoot = path.join(__dirname, '../extension-setup/extensions-version');
const directories = fs.readdirSync(extensionRoot).filter(name => fs.statSync(path.join(extensionRoot, name)).isDirectory());

async function runExtension(name, loggingUrl) {
  const folder = path.join(extensionRoot, name);
  const config = JSON.parse(fs.readFileSync(path.join(folder, 'config.json'), 'utf8'));
  config.logging_api_url = loggingUrl;
  const calls = [];
  const verdict = { url: 'https://example.com/Case?Token=AbC', score: 0.8,
    risk_level: 'High', status: 'Block', reason: 'Test',
    ml_engine: 'phishing_url_bundle', model_threshold: 0.569018 };
  const context = vm.createContext({
    console: { warn() {}, error() {} }, AbortController, setTimeout, clearTimeout,
    chrome: { runtime: { getURL: name => name, onMessage: { addListener() {} } } },
    fetch: async (url, options) => {
      if (url === 'config.json') return { ok: true, json: async () => config };
      calls.push({ url, options });
      return { ok: true, json: async () => verdict };
    },
  });
  vm.runInContext(fs.readFileSync(path.join(folder, 'background.js'), 'utf8'), context);
  const response = await new Promise(resolve => context.processPhishCheck(
    { action: 'checkPhish', url: verdict.url }, {}, resolve));
  await new Promise(resolve => setImmediate(resolve));
  return { calls, config, response, verdict };
}

for (const name of directories) {
  test(`${name}: prediction goes to Render and logs go to Hostinger`, async () => {
    const logUrl = 'https://monitoring.example/api/phishing-logs';
    const { calls, config, response, verdict } = await runExtension(name, logUrl);
    assert.equal(response.ok, true);
    assert.equal(calls.length, 2);
    assert.equal(calls[0].url, config.prediction_api_url + encodeURIComponent(verdict.url));
    assert.match(calls[0].url, /^https:\/\/phish-blocker-monitoring-system-8f1o\.onrender\.com\/check_url\?url=/);
    assert.equal(calls[1].url, logUrl);
    assert.equal(calls[1].options.method, 'POST');
    const payload = JSON.parse(calls[1].options.body);
    assert.equal(payload.campus_name, config.campus_name);
    assert.equal(payload.computer_number, config.computer_number);
    assert.equal(payload.url, verdict.url);
    assert.equal(payload.metadata.ml_engine, 'phishing_url_bundle');
    assert.equal(payload.metadata.model_threshold, verdict.model_threshold);
    const manifest = JSON.parse(fs.readFileSync(path.join(extensionRoot, name, 'manifest.json'), 'utf8'));
    assert.ok(manifest.host_permissions.includes('<all_urls>'));
    assert.ok(manifest.host_permissions.includes('https://phish-blocker-monitoring-system-8f1o.onrender.com/*'));
  });

  test(`${name}: an unset logging URL never falls back to Render or Supabase`, async () => {
    const { calls, response } = await runExtension(name, '');
    assert.equal(response.ok, true);
    assert.equal(calls.length, 1);
  });
}
