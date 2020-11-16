<?php

return array(
    'server_name' => 'checkout.acme.com',
    'langs' => array(
        'en',
        'it'
    ),
    'brandName' => 'Acme Corp',
    'dbHost' => 'localhost',
    'dbName' => 'checkout_acme_com',
    'dbUser' => '',
    'dbPassword' => '',
    'bankName' => '',
    'bankIban' => '',
    'bankBic' => '',
    'bankSwift' => '',
    'stripeCheckoutLogo' => 'https://stripe.com/img/documentation/checkout/marketplace.png',
    'stripeSk' => 'sk_test_XXX',
    'stripePk' => 'pk_test_XXX',
    'paypalMeLink' => 'https://www.paypal.me/acme/',
    'clockWorkSmsApi' => 'XXX',
    'invoiceTaxPercentage' => 20,
    'smtpDebugLevel' => 0,
    'smtpHost' => 'ssl://smtp.gmail.com',
    'smtpUsername' => '',
    'smtpPassword' => '',
    'smtpSecure' => 'ssl',
    'smtpPort' => '465',
    'smtpSetFrom' => array(
        'info@acme.com',
        'Acme Corp'
    ),
    'smtpAddAddress' => array(
        array(
            'info@acme.com',
            'Acme Corp'
        )
    ),
    'smtpAddReplyTo' => array(
        'info@acme.com',
        'Acme Corp'
    ),
    'actionsHash' => array(
        'setItemAsPaid' => '',
        'setItemAsDead' => '',
    )
);

?>
