<?php

/**
 * This solution is suitable for freelances, with a limited number of customers, selling recurring products.
 * This processes the items generating PDFs and sending notices via e-mail and SMS.
 * The notices are sent exclusively to the administrator for review and approvation.
 *
 * The purpose of this tool is to make possible the administration and invoicing
 * related to recurring products and services just checking the mailbox.
 */
namespace Deirde\SimpleCheckout {

    /**
     * PHP configuration
     */
    error_reporting(E_STRICT|E_ALL);
    ini_set('display_errors', '1');
    date_default_timezone_set('UTC');

    /**
     * Dependecies
     */
    $loader = require __DIR__ . '/../vendor/autoload.php';
    class_alias('\RedBeanPHP\R','\R');
    use Dompdf\Dompdf;

    /**
     * The class.
     */
    class SimpleCheckoutCli {

        /**
         * The constants definition.
         */
        const ITEM_STATUS_ACTIVE = 1;
        const DIR_STORAGE = '/../storage/';

        /**
         * The private variables definition.
         */
        private $_config = array();
        private $_locale = array();
        private $_items = array();
        private $_cycles = array(
            1 => 'P1W',
            2 => 'P2W',
            3 => 'P3W',
            4 => 'P1M',
            5 => 'P2M',
            6 => 'P3M',
            7 => 'P4M',
            8 => 'P6M',
            9 => 'P1Y',
        );
        private $_registry = array();

        /**
         * The ORM setup.
         * http://www.redbeanphp.com/index.php
         * This method is triggered by the class constructor.
         */
        private function _db() {

            \R::setup(
                'mysql:host=' . $this->_getConfig('dbHost') . ';dbname=' .
                    $this->_getConfig('dbName'),
                $this->_getConfig('dbUser'),
                $this->_getConfig('dbPassword')
            );

        }

        /**
         * Set the locale code and translations by the appropriate csv file.
         * This method is triggered by the class constructor.
         */
        private function _setLocale() {

            foreach ($this->_getConfig('langs') as $locale) {

                $__ = array();
                if (file_exists($file = __DIR__ .
                    '/../config/locale/' . $locale . '.csv')) {
                    foreach (array_map('str_getcsv', file($file)) as $_) {
                        $__[$_[0]] = $_[1];
                    }
                }

                $this->_locale[$locale] = $__;

            }

        }

        /**
         * Retrieve the translation by the key as a parameter.
         * If the translation is not found it arises an exception.
         */
        private function _($locale = 'en', $key, $replace) {

            $response = $this->_locale[$locale];

            if (!isset($response[$key])) {
                return false;
            } else {

                $response = $response[$key];

                if (count($replace) > 0) {
                    for ($i= 0; $i <= 9; $i++) {
                        if (isset($replace[$i])) {
                            $response = str_replace(
                                "{" . $i . "}",
                                $replace[$i],
                                $response);
                        }
                    }
                }

                return $response;

            }

        }

        /**
         * Set the registry object found in the database
         * by the <client__id> property of the master object.
         */
        private function _setRegistry($client__id) {

            if (!isset($this->_registry[$client__id])) {
                $this->_registry[$client__id] = \R::findOne('registry',
                    ' id = ? ', array($client__id));
            }

        }

        /**
         * Retrieve the registry object.
         */
        private function _getRegistry($client__id) {

            return $this->_registry[$client__id];

        }

