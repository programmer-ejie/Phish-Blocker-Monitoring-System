document.addEventListener("DOMContentLoaded", async () => {
  const RESULT_STORE_KEY = "phishResultsByUrl";
  const urlEl = document.getElementById("url");
  const statusEl = document.getElementById("status");
  const scoreEl = document.getElementById("score");
  const detailsEl = document.getElementById("details");
  const recheckBtn = document.getElementById("recheck");

  function getActiveTab() {
    return new Promise((resolve) => {
      chrome.tabs.query({ active: true, currentWindow: true }, (tabs) => {
        resolve(tabs[0] || null);
      });
    });
  }

  function renderResult(result) {
    if (result) {
      const risk = String(result.risk_level || "Unknown");
      const riskKey = risk.toLowerCase();
      statusEl.className = `status ${riskKey === "high" || riskKey === "critical" ? "high" : riskKey === "medium" ? "medium" : "safe"}`;
      statusEl.textContent = risk === "Unknown" ? "Assessment available" : `${risk} risk`;
      scoreEl.textContent = result.score == null ? "Score unavailable" : `Score ${Number(result.score).toFixed(3)}`;
      detailsEl.textContent = result.reason || "No suspicious indicators were reported.";
      return;
    }

    statusEl.className = "status";
    statusEl.textContent = "Not checked yet";
    scoreEl.textContent = "";
    detailsEl.textContent = "Run a security check to assess this destination.";
  }

  const activeTab = await getActiveTab();
  const activeUrl = activeTab?.url || "";
  const activeTabId = activeTab?.id ?? null;

  urlEl.textContent = activeUrl;

  chrome.storage.local.get([RESULT_STORE_KEY], (data) => {
    const result = data[RESULT_STORE_KEY]?.[activeUrl];
    renderResult(result);
  });

  recheckBtn.addEventListener("click", () => {
    recheckBtn.disabled = true;
    recheckBtn.textContent = "Checking destination...";
    chrome.runtime.sendMessage({ action: "recheck_url", url: activeUrl, tabId: activeTabId }, (resp) => {
      if (chrome.runtime.lastError) {
        statusEl.textContent = "Re-check failed";
        detailsEl.textContent = chrome.runtime.lastError.message;
        recheckBtn.disabled = false;
        recheckBtn.textContent = "Run security check";
        return;
      }

      if (!resp?.ok) {
        statusEl.textContent = "Re-check failed";
        detailsEl.textContent = resp?.error || "Unknown error";
        recheckBtn.disabled = false;
        recheckBtn.textContent = "Run security check";
        return;
      }

      setTimeout(() => {
        chrome.storage.local.get([RESULT_STORE_KEY], (data) => {
          const result = data[RESULT_STORE_KEY]?.[activeUrl];
          renderResult(result);
          recheckBtn.disabled = false;
          recheckBtn.textContent = "Run security check";
        });
      }, 500);
    });
  });
});
