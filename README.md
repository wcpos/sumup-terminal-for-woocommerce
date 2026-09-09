## SumUp Terminal for WooCommerce

### Setup

1. Check [releases](https://github.com/wcpos/sumup-terminal-for-woocommerce/releases) for the latest version of the plugin.
2. Download the **sumup-terminal-for-woocommerce.zip** file.
3. Install & activate the plugin via `WP Admin > Plugins > Add New > Upload Plugin`.
<img alt="Gateway Settings" src="https://github.com/user-attachments/assets/ef6858f6-79a2-4436-8411-8bf80a617437" />

4. Go to `WP Admin > WooCommerce > Settings > Payments > SumUp Terminal` and enter your [SumUp API key](https://developer.sumup.com/api/). Note: you do not need to enable the SumUp Terminal here, the Terminal will be enabled for the POS in a later step.
<img alt="sumup-settings" src="https://github.com/user-attachments/assets/54fcfa61-0ad4-435a-aaea-ecce7ec06f23" />

5. Pair your SumUp Terminal: On the same settings page, enter the pairing code displayed on your SumUp device and click "Pair Reader". The reader must be successfully paired before you can process payments.

6. Go to `WP Admin > WooCommerce POS > Settings > Checkout > enable` the SumUp Terminal gateway.
<img alt="enable-sumup-terminal" src="https://github.com/user-attachments/assets/9a0ed9a6-52d4-4d9a-9269-77182c5f94f7" />


### Checkout

1. Selecting the SumUp Terminal gateway will allow you to start a new payment, or cancel a payment currently in process. The order will automatically complete once a successful payment is detected.
<img alt="sumup-checkout" src="https://github.com/user-attachments/assets/b6a8ab0d-9295-4195-afc4-f9af9bf12886" />

### WooCommerce POS 1.11 checkout

With WooCommerce POS Pro 1.11.0 or newer, SumUp Solo accepts payments through the
POS checkout tile using the cloud Readers API; Virtual Solo works for testing.
In the SumUp gateway settings, enter both **Affiliate App ID** and **Affiliate Key**
from [SumUp Affiliate Keys](https://developer.sumup.com/tools/authorization/affiliate-keys/)
for the new server checkout integration. These credentials are sent only when both
fields are filled; they do not change legacy checkout. SumUp Air is not supported
by this cloud integration.

The result webhook URL is supplied automatically when a payment starts. The
existing order-pay checkout remains available, including without Pro 1.11+.
