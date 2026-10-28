# Automatic campus computer names

Deploy the Laravel changes and run the devices migration before using extension version 1.2.
For each campus folder, set `logging_api_url` in `config.json` to your deployed Laravel endpoint,
for example `https://your-monitoring-domain.example/api/phishing-logs`.
Keep `campus_name` set to that folder's campus. No computer number is needed.
The registration endpoint is derived automatically from the logging URL.

Install the appropriate campus folder through Chrome's Load unpacked option, or reload an
existing installation. On its first successful URL assessment, the extension registers with
Laravel and receives a name such as SLSU-TO-PC-001. The UUID and assigned identity are stored
in chrome.storage.local (`phishblockDeviceUuid` and `phishblockDevice`).
The dashboard shows this assigned name. Detection timestamps remain UTC in transit and
are displayed in Philippine time.

The UUID survives browser and PC restarts and extension updates. Each browser profile has
its own identity. Uninstalling the extension or clearing its local storage creates a new
identity; cloning browser profile storage can copy an identity. This does not read the
Windows hostname or rename the extension in Chrome. Failed registration retries on the
next assessment; failed log submissions are not queued. Blank logging URLs disable
registration and logging while URL assessments continue.

Existing logs and older extensions with manual computer numbers remain supported. Automatic
allocation skips existing log numbers; retire manual installations before rolling out
automatic registration to avoid future overlap with manually chosen numbers.
