document.addEventListener("DOMContentLoaded", async () => {
  const RESULT_STORE_KEY = "phishResultsByUrl";
  const urlEl = document.getElementById("url");
  const statusEl = document.getElementById("status");
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
      statusEl.textContent = result.status || result.decision || result.risk_level || "Unknown";
      detailsEl.textContent = `Score: ${result.score ?? "N/A"} - Reason: ${result.reason ?? JSON.stringify(result.flags) ?? ""}`;
      return;
    }

    statusEl.textContent = "Not checked yet";
    detailsEl.textContent = "";
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
    chrome.runtime.sendMessage({ action: "recheck_url", url: activeUrl, tabId: activeTabId }, (resp) => {
      if (chrome.runtime.lastError) {
        statusEl.textContent = "Re-check failed";
        detailsEl.textContent = chrome.runtime.lastError.message;
        return;
      }

      if (!resp?.ok) {
        statusEl.textContent = "Re-check failed";
        detailsEl.textContent = resp?.error || "Unknown error";
        return;
      }

      setTimeout(() => {
        chrome.storage.local.get([RESULT_STORE_KEY], (data) => {
          const result = data[RESULT_STORE_KEY]?.[activeUrl];
          renderResult(result);
        });
      }, 500);
    });
  });
});