        /**
         * Generate the invoice's PDF and save it on disc.
         */
        private function _generatePdf($item) {

            /**
             * Gets the registry customer data.
             */
            $this->_setRegistry($item['client__id']);

            /**
             * Set the locale code based on registry.
             */
            //setlocale(LC_MONETARY, 'en_GB');

            /**
             * Get the contents of the template.
             */
            $contents = file_get_contents(__DIR__ .
                '/../config/template/template-invoice.html');

            /**
             * Set the internationalized texts.
             */
            foreach ($this->_locale[$this->_getRegistry(
                $item['client__id'])->locale] as $key => $val) {
                $contents = str_replace($key, $val, $contents);
            }

            /**
             * Set the variable data.
             */
            $_ = array(
                '{lang}' => $this->_getRegistry($item['client__id'])->locale,
                '{pageTitle}' => '',
                '{sInvoiceId}' => $this->_invoiceId($item),

                '{logoImgPath}' =>  __DIR__ . '/assets/' . $this->_locale[$this->_getRegistry(
                    $item['client__id'])->locale]['logoFilename'],
                '{invoiceYear}' => date(
                    'Y',
                    strtotime($item['expiration_timestamp'])
                ),

                '{skuVal}' => strtoupper($item['sku']),

                '{sExpirationDateVal}' => date(
                    'd/m/Y',
                    strtotime($item['expiration_timestamp'])
                ),

                '{paymentDueVal}' => strtoupper($item['currency']) . ' ' .
                    money_format('%i', $item['amount_due']),

                '{invoiceToVal}' => str_replace(chr(10), "<br />",
                    $this->_getRegistry($item['client__id'])->invoice_heading),

                '{itemSubjectVal}' => strtoupper($item['subject']),
                '{itemDescriptionVal}' => $item['description'],
                '{currencyVal}' => strtoupper($item['currency']),
                '{priceVal}' => money_format('%i', $item['amount_due']),
                '{taxVal}' => $this->_getConfig('invoiceTaxPercentage') . '%',
                '{paymentUrl}' => 'https://' . $this->_getConfig('server_name') . '?sku=' . $item['sku']
            );
            foreach ($_ as $key => $val) {
                $contents = str_replace($key, $val, $contents);
            }

            /**
             * Convert the contents into a PDF.
             */
            $dompdf = new Dompdf();
            $dompdf->loadHtml($contents);
            $dompdf->setPaper('A4', 'portrait');
            $dompdf->render();

            /**
             * Force the browser the open PDF, for testing purposes.
             */
            //$dompdf->stream();

            /**
             * This saves the PDF to disc.
             */
            $dir = __DIR__ . self::DIR_STORAGE;
            shell_exec('mkdir -p ' . $dir);
            $filePath = $dir . 'invoice_' . $this->_invoiceId($item) . '.pdf';
            //shell_exec('rm -p ' . $filePath);

            file_put_contents($filePath, $dompdf->output());

        }

        /**
         * Get the PDF file path fron the item.
         */
        private function _getPdfFilePath($item) {

            $dir = __DIR__ . self::DIR_STORAGE;
            $filePath = $dir . 'invoice_' . $this->_invoiceId($item) . '.pdf';
            if (file_exists($filePath)) {
                return $filePath;
            } else {
                return false;
            }

        }

        /**
         * Generate the unique invoice ID.
         */
        private function _invoiceId($item) {

            $_ = \R::findOne('master', ' sku = ? ', array($item['sku']));

            if ($_['invoice_no'] === NULL) {

                /**
                 * Get the correct invoice number.
                 */
                $response = \R::getCell('SELECT MAX(invoice_no) + 1 FROM master');

                /**
                 * Updates the object into the database.
                 */
                $_ = \R::findOne('master', ' sku = ? ', array($item['sku']));
                $_->setAttr('invoice_no', $response);
                \R::store($_);


            } else {

                $response = $_['invoice_no'];

            }

            return date('y', strtotime($_['insert_time'])) . str_pad($response, 3, '0', STR_PAD_LEFT);

        }

