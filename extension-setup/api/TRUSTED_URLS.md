# Trusted URL policy

`trusted_urls.json` contains the reviewed policy. `trusted_hosts` applies to every
HTTPS path and query on each exact hostname. It does not include arbitrary
subdomains, hostname lookalikes, HTTP URLs, credentials, or nonstandard ports.
The Google shared-content domains are trusted domain-wide by administrator choice.
Facebook/Messenger redirects, coingraph-update.com, mail.printakid.com, and
testcsfund.site.je are excluded. Redirect destinations are not automatically trusted.

The API retains the original model score and `ml_decision`. A policy match changes
the final verdict to `Proceed` / `Low` and adds `trusted_override: true` and an
explanation in `reason`. PhishTank and Google Safe Browsing threat matches still
block trusted URLs. Training artifacts and thresholds are unchanged.

Deploy `app.py`, `trusted_url_policy.py`, and `trusted_urls.json` with the existing
Render API service and keep its current URL. Installed extensions need no update.
Local edits alone do not change the deployed API. Existing browser verdict caches
may require a fresh check after deployment.

For historical logs, run from the Laravel project root:

```sh
php artisan logs:apply-trusted-policy
php artisan logs:apply-trusted-policy --apply
```

The first command previews the count. The second backs up matching rows to private
Laravel storage, then updates only matching Block/Suspicious rows in a transaction.
Original verdicts are also retained in each row's metadata; scores are unchanged.
Known threat-intelligence matches and excluded domains are left unchanged.
The command targets the database configured for that Laravel installation. Repeat
it on the hosting installation if it uses a different database. Existing alerts
are retained, since they are separate incident records.
