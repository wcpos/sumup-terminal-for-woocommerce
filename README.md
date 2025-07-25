## SumUp Terminal for WooCommerce

### Setup

1. Check [releases](https://github.com/wcpos/sumup-terminal-for-woocommerce/releases) for the latest version of the plugin.
2. Download the **sumup-terminal-for-woocommerce.zip** file.
3. Install & activate the plugin via `WP Admin > Plugins > Add New > Upload Plugin`.
<img width="909" alt="Gateway Settings" src="https://github.com/user-attachments/assets/ef6858f6-79a2-4436-8411-8bf80a617437" />

4. Go to `WP Admin > WooCommerce > Settings > Payments > SumUp Terminal` and enter your [SumUp API key](https://developer.sumup.com/api/). Note: you do not need to enable the SumUp Terminal here, the Terminal will be enabled for the POS in a later step.
<img width="901" alt="Screenshot 2024-12-25 at 7 48 08 PM" src="https://github.com/user-attachments/assets/18465660-4a74-42f6-bd3a-5485628d6d7e" />

5. Pair your SumUp Terminal: On the same settings page, enter the pairing code displayed on your SumUp device and click "Pair Reader". The reader must be successfully paired before you can process payments.

6. Go to `WP Admin > WooCommerce POS > Settings > Checkout > enable` the SumUp Terminal gateway.
<img width="739" alt="Enable in POS" src="https://github.com/user-attachments/assets/cadf6c97-27c7-4197-8783-2ba05ffee9ad" />

### Checkout

1. Selecting the SumUp Terminal gateway will allow you to start a new payment, or cancel a payment currently in process. The order will automatically complete once a successful payment is detected.
<img width="629" alt="Screenshot 2024-12-25 at 7 49 54 PM" src="https://github.com/user-attachments/assets/6aa4e96f-3a86-4019-b8ee-1d81223912f1" />
