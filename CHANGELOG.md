# Changelog

## Unreleased

- A POS checkout SumUp did not answer (a dropped connection, a timeout, an outage) now keeps the leg pending instead of dropping it while the checkout may be on the reader; a busy-reader refusal on the retry of such a leg keeps it pending too, since the checkout holding the reader may be this sale's, and the first checkout settles the leg through its result URL if the customer pays it.
- A refund from the POS of an order paid on the previous order-pay panel finds the SumUp transaction by the order's transaction id.
- A result delivery for an attempt the POS adopted on upgrade resolves to that attempt; a delivery for no POS payment is still acknowledged so SumUp stops retrying.
- Pro's provider conformance suite runs in CI against the real adapter over a scripted SumUp; the transcripts in `tests/conformance/transcripts` are the certified record.
- Requires WooCommerce POS Pro 2.0.0 or newer; the plugin registers nothing and shows an admin notice on older or missing Pro.
- Web checkout removed: SumUp Terminal is no longer offered on the shop's checkout, and the "Enable SumUp Terminal for web checkout" setting is gone. The POS keypad and the POS order-pay page are the only surfaces.
- The POS Legacy tab (order-pay page) runs through WooCommerce POS Pro's shared order-pay panel, so a Legacy-tab payment is a ledger row like a keypad payment; the plugin's own order-pay script and its payment AJAX endpoints are removed.
- Attempts the previous panel left mid-flight are folded into Pro's ledger once on upgrade, 25 orders per request; the legacy webhook leaves an adopted attempt to Pro.
- A cancel from the till is confirmed about three seconds after the tap instead of twelve: once SumUp's own delivery that the checkout ended without money has arrived after the terminate, the reader status is checked at once rather than after the ten-second grace.
- A checkout the store terminated is confirmed cancelled on the next poll when SumUp has no transaction for it; a physical Solo never records one, so the till waited for the five-minute deadline.
- POS connection defaults to the Solo over the cloud when nothing is saved; the Bluetooth default hid the SumUp tile on every web and desktop till.
- A checkout SumUp refuses (for example a currency the merchant cannot take) reaches the till with SumUp's words and its HTTP status, so the POS stops instead of retrying.
- A `FAILED` transaction is reported as `declined_or_cancelled`: SumUp cannot tell a declined card from a cancel on the reader, and the POS no longer calls a decline a cancel.
- Add SumUp Solo server-mode checkout for the WooCommerce POS 2.0 keypad, including Virtual Solo testing, asynchronous cancellation, verified transaction results and full or partial provider refunds.
- Add affiliate credentials and POS checkout diagnostics.
