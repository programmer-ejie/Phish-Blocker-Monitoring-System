// content.js - ask background to fetch the API (avoids CORS)
(() => {
  const OVERLAY_ID = "phishblock-overlay-v1";
  const RESULT_STORE_KEY = "phishResultsByUrl";

  // Avoid duplicate injection
  if (document.getElementById(OVERLAY_ID)) return;

  const host = window.location.hostname;

  // Skip local, chrome, or search engine pages
  const searchEngines = ["google.", "bing.", "yahoo.", "duckduckgo.", "baidu."];
  if (
    host === "localhost" ||
    host === "127.0.0.1" ||
    host === "" ||
    window.location.protocol.startsWith("chrome") ||
    searchEngines.some(se => host.includes(se))
  ) {
    console.log("PhishBlock: skipping check for local/search page:", host);
    return;
  }

  // Create preload overlay
  const overlay = document.createElement("div");
  overlay.id = OVERLAY_ID;
  Object.assign(overlay.style, {
    position: "fixed",
    top: "0",
    left: "0",
    width: "100%",
    height: "100%",
    backgroundColor: "rgba(0,0,0,0.6)",
    color: "#fff",
    zIndex: "2147483647",
    display: "flex",
    alignItems: "center",
    justifyContent: "center",
    fontFamily: "Arial, sans-serif",
    fontSize: "18px",
    textAlign: "center",
    padding: "16px",
    transition: "opacity 0.4s ease-in-out"
  });
  overlay.innerHTML = `
    <div style="display:flex; flex-direction:column; align-items:center; justify-content:center; gap:16px;">
      <div style="border:6px solid rgba(255,255,255,0.3); border-top:6px solid #fff; border-radius:50%; width:60px; height:60px; animation:spin 1s linear infinite;"></div>
      <div style="font-size:18px; text-align:center;">Checking this site for phishing risk...</div>
    </div>

    <style>
      @keyframes spin {
        0% { transform: rotate(0deg); }
        100% { transform: rotate(360deg); }
      }
    </style>
  `;

  try {
    (document.documentElement || document.body || document).appendChild(overlay);
  } catch (e) {
    console.warn("PhishBlock: overlay injection failed", e);
    return;
  }

  function callBackgroundFetch(url, timeoutMs = 9000) {
    return new Promise((resolve) => {
      let responded = false;

      chrome.runtime.sendMessage({ action: "fetch_phish_api", url }, (resp) => {
        responded = true;
        if (chrome.runtime.lastError) {
          resolve({ ok: false, error: chrome.runtime.lastError.message });
          return;
        }
        resolve(resp || { ok: false, error: "no-response" });
      });

      setTimeout(() => {
        if (!responded) {
          console.warn("PhishBlock: background fetch timed out");
          resolve({ ok: false, error: "bg-timeout" });
        }
      }, timeoutMs);
    });
  }

  function normalizeStatus(result) {
    return (
      result?.status ??
      result?.decision ??
      (result?.risk_level === "High"
        ? "Block"
        : result?.risk_level === "Medium"
        ? "Suspicious"
        : "Proceed")
    );
  }

  function storeResultForUrl(url, result) {
    chrome.storage.local.get([RESULT_STORE_KEY], (data) => {
      const resultsByUrl = data[RESULT_STORE_KEY] || {};
      resultsByUrl[url] = result;
      chrome.storage.local.set({
        [RESULT_STORE_KEY]: resultsByUrl,
        lastPhishResult: result
      });
    });
  }

  function showBlockedOverlay(result, currentOverlay, reason, score) {
    currentOverlay.innerHTML = `
      <div style="max-width:800px; background:#ffe5e5; border:2px solid #ff4d4d; border-radius:12px; padding:24px; box-shadow:0 4px 12px rgba(0,0,0,0.3); text-align:center; color:#000;">
        <h1 style="font-size:26px; margin:0 0 12px; color:#cc0000;">Phishing Risk Detected</h1>
        <p style="margin:6px 0 16px; font-size:16px; line-height:1.5;">
          This site has been flagged as <strong style="color:#b30000;">HIGH RISK</strong>.<br>
          For your safety, please <strong>do not enter personal info</strong>.
        </p>
        <pre id="phish-risk-details" style="white-space:pre-wrap; text-align:left; background:#fff0f0; padding:12px; border-radius:8px; border:1px solid #ffcccc; font-size:14px; color:#660000; max-height:200px; overflow:auto;"></pre>
        <div style="margin-top:16px; display:flex; justify-content:center; gap:12px;">
          <button id="phish-close-tab" style="background:#cc0000; color:#fff; padding:10px 16px; border:none; border-radius:6px; font-size:15px; cursor:pointer; font-weight:bold;">Close Page</button>
          <button id="phish-continue-anyway" style="background:#fff; color:#333; border:1px solid #aaa; padding:10px 16px; border-radius:6px; font-size:15px; cursor:pointer;">Proceed Anyway</button>
        </div>
      </div>`;

    const detailsEl = document.getElementById("phish-risk-details");
    if (detailsEl) {
      detailsEl.textContent = `Reason: ${reason}\nScore: ${score}`;
    }

    try {
      document.getElementById("phish-close-tab").addEventListener("click", () => {
        try { window.close(); } catch (e) {}
        location.href = "about:blank";
      });
      document.getElementById("phish-continue-anyway").addEventListener("click", () => {
        currentOverlay.remove();
      });
    } catch (e) {
      console.warn("PhishBlock: attach handlers failed", e);
    }

    storeResultForUrl(window.location.href, result);
  }

  async function checkAndAct(targetUrl) {
    let bgResp;
    try {
      bgResp = await callBackgroundFetch(targetUrl, 9000);
    } catch (err) {
      console.warn("PhishBlock: background fetch threw", err);
      overlay.remove();
      return;
    }

    if (!bgResp || bgResp.ok === false) {
      console.warn("PhishBlock: API fetch failed (background)", bgResp);
      overlay.remove();
      return;
    }

    const result = bgResp.json ?? bgResp.data ?? bgResp;
    const status = normalizeStatus(result);
    const score = result?.score ?? null;
    const reason = result?.reason ?? JSON.stringify(result?.flags ?? result ?? "");

    if (status && status.includes("Block")) {
      showBlockedOverlay(result, overlay, reason, score);
      return;
    }

    overlay.remove();
    storeResultForUrl(targetUrl, result);
  }

  checkAndAct(window.location.href);

  chrome.runtime.onMessage.addListener((msg, sender, sendResponse) => {
    if (msg?.action === "run_check" && msg.url) {
      checkAndAct(msg.url)
        .then(() => sendResponse({ ok: true }))
        .catch((e) => sendResponse({ ok: false, error: String(e) }));
      return true;
    }
  });
})();
