# Funnel Webhook Demo

Local demo of a lead/order funnel: HTML form → JavaScript validation + `fetch()` → PHP webhook, plus example Stripe signature verification and a Meta Conversions API–style event payload.

## What’s included

| File | Role |
|------|------|
| `index.html` | Lead / order form |
| `styles.css` | Simple dark UI |
| `script.js` | Client validation + `fetch()` POST to the webhook |
| `webhook.php` | Receives JSON, validates lead/order, stores a log line |
| `stripe-webhook.php` | HMAC signature handling patterned after Stripe webhooks |
| `meta-event.php` | Builds a hashed Meta CAPI-style event (dry-run by default) |
| `Dockerfile` / `docker-compose.yml` | PHP 8.3 + Apache — no local PHP install needed |

## Requirements (Windows 11)

1. [Docker Desktop](https://www.docker.com/products/docker-desktop/) installed and running
2. This project folder

You do **not** need PHP installed on Windows.

## Run locally with Docker

From this project directory in PowerShell:

```powershell
docker compose up --build
```

Open:

- Form: http://localhost:8080/
- Webhook: `POST` http://localhost:8080/webhook.php

Stop with `Ctrl+C`, or in another terminal:

```powershell
docker compose down
```

Edits to PHP/HTML/JS on your machine are reflected immediately (folder is mounted into the container).

## Try the form

1. Open http://localhost:8080/
2. Fill name, email, product
3. Click **Send to webhook**
4. The response panel shows HTTP status + JSON from `webhook.php`
5. Accepted leads are appended to `storage/leads.jsonl` inside the project folder

## Test Stripe webhook signature (demo)

Default secret (also set in `docker-compose.yml`): `whsec_demo_replace_me`

PowerShell example:

```powershell
$secret = "whsec_demo_replace_me"
$body = '{"id":"evt_demo_1","type":"checkout.session.completed"}'
$t = [int](Get-Date -UFormat %s)
$signed = "$t.$body"
$hmac = [System.BitConverter]::ToString(
  (New-Object System.Security.Cryptography.HMACSHA256 (
    [Text.Encoding]::UTF8.GetBytes($secret)
  )).ComputeHash([Text.Encoding]::UTF8.GetBytes($signed))
).Replace("-", "").ToLower()

Invoke-RestMethod -Method POST -Uri "http://localhost:8080/stripe-webhook.php" `
  -ContentType "application/json" `
  -Headers @{ "Stripe-Signature" = "t=$t,v1=$hmac" } `
  -Body $body
```

## Test Meta event payload (dry-run)

```powershell
Invoke-RestMethod -Method POST -Uri "http://localhost:8080/meta-event.php" `
  -ContentType "application/json" `
  -Body '{"email":"lead@example.com","phone":"+15550100","event_name":"Lead","value":79,"currency":"USD","content_name":"growth"}'
```

To send a live request to Meta’s Graph API, set `META_PIXEL_ID` and `META_ACCESS_TOKEN` in `docker-compose.yml`, then recreate the container:

```powershell
docker compose up --build -d
```

## What this demonstrates

- JavaScript form handling and client-side validation
- API / webhook request flow with `fetch()`
- PHP endpoint validation and JSON responses
- Append-only lead logging
- Stripe-style webhook signature verification
- Server-side Meta event payload shaping (SHA-256 hashed PII)