        /**
         * Send to the administrator the e-mail with the summary.
         */
        private function _sendEmail($item) {

            /**
             * Get the contents of the template.
             */
            $contents = file_get_contents(__DIR__ .
                '/../config/template/template-email-info.html');

            /**
             * Set the internationalized texts.
             */
            foreach ($this->_locale[$this->_getRegistry(
                $item['client__id'])->locale] as $key => $val) {
                $contents = str_replace($key, $val, $contents);
            }

            /**
             * Set the variable data.
             */
            $registry = $this->_getRegistry($item['client__id']);
            $_ = array(
                '{registryTitle}' => $registry->title,

                '{masterSummary}' => strtoupper($item['subject']) .
                    ' - ' . $item['description'] . ' - ' .
                    strtoupper($item['currency']) . ' ' .
                    money_format('%i', $item['amount_due']) . ' - ' .
                    ucfirst($this->_locale[$registry->locale]['expiration']) . ' ' .
                    date('d/m/Y', strtotime($item['expiration_timestamp'])),

                '{currencyVal}' => strtoupper($item['currency']),

                '{priceVal}' => money_format(
                    '%i',
                    $item['amount_due']
                ),

                '{amountDueVal}' => money_format(
                    '%i',
                    $item['amount_due']
                ),

                '{sExpirationDateVal}' => date(
                    'd/m/Y',
                    strtotime($item['expiration_timestamp'])
                ),

                '{sExpirationInVal}' => $this->_countdown($item['expiration_timestamp']),
                '{recipientEmailVal}' => $registry->contact_email,
                '{paymentUrl}' => 'https://' . $this->_getConfig('server_name') . '?sku=' . $item['sku'],
                '{registryPhoneVal}' => $registry->contact_phone,

                '{smsRecipientEmailVal}' => $registry->contact_phone . '@' .
                    $this->_getConfig('clockWorkSmsApi') . '.clockworksms.com',

                '{markAsPaid}' => '<a href="https://' .
                    $this->_getConfig('server_name') . '?sku=' . $item['sku'] . '&action=' .
                    $this->_getConfig('actionsHash')['setItemAsPaid'] . '">Mark this item as PAID</a>',

                '{markAsDead}' => '<a href="https://' .
                    $this->_getConfig('server_name') . '?sku=' . $item['sku'] . '&action=' .
                    $this->_getConfig('actionsHash')['setItemAsDead'] . '">Mark this item as DEAD</a>',
            );
            foreach ($_ as $key => $val) {
                $contents = str_replace($key, $val, $contents);
            }

            $contents = str_replace(chr(10), "<br />", $contents);

            /**
             * Send the e-mail.
             */
            $mail = new \PHPMailer;
            $mail->CharSet = 'UTF-8';
            $mail->isSMTP();
            $mail->SMTPDebug = $this->_getConfig('smtpDebugLevel');
            $mail->Host = $this->_getConfig('smtpHost');
            $mail->SMTPAuth = true;
            $mail->Username = $this->_getConfig('smtpUsername');
            $mail->Password = $this->_getConfig('smtpPassword');
            $mail->SMTPSecure = $this->_getConfig('smtpSecure');
            $mail->Port = $this->_getConfig('smtpPort');

            $mail->setFrom(
                $this->_getConfig('smtpSetFrom')[0],
                $this->_getConfig('smtpSetFrom')[1]
            );

            foreach ($this->_getConfig('smtpAddAddress') as $addAddress) {
                $mail->addAddress($addAddress[0], $addAddress[1]);
            }

            $mail->addReplyTo(
                $this->_getConfig('smtpAddReplyTo')[0],
                $this->_getConfig('smtpAddReplyTo')[1]
            );

            if ($pdfFilePath = $this->_getPdfFilePath($item)) {
                $mail->addAttachment(
                    $pdfFilePath,
                    $this->_locale[$registry->locale]['{invoice}'] . '_' .
                        $this->_invoiceId($item) . '.pdf'
                );
            }
            $mail->isHTML(true);

            $mail->Subject = $this->_getRegistry($item['client__id'])->title .
                ' - ' . $this->_locale[$registry->locale]['{adminEmailSubject}'];

            $mail->Body = $contents;
            $mail->AltBody = $contents;
            if (!$mail->send()) {
                echo $mail->ErrorInfo;
            }
            $mail->ClearAllRecipients();

        }

        /**
         * Set the active items to process.
         */
        private function _setItems() {

            $this->_items = \R::getAll('SELECT * FROM master WHERE `status` = ' .
                self::ITEM_STATUS_ACTIVE);

        }

        /**
         * Process the items.
         */
        private function _processItems() {

            foreach ($this->_items as $item) {

                /**
                 * Since now on, when the class is loaded it does what's below.
                 */
                if ($this->_countdown($item['expiration_timestamp']) <= $item['timeline']) {

                    /**
                     * Generate the next in cycle, if it is a recurring product.
                     */
                    $this->_generateCycle($item);

                    /**
                     * Generate the invoice PDF.
                     */
                    $this->_generatePdf($item);

                    /**
                     * Send the notification to the administrator.
                     */
                    $this->_sendEmail($item);
                }

            }

        }

        /**
         * Get the remaining days before the expiration.
         */
        private function _countdown($expirationTimestamp) {

            $nowDateTime = new \DateTime("now");
            $expirationDateTime = new \DateTime($expirationTimestamp);
            return (integer) $nowDateTime->diff($expirationDateTime)->format('%R%a');

        }

        /**
         * Generate the next recurring product.
         */
        private function _generateCycle($item) {

            if ((integer) $item['cycle'] > 0) {

                /**
                 * Create the new item and sets the attributes.
                 */
                $_ = \R::dispense('master');

                unset($item['id']);
                unset($item['invoice_no']);

                foreach ($item as $attrKey => $attrVal) {
                    $_->setAttr($attrKey, $attrVal);
                }

                /**
                 * Set the new <sku> and <expiration_timestamp> values.
                 */
                $expirationDateTime = new \DateTime($item['expiration_timestamp']);
                $itemCycle = $this->_cycles[$item['cycle']];
                $_->setAttr(
                    'expiration_timestamp',
                    $expirationDateTime->add(
                        new \DateInterval($itemCycle)
                    )->format('Y-m-d')
                );
                $_->setAttr('sku', uniqid());

                /**
                 * Save the new object into the database.
                 */
                \R::store($_);

                /**
                 * Erase the cycle and updates the old item.
                 */
                $_ = \R::findOne('master', 'sku = ? ', [ $item["sku"] ]);
                $_->setAttr('cycle', 0);

                /**
                 * Update the old object into the database.
                 */
                \R::store($_);

            }

        }

