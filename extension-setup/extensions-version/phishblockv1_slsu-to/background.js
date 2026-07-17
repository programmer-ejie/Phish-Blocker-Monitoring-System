let deviceConfig = {
  computer_number: null,
  campus_name: null
};

const campusCodeReady = fetch(chrome.runtime.getURL("config.json"))
  .then(r => r.json())
  .then(cfg => {
    deviceConfig = {
      computer_number: cfg.computer_number ?? null,
      campus_name: cfg.campus_name ?? null
    };
    console.log("Loaded device config:", deviceConfig);
    return deviceConfig;
  })
  .catch(err => {
    console.error("Failed to load config.json", err);
    return deviceConfig;
  });

const API_BASE = "https://phish-blocker-monitoring-system.onrender.com/check_url?url=";
const LOG_API = "https://phish-blocker-monitoring-system.onrender.com/logs";

async function logToDatabase(result) {
  if (!result) return;
  await campusCodeReady;
  if (deviceConfig.computer_number == null && !deviceConfig.campus_name) {
    console.warn("Config not loaded yet, skipping log for now");
    return;
  }

  const payload = {
    ...result,
    computer_number: deviceConfig.computer_number,
    campus_name: deviceConfig.campus_name
  };

  fetch(LOG_API, {
    method: "POST",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify(payload)
  }).then(response => {
    if (!response.ok) {
      throw new Error(`Logging API returned HTTP ${response.status}`);
    }
  }).catch(err => console.warn("Supabase log failed:", err));
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
    const apiUrl = API_BASE + encodeURIComponent(target);

    fetchWithTimeout(apiUrl, { method: "GET" }, 9000)
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
