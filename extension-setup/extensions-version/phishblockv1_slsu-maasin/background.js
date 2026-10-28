const DEFAULT_PREDICTION_API = "https://phish-blocker-monitoring-system-8f1o.onrender.com/check_url?url=";
let deviceConfig = {
  campus_name: null,
  prediction_api_url: DEFAULT_PREDICTION_API,
  logging_api_url: ""
};

const campusCodeReady = fetch(chrome.runtime.getURL("config.json"))
  .then(r => {
    if (!r.ok) throw new Error("Could not load extension configuration");
    return r.json();
  })
  .then(cfg => {
    deviceConfig = {
      campus_name: cfg.campus_name ?? null,
      prediction_api_url: cfg.prediction_api_url || DEFAULT_PREDICTION_API,
      logging_api_url: cfg.logging_api_url || ""
    };
    return deviceConfig;
  })
  .catch(err => {
    console.error("Failed to load config.json", err);
    return deviceConfig;
  });

let registrationPromise = null;

async function registerDevice() {
  await campusCodeReady;
  if (!deviceConfig.logging_api_url || !deviceConfig.campus_name) {
    throw new Error("Set campus_name and logging_api_url in config.json before registering this device");
  }
  const saved = await chrome.storage.local.get("phishblockDeviceUuid");
  const deviceUuid = saved.phishblockDeviceUuid || crypto.randomUUID();
  if (!saved.phishblockDeviceUuid) {
    await chrome.storage.local.set({ phishblockDeviceUuid: deviceUuid });
  }
  const registrationUrl = new URL(deviceConfig.logging_api_url);
  registrationUrl.pathname = registrationUrl.pathname.replace(/\/phishing-logs\/?$/, "/devices/register");
  if (registrationUrl.pathname === new URL(deviceConfig.logging_api_url).pathname) {
    throw new Error("logging_api_url must end in /api/phishing-logs");
  }
  const response = await fetchWithTimeout(registrationUrl.href, {
    method: "POST",
    headers: { "Content-Type": "application/json", "Accept": "application/json" },
    body: JSON.stringify({ device_uuid: deviceUuid, campus_name: deviceConfig.campus_name })
  }, 12000);
  if (!response.ok) throw new Error(`Device registration returned HTTP ${response.status}`);
  const device = await response.json();
  if (device.device_uuid !== deviceUuid || device.campus_name !== deviceConfig.campus_name || !device.name) {
    throw new Error("Invalid device registration response");
  }
  await chrome.storage.local.set({ phishblockDevice: device });
  return device;
}

function ensureDeviceRegistered() {
  if (!registrationPromise) {
    registrationPromise = registerDevice().catch(error => {
      registrationPromise = null;
      throw error;
    });
  }
  return registrationPromise;
}

async function logToDatabase(result) {
  if (!result) return;
  const detectedAt = new Date().toISOString();
  await campusCodeReady;
  if (!deviceConfig.logging_api_url) {
    console.warn("Logging is not configured: set logging_api_url in config.json to your Hostinger /api/phishing-logs endpoint.");
    return;
  }
  try {
    const device = await ensureDeviceRegistered();
    const payload = {
      detected_at: detectedAt,
      url: result.url,
      score: result.score,
      risk_level: result.risk_level,
      status: result.status,
      reason: result.reason,
      device_uuid: device.device_uuid,
      campus_name: deviceConfig.campus_name,
      metadata: {
        ml_engine: result.ml_engine,
        model_threshold: result.model_threshold,
        model_block_threshold: result.model_block_threshold,
        model_decision: result.ml_decision
      }
    };
    const response = await fetchWithTimeout(deviceConfig.logging_api_url, {
      method: "POST",
      headers: { "Content-Type": "application/json", "Accept": "application/json" },
      body: JSON.stringify(payload)
    }, 12000);
    if (!response.ok) throw new Error(`Logging endpoint returned HTTP ${response.status}`);
  } catch (err) {
    console.warn("Hostinger logging failed:", err);
  }
}

function fetchWithTimeout(url, opts = {}, timeout = 8000) {
  const controller = new AbortController();
  const timer = setTimeout(() => controller.abort(), timeout);

  return fetch(url, { ...opts, signal: controller.signal })
    .catch(err => {
      if (err?.name === "AbortError") {
        throw new Error("fetch-timeout");
      }
      throw err;
    })
    .finally(() => {
      clearTimeout(timer);
    });
}

function forwardRecheckToTab(msg, sendResponse) {
  const tabId = Number(msg?.tabId);

  if (!Number.isInteger(tabId)) {
    sendResponse({ ok: false, error: "no-tab-id-provided" });
    return;
  }

  chrome.tabs.sendMessage(tabId, { action: "run_check", url: msg.url }, (resp) => {
    if (chrome.runtime.lastError) {
      sendResponse({ ok: false, error: chrome.runtime.lastError.message });
      return;
    }

    sendResponse(resp || { ok: false, error: "no-response" });
  });
}

function processPhishCheck(msg, sender, sendResponse) {
  try {
    if (!msg || !msg.action) return;
    if (msg.action === "recheck_url") {
      forwardRecheckToTab(msg, sendResponse);
      return;
    }
    if (msg.action !== "checkPhish" && msg.action !== "fetch_phish_api") return;
    if (!msg.url) {
      sendResponse({ ok: false, error: "no-url-provided" });
      return;
    }

    const target = msg.url;
    campusCodeReady
      .then(() => fetchWithTimeout(deviceConfig.prediction_api_url + encodeURIComponent(target), { method: "GET" }, 9000))
      .then(async resp => {
        if (!resp.ok) {
          const text = await resp.text().catch(() => "");
          sendResponse({ ok: false, status: resp.status, text });
          return;
        }

        const json = await resp.json().catch(() => null);

        void logToDatabase(json);

        sendResponse({ ok: true, json });
      })
      .catch(err => {
        sendResponse({ ok: false, error: String(err) });
      });
  } catch (e) {
    sendResponse({ ok: false, error: String(e) });
  }
}

chrome.runtime.onMessage.addListener((msg, sender, sendResponse) => {
  try {
    processPhishCheck(msg, sender, sendResponse);
    return true;
  } catch (e) {
    sendResponse({ ok: false, error: String(e) });
  }
});