        /**
         * Retrive one config value by the <key> as a parameter.
         */
        private function _getConfig($key) {

            return $this->_config[$key];

        }

        /**
         * The class construct contains the bootstrap and mandatory behaviors:
         * $this->_config
         * $this->_db()
         * $this->_setLocale()
         * $this->_setItems()
         * $this->_processItems()
         */
        public function __construct() {

            /**
             * Inject the array contained in the configuration file.
             */
            $this->_config = require __DIR__ . '/../config/_config.php';

            /**
             * The ORM setup.
             */
            $this->_db();

            /**
             * Set the locale and the translations.
             */
            $this->_setLocale();

            /**
             * Arguments passed to script
             */
            global $argv;
            if (isset($argv[1]) && $argv[1] === 'generateAllPdf') {

                /**
                 * This generates all the PDF invoices files considering
                 * the already paid items and the actives still to be paid.
                 * For administration purposes.
                 */
                $this->_generateAllPdf();

            } elseif (isset($argv[1])) {

                echo "The parameter specified isn't valid." . PHP_EOL;

            } else {

                /**
                 * Set the active items to process.
                 */
                $this->_setItems();

                /**
                 * Process the items.
                 */
                $this->_processItems();

            }

            $this->_report();

        }

        /**
         * Generate PDF files for all the active items.
         */
        private function _generateAllPdf() {

            $items = \R::getAll('SELECT * FROM master WHERE `invoice_no` IS NOT NULL');
            foreach ($items as $item) {
                $this->_generatePdf($item);
            }

        }

        /**
         * Construct and send a report
         */
        private function _report() {

            $firstInvoice = \R::getRow("SELECT * FROM master ORDER BY `id` ASC");
            $firstYear = date('Y', strtotime($firstInvoice['expiration_timestamp']));
            $lastInvoice = \R::getRow("SELECT * FROM master ORDER BY `id` DESC");
            $lastYear = date('Y', strtotime($lastInvoice['expiration_timestamp']));
            $content = '';

            for ($i = $firstYear; $i <= $lastYear; $i++) {

                /**
                 * @TODO: Actually the total is calculated without considering the currency used
                 */
                $total = array(0 => 0, 1 => 0, 2 => 0);
                $items = \R::getAll("SELECT * FROM master WHERE `expiration_timestamp` BETWEEN  '"
                    . $i . "/04/01' AND '" . ($i + 1). "/03/31'");
                foreach ($items as $item) {
                    $total[$item['status'] + 1] += $item['amount_due'];
                }

                /**
                 * Email content
                 */
                $content .= '<b>For year <' . $i . '>, from ' . $i . '/04/01 to ' . $i . '/03/31</b>' . PHP_EOL;
                $content .= 'Deads: ' . $total[0] . PHP_EOL;
                $content .= 'Paid: ' . $total[1] . PHP_EOL;
                $content .= 'Open: ' . $total[2] . PHP_EOL;
                $content .= PHP_EOL;

            }

            /**
             * Send the e-mail.
             */
            $mail = new \PHPMailer;
            $mail->CharSet = 'UTF-8';
            $mail->isSMTP();
            $mail->SMTPDebug = $this->_getConfig('smtpDebugLevel');
            $mail->Host = $this->_getConfig('smtpHost');
            $mail->SMTPAuth = true;
            $mail->Username = $this->_getConfig('smtpUsername');
            $mail->Password = $this->_getConfig('smtpPassword');
            $mail->SMTPSecure = $this->_getConfig('smtpSecure');
            $mail->Port = $this->_getConfig('smtpPort');

            $mail->setFrom(
                $this->_getConfig('smtpSetFrom')[0],
                $this->_getConfig('smtpSetFrom')[1]
            );

            foreach ($this->_getConfig('smtpAddAddress') as $addAddress) {
                $mail->addAddress($addAddress[0], $addAddress[1]);
            }

            $mail->isHTML(true);
            $mail->Subject = 'Business report';

            $content = str_replace(chr(10), "<br />", $content);
            $mail->Body = $content;
            $mail->AltBody = $content;
            if (!$mail->send()) {
                echo $mail->ErrorInfo;
            }
            $mail->ClearAllRecipients();

        }

        /**
         * The class destruct method triggered when the PHP process dies.
         */
        public function __destruct() {

            /**
             * Close the database connection.
             */
            \R::close();

            /**
             * Output.
             */
            echo "The procedure has been completed, bye bye." . PHP_EOL;

        }

    }

    /**
     * This procedure is allowed only from CLI.
     */
    if (php_sapi_name() === "cli") {

        new \Deirde\SimpleCheckout\SimpleCheckoutCli();

    } else {

        header("HTTP/1.0 404 Not Found");
        exit();

    }

}

?>
