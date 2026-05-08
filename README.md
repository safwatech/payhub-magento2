# PayHub for Magento 2

Native Magento 2 / Adobe Commerce payment module for
[PayHub](https://payhub.ly).

> **v0.1.0** is the scaffolding release — module registration and
> Composer manifest only. The full native gateway with all four
> `next_action` flows lands in **v0.2.0** (Phase 4 of the
> [plugin plan](../README.md#status)).

## Install

```bash
composer require payhub/module-payments
bin/magento module:enable Payhub_Payments
bin/magento setup:upgrade
bin/magento setup:di:compile
bin/magento cache:flush
```

Configure under **Stores → Configuration → Sales → Payment Methods → PayHub**
(lands in v0.2.0).

## Compatibility

- Magento 2.4.6+ / Adobe Commerce 2.4.6+
- PHP 8.1+
- MySQL 8.0+ or MariaDB 10.6+

## Roadmap

- v0.2.0 — Tier A native gateway, refunds, Arabic + RTL.
- v0.3.0 — Magento Marketplace submission.
