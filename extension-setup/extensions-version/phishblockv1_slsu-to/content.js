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
    background: "rgba(4, 12, 23, 0.92)",
    backdropFilter: "blur(12px)",
    color: "#e8f1fa",
    zIndex: "2147483647",
    display: "flex",
    alignItems: "center",
    justifyContent: "center",
    fontFamily: "Inter, ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif",
    fontSize: "16px",
    textAlign: "center",
    padding: "16px",
    transition: "opacity 0.4s ease-in-out"
  });
  overlay.innerHTML = `
    <div style="width:min(430px,calc(100vw - 40px)); padding:34px; border:1px solid #203b57; border-radius:22px; background:linear-gradient(145deg,#0b1929,#0e2236); box-shadow:0 30px 80px rgba(0,0,0,.45);">
      <div style="display:flex; align-items:center; gap:14px; margin-bottom:26px; text-align:left;">
        <div style="width:44px;height:44px;display:grid;place-items:center;border:1px solid #285171;border-radius:14px;background:#102c43;color:#5ac8ff;font-weight:800;">PB</div>
        <div><div style="font-size:18px;font-weight:750;letter-spacing:.01em;">PhishBlock</div><div style="margin-top:3px;color:#7f96aa;font-size:12px;">Secure destination analysis</div></div>
      </div>
      <div style="display:flex; align-items:center; gap:16px; padding:18px; border:1px solid #1b354d; border-radius:16px; background:#091625; text-align:left;">
        <div style="flex:0 0 auto;border:3px solid rgba(82,196,255,.18);border-top-color:#52c4ff;border-radius:50%;width:34px;height:34px;animation:phishblock-spin .8s linear infinite;"></div>
        <div><div style="font-size:14px;font-weight:700;">Analyzing this website</div><div style="margin-top:5px;color:#8399ad;font-size:12px;line-height:1.45;">Checking reputation, URL patterns, and phishing indicators.</div></div>
      </div>
    </div>

    <style>
      @keyframes phishblock-spin {
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
      <div style="width:min(560px,calc(100vw - 40px));background:linear-gradient(145deg,#17141d,#21151c);border:1px solid #74333d;border-radius:24px;padding:34px;box-shadow:0 35px 90px rgba(0,0,0,.58);text-align:left;color:#f7edf0;">
        <div style="display:flex;align-items:flex-start;gap:16px;">
          <div style="width:48px;height:48px;flex:0 0 auto;display:grid;place-items:center;border:1px solid #8f3c47;border-radius:15px;background:#3a1d25;color:#ff7b88;font-size:24px;font-weight:800;">!</div>
          <div><div style="color:#ff8490;font-size:11px;font-weight:800;letter-spacing:.14em;text-transform:uppercase;">Security intervention</div><h1 style="font-size:25px;margin:5px 0 8px;color:#fff5f6;letter-spacing:-.02em;">Potential phishing site blocked</h1><p style="margin:0;color:#c9aeb3;font-size:14px;line-height:1.55;">PhishBlock detected high-risk indicators. Do not enter passwords, payment details, or other sensitive information.</p></div>
        </div>
        <div style="margin-top:23px;padding:16px;border:1px solid #55303a;border-radius:14px;background:#130f15;">
          <div style="margin-bottom:7px;color:#98757d;font-size:10px;font-weight:800;letter-spacing:.12em;text-transform:uppercase;">Assessment details</div>
          <pre id="phish-risk-details" style="margin:0;white-space:pre-wrap;overflow-wrap:anywhere;font:12px/1.55 ui-monospace,SFMono-Regular,Consolas,monospace;color:#e4c9ce;max-height:170px;overflow:auto;"></pre>
        </div>
        <div style="margin-top:23px;display:flex;justify-content:flex-end;gap:10px;">
          <button id="phish-continue-anyway" style="background:transparent;color:#bfa7ac;border:1px solid #583943;padding:11px 15px;border-radius:10px;font-size:12px;cursor:pointer;">Continue at my own risk</button>
          <button id="phish-close-tab" style="background:linear-gradient(135deg,#db4353,#bb2d3d);color:#fff;padding:11px 18px;border:0;border-radius:10px;font-size:12px;cursor:pointer;font-weight:750;box-shadow:0 10px 24px rgba(200,45,60,.25);">Leave unsafe page</button>
        </div>
      </div>`;

    const detailsEl = document.getElementById("phish-risk-details");
    if (detailsEl) {
      detailsEl.textContent = `Risk level: ${result?.risk_level ?? "High"}\nScore: ${score ?? "Unavailable"}\nReason: ${reason}`;
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
