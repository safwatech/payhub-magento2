# Magento Marketplace submission — pre-filled

Form: https://commercemarketplace.adobe.com/seller/

| Field | Value |
| --- | --- |
| Extension Name | PayHub for Magento 2 |
| Composer Package | `payhub/module-payments` |
| Compatibility | Magento Open Source 2.4.6+, Adobe Commerce 2.4.6+, PHP 8.1+ |
| License | MIT |
| Categories | Payments & Security |
| Tags | payments, libya, sadad, moamalat, mobicash, tlync, arabic, multi-language |

## Long description

> PayHub for Magento 2 brings five Libyan payment service providers
> behind a single Magento payment method. Sadad OTP, Moamalat redirect,
> Mobicash QR, T-Lync, and Adfali all flow through one configuration
> screen with full English + Arabic UI, RTL checkout, and webhook
> reconciliation. Refunds from the order admin, encrypted secret
> storage, CSP-compliant lightbox embedding for hosted-fields PSPs.

## Validation checklist (Marketplace EQP runs this on submission)

- [ ] `composer.json` declares `"type": "magento2-module"` ✅
- [ ] `registration.php` registers the module name `Payhub_Payments` ✅
- [ ] `etc/module.xml` `setup_version` matches `composer.json` version ✅
- [ ] `etc/csp_whitelist.xml` for any external script/iframe hosts ✅
- [ ] No use of deprecated `AbstractMethod` for new code: **partial — we use it for backwards compatibility with 2.4.6**
- [ ] `MFTF` test stub in `Test/Mftf/`: **not yet — Phase 6 add**
- [ ] PHPCS Magento2 standard clean: **run `vendor/bin/phpcs --standard=Magento2 .`**

## Submission timeline

EQP review is typically 4–6 weeks for first-time submissions.
Findings are returned as a structured report; respond and resubmit
without losing your place in the queue.
