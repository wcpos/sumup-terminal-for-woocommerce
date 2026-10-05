# Changelog

## Unreleased

- A checkout the store terminated is confirmed cancelled on the next poll when SumUp has no transaction for it; a physical Solo never records one, so the till waited for the five-minute deadline.
- POS connection defaults to the Solo over the cloud when nothing is saved; the Bluetooth default hid the SumUp tile on every web and desktop till.
- A checkout SumUp refuses (for example a currency the merchant cannot take) reaches the till with SumUp's words and its HTTP status, so the POS stops instead of retrying.
- A `FAILED` transaction is reported as `declined_or_cancelled`: SumUp cannot tell a declined card from a cancel on the reader, and the POS no longer calls a decline a cancel.
- Add SumUp Solo server-mode checkout for WooCommerce POS Pro 1.11+, including Virtual Solo testing, asynchronous cancellation, verified transaction results and full or partial provider refunds.
- Add affiliate credentials and POS checkout diagnostics without replacing legacy order-pay checkout.
