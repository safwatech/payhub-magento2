# Changelog

## 0.2.0 (2026-05-08)

First functional release. Native Magento 2 / Adobe Commerce payment
module wired to the PayHub PHP SDK.

### Features

- Payment method `payhub` registered via `etc/payment.xml`,
  `etc/config.xml`, and `Model/Method/Payhub.php`.
- Admin config screen at **Stores → Configuration → Sales →
  Payment Methods → PayHub**: enabled, title, environment
  (production / sandbox / custom), API key + webhook secret
  (encrypted), default PSP, debug logging.
- Knockout payment renderer (`view/frontend/web/js/view/payment/`)
  collecting MSISDN and birth-year fields for Sadad.
- After place-order, customer is redirected to
  `/payhub/flow/index/id/<order_id>` which renders one of:
  - Auto-submitting redirect form (POST with fields).
  - OTP form posting to `/payhub/otp/submit`.
  - QR via api.qrserver.com proxy + 3-second status poll.
  - Lightbox embed + status poll.
- `POST /payhub/webhook` verifies `Hub-Signature` via
  `Payhub\WebhookEvent::verify`. On `payment.succeeded` it auto-
  invoices via `prepareInvoice` + `CAPTURE_OFFLINE`. Idempotent —
  applied event IDs are recorded on the order's payment additional
  information.
- Refund support via `process_refund` (full + partial) calling
  `payments->refund`.
- CSP whitelist entries for `app.payhub.ly`, `demo.payhub.ly`,
  `tnpg.moamalat.net`, and `api.qrserver.com`.
- Bilingual i18n (English + Arabic Libya — `i18n/ar_LY.csv`).

### Known limitations

- **Multi-PSP customer selection** — only the configured default PSP
  is offered; a per-customer PSP picker lands in v0.3.
- **Integration tests** — module is tested end-to-end manually
  against a Docker Magento 2.4.6 install; no `Tests/Integration/`
  suite yet.
- **Hyvä theme support** — Knockout renderer targets stock Luma /
  Hyvä admin only; Hyvä-checkout-specific React renderer is on the
  Phase 6 roadmap.

## 0.1.0 (2026-05-08)

Initial scaffolding. Module registers cleanly but defines no payment
method.
