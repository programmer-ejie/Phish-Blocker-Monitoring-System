const { test } = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');
const crypto = require('node:crypto');

const extensionRoot = path.join(__dirname, '../extension-setup/extensions-version');
const directories = fs.readdirSync(extensionRoot).filter(name => fs.statSync(path.join(extensionRoot, name)).isDirectory());

async function runExtension(name, loggingUrl, storage = {}, failRegistration = false) {
  const folder = path.join(extensionRoot, name);
  const config = JSON.parse(fs.readFileSync(path.join(folder, 'config.json'), 'utf8'));
  config.logging_api_url = loggingUrl;
  const calls = [];
  const verdict = { url: 'https://example.com/Case?Token=AbC', score: 0.8,
    risk_level: 'High', status: 'Block', reason: 'Test',
    ml_engine: 'phishing_url_bundle', model_threshold: 0.569018 };
  const context = vm.createContext({
    console: { warn() {}, error() {} }, AbortController, setTimeout, clearTimeout, URL, crypto,
    chrome: { storage: { local: {
      get: async key => ({ [key]: storage[key] }),
      set: async values => Object.assign(storage, values)
    } }, runtime: { getURL: name => name, onMessage: { addListener() {} } } },
    fetch: async (url, options) => {
      if (url === 'config.json') return { ok: true, json: async () => config };
      calls.push({ url, options });
      if (url.endsWith('/devices/register')) {
        const data = JSON.parse(options.body);
        return { ok: !failRegistration, status: failRegistration ? 503 : 200, json: async () => ({ ...data, computer_number: 7, name: `${data.campus_name}-PC-007` }) };
      }
      return { ok: true, json: async () => verdict };
    },
  });
  vm.runInContext(fs.readFileSync(path.join(folder, 'background.js'), 'utf8'), context);
  const response = await new Promise(resolve => context.processPhishCheck(
    { action: 'checkPhish', url: verdict.url }, {}, resolve));
  await new Promise(resolve => setImmediate(resolve));
  return { calls, config, response, verdict, storage, context };
}

for (const name of directories) {
  test(`${name}: prediction goes to Render and logs go to Hostinger`, async () => {
    const logUrl = 'https://monitoring.example/api/phishing-logs';
    const { calls, config, response, verdict } = await runExtension(name, logUrl);
    assert.equal(response.ok, true);
    assert.equal(calls.length, 3);
    assert.equal(calls[0].url, config.prediction_api_url + encodeURIComponent(verdict.url));
    assert.match(calls[0].url, /^https:\/\/phish-blocker-monitoring-system-8f1o\.onrender\.com\/check_url\?url=/);
    assert.equal(calls[1].url, 'https://monitoring.example/api/devices/register');
    assert.equal(calls[2].url, logUrl);
    assert.equal(calls[2].options.method, 'POST');
    const payload = JSON.parse(calls[2].options.body);
    assert.equal(new Date(payload.detected_at).toISOString(), payload.detected_at);
    assert.equal(payload.campus_name, config.campus_name);
    assert.match(payload.device_uuid, /^[0-9a-f-]{36}$/);
    assert.equal(payload.computer_number, undefined);
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


test('device UUID persists across service worker restarts and concurrent logs share registration', async () => {
  const storage = {};
  const name = directories[0];
  const url = 'https://monitoring.example/api/phishing-logs';
  const first = await runExtension(name, url, storage);
  const uuid = storage.phishblockDeviceUuid;
  const second = await runExtension(name, url, storage);
  assert.equal(storage.phishblockDeviceUuid, uuid);
  assert.equal(JSON.parse(second.calls[1].options.body).device_uuid, uuid);
  await Promise.all([second.context.logToDatabase(second.verdict), second.context.logToDatabase(second.verdict)]);
  assert.equal(second.calls.filter(call => call.url.endsWith('/devices/register')).length, 1);
  assert.equal(storage.phishblockDevice.name, `${second.config.campus_name}-PC-007`);
});

test('registration failure preserves UUID and allows retry without sending unregistered logs', async () => {
  const result = await runExtension(directories[0], 'https://monitoring.example/api/phishing-logs', {}, true);
  assert.equal(result.response.ok, true);
  assert.equal(result.calls.length, 2);
  const uuid = result.storage.phishblockDeviceUuid;
  await result.context.logToDatabase(result.verdict);
  assert.equal(result.calls.length, 3);
  assert.equal(JSON.parse(result.calls[2].options.body).device_uuid, uuid);
});
