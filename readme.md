# SIMPLE RECURRING PAYMENTS #

Description
-----

A basic tool to lighten the administration and invoicing commitments by auto-generated invoices and reports them for review and approvation.


Requirements and setup
-----
- Fill the database with tables structure<br/>
- Put `/httpdocs/SimpleCheckout.cli.php` in a cron job scheduling the execution<br/>
- A Stripe account is required, https://stripe.com/gb<br/>
- A Paypal account with a PayPal.Me is required, https://www.paypal.com/paypalme/grab<br/>
- The SMS are sent with ClockworkSMS API, https://www.clockworksms.com.<br/>
- Move `/config/config.sample.php` to `/config/_config.php` and updated accordingly.<br/>
- Move `/config/locale/en.sample.csv` to `/config/locale/en.csv` and updated accordingly.<br/>
- Move `/config/template/template-email-info.sample.html` to `/config/template/template-email-info.html` and updated accordingly.<br/>
- Move `/config/template/template-invoice.sample.html` to `/config/template/template-invoice.html` and updated accordingly.