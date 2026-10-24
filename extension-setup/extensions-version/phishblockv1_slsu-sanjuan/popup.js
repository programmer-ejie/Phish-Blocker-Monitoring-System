document.addEventListener("DOMContentLoaded", async () => {
  const RESULT_STORE_KEY = "phishResultsByUrl";
  const urlEl = document.getElementById("url");
  const statusEl = document.getElementById("status");
  const scoreEl = document.getElementById("score");
  const detailsEl = document.getElementById("details");
  const recheckBtn = document.getElementById("recheck");

  const [activeTab] = await chrome.tabs.query({ active: true, currentWindow: true });
  const activeUrl = activeTab?.url || "";
  urlEl.textContent = activeUrl || "This page cannot be checked.";

  function renderResult(result) {
    if (!result) {
      statusEl.className = "status";
      statusEl.textContent = "Checking in background";
      scoreEl.textContent = "";
      detailsEl.textContent = "PhishBlock scans pages quietly. Select Run security check to check this page now.";
      return;
    }

    const risk = String(result.risk_level || "Unknown");
    const riskKey = risk.toLowerCase();
    statusEl.className = `status ${riskKey === "high" || riskKey === "critical" ? "high" : riskKey === "medium" ? "medium" : "safe"}`;
    statusEl.textContent = risk === "Unknown" ? "Assessment available" : `${risk} risk`;

    const score = Number(result.score);
    scoreEl.textContent = Number.isFinite(score)
      ? `Phishing probability ${Math.round(Math.max(0, Math.min(1, score)) * 100)}%`
      : "Prediction percentage unavailable";
    detailsEl.textContent = result.reason || "No suspicious indicators were reported.";
  }

  function saveResult(result) {
    chrome.storage.local.get([RESULT_STORE_KEY], (data) => {
      const results = data[RESULT_STORE_KEY] || {};
      results[activeUrl] = result;
      chrome.storage.local.set({ [RESULT_STORE_KEY]: results, lastPhishResult: result });
    });
  }

  function runCheck() {
    if (!activeUrl || !/^https?:/i.test(activeUrl)) {
      statusEl.className = "status";
      statusEl.textContent = "Page unavailable";
      detailsEl.textContent = "Open a regular website to run a security check.";
      return;
    }

    recheckBtn.disabled = true;
    recheckBtn.textContent = "Checking destination...";
    statusEl.className = "status";
    statusEl.textContent = "Checking";
    detailsEl.textContent = "Analyzing reputation and URL patterns in the background.";

    chrome.runtime.sendMessage({ action: "checkPhish", url: activeUrl }, (resp) => {
      if (chrome.runtime.lastError || !resp?.ok) {
        statusEl.className = "status high";
        statusEl.textContent = "Check unavailable";
        detailsEl.textContent = resp?.error || chrome.runtime.lastError?.message || "The detection service did not respond.";
      } else {
        const result = resp.json ?? resp.data ?? resp;
        saveResult(result);
        renderResult(result);
      }
      recheckBtn.disabled = false;
      recheckBtn.textContent = "Run security check";
    });
  }

  chrome.storage.local.get([RESULT_STORE_KEY], (data) => {
    const result = data[RESULT_STORE_KEY]?.[activeUrl];
    renderResult(result);
    if (!result && /^https?:/i.test(activeUrl)) runCheck();
  });

  recheckBtn.addEventListener("click", runCheck);
});
