<?php
require_once "SimpleCheckout.class.php";
$DSC = new \Deirde\SimpleCheckout\SimpleCheckout();
?>

<!DOCTYPE html>
<html lang="<?php echo $DSC->getRegistry()->locale; ?>">
    <head>
        <meta charset="UTF-8">
        <title><?php echo $DSC->getPageTitle(); ?></title>
        <meta name="robots" content="noindex, follow">
        <?php echo $DSC->loadAssets(); ?>
    </head>
    <body>

        <div id="stains-1">
            &nbsp;
        </div>
        <div id="stains-2">
            &nbsp;
        </div>
        <div id="stains-3">
            &nbsp;
        </div>
        <table align="center">
            <tr>
                <td id="main" align="center">
                    <img id="logo" src="/assets/<?php echo $DSC->_('logoFilename'); ?>"
                        title="<?php echo $DSC->getConfig('brandName'); ?>" />
                    <h1>
                        <?php echo $DSC->_('subject'); ?>
                        <small>
                             <?php echo $DSC->getMaster()->description; ?>
                        </small>
                        <br />
                        <br />
                        <?php echo $DSC->_($DSC->getMaster()->currency); ?>
                        <small>
                            <?php echo money_format('%.2n',
                                $DSC->getMaster()->amount_due); ?>
                        </small>
                    </h1>
                    <h3>
                        <?php echo $DSC->_('customer') . ": "; ?>
                        <span class="black">
                            <?php echo $DSC->getRegistry()->title; ?>
                        </span>
                        <br />
                        <?php echo $DSC->_('orderId') . ": "; ?>
                        <span class="black">
                            <?php echo $DSC->getMaster()->sku; ?>
                        </span>
                        <?php echo " ~ " . $DSC->_('expiration') . ": "; ?>
                        <span class="black">
                            <?php echo date('d/m/Y',
                                strtotime($DSC->getMaster()->expiration_timestamp)); ?>
                        </span>
                    </h3>
                    <img id="loading-spinner" src="/assets/loading-spinner.gif"
                        title="<?php echo $DSC->_('loadingOnProgress'); ?>" />
                    <button id="pay-button-credit-card" type="button"
                        class="btn btn-primary pay-button">
                        <?php echo $DSC->_('payWithCreditCard'); ?>
                    </button>
                    <?php if (!empty($DSC->getConfig('paypalMeLink'))) { ?>
                        <button id="pay-button-paypal" type="button"
                            class="btn btn-primary btn-warning pay-button">
                            <?php echo $DSC->_('payWithPaypal'); ?>
                        </button>
                    <?php } ?>
                    <?php if (!empty($DSC->getConfig('bankName'))) { ?>
                        <h3 id="pay-direct-bank-info">
                            <?php echo $DSC->_('payWithDirectBankTransfer') . ": "; ?>
                            <br />
                            <br />
                            <?php echo $DSC->_('bankName') . ": " .
                                $DSC->getConfig('bankName'); ?>
                            <br />
                            <?php echo $DSC->_('bankIban') . ": " .
                                $DSC->getConfig('bankIban') . ", "; ?>
                            <?php echo $DSC->_('bankBic') . ": " .
                                $DSC->getConfig('bankBic') . ", "; ?>
                            <?php echo $DSC->_('bankSwift') . ": " .
                                $DSC->getConfig('bankSwift'); ?>
                            <br />
                            <?php echo $DSC->_('directBankPaymentProof'); ?>
                        </h3>
                    <?php } ?>
                    <h2 id="transaction-ok">
                        <?php echo $DSC->_('transactionCompleted'); ?>
                        <br />
                        <small>
                            <?php echo $DSC->_('paymentReceived'); ?>
                            <br />
                            <?php echo $DSC->_('weSendInvoice'); ?>
                        </small>
                    </h2>
                    <h2 id="transaction-ko">
                        <?php echo $DSC->_('transactionFailed'); ?>
                        <br />
                        <small>
                            <?php echo $DSC->_('tryAgain'); ?>
                        </small>
                    </h2>
                </td>
            </tr>
            <tr>
                <td id="footer" align="center">
                    <?php echo $DSC->_('footerLine'); ?>
                </td>
            </tr>
        </table>
    </body>
</html>
