<?php

/**
 * This is a simple checkout system providing a fast online payment through Stripe and Paypal.
 * Used together with the CLI class it provides the expirations and payment notices via e-mail and SMS.
 * 
 * Considered and covered cases:
 * 1. The product exists and it is active then it starts the checkout process.
 * 2. The product exists but it is not active.
 * 3. The product dosn't exist.
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
    
    /**
     * The class.
     */
    class SimpleCheckout {
        
        /**
         * Constants definition.
         */
        const DEFAULT_LOCALE = "en";
        const ITEM_STATUS_CLOSED = 0;
        const ITEM_CYCLE_OFF = 0;
        const ITEM_STATUS_DEAD = -1;
        
        /**
         * Private variables definition.
         */
        private $_config = array();
        private $_sku = '';
        private $_action = '';
        private $_processParams = array();
        private $_master;
        private $_registry = array();
        private $_locale = array();
        private $_pageTitle = '';
        
        /**
         * Force the HTTPS protocol.
         * This method is triggered by the class constructor.
         */
        private function _forceHttps() {
            
            if (empty($_SERVER['HTTPS'])) {
                header('HTTP/1.1 301 Moved Permanently');
                header('Location: https://' . $_SERVER['HTTP_HOST'] . 
                    $_SERVER['REQUEST_URI']);
                exit();
            }
            
        }
        
        /**
         * Set the <sku> needed to proceed further.
         * The <sku> represents the unique order ID.
         * This method is triggered by the class constructor.
         */
        private function _setSku() {
            
            $gump = new \GUMP();
            $_REQUEST = $gump->sanitize((array)$_REQUEST);
            
            $gump->validation_rules(array(
                'sku' => 'required|max_len,100|min_len,6'
            ));
            
            $gump->filter_rules(array(
                'sku' => 'trim|sanitize_string'
            ));
            
            $response = $gump->run((array)$_REQUEST);
            
            if (is_array($response)) {
                $response = $response['sku'];
            }
            
            $this->_sku = $response;
            
        }
        
        /**
         *
         */
        private function _setAction() {
            
            $gump = new \GUMP();
            $_REQUEST = $gump->sanitize((array)$_REQUEST);
            
            $gump->validation_rules(array(
                'action' => 'required|max_len,32|min_len,32'
            ));
            
            $gump->filter_rules(array(
                'action' => 'trim|sanitize_string'
            ));
            
            $response = $gump->run((array)$_REQUEST);
            
            if (is_array($response)) {
                $response = $response['action'];
            }
            
            $this->_action = $response;
            
        }
        
        /**
         * Validate and set the mandatory parameters
         * to process the Stripe checkout.
         * This method is triggered by the class constructor.
         */
        private function _setProcessParams() {
            
            $gump = new \GUMP();
            $_POST = $gump->sanitize((array)$_POST);
            
            $gump->validation_rules(array(
                'id' => 'required|max_len,100|min_len,1',
                'email' => 'required|valid_email',
                'currency' => 'required|max_len,100|min_len,1',
                'amount' => 'required|max_len,100|min_len,1',
                'description' => 'required|max_len,100|min_len,1'
            ));
            
            $gump->filter_rules(array(
                'id' => 'trim|sanitize_string',
                'email' => 'trim|sanitize_email',
                'currency' => 'trim|sanitize_string',
                'amount' => 'trim|sanitize_string',
                'description' => 'trim|sanitize_string'
            ));
            
            $this->_processParams = $gump->run((array)$_POST);
            
        }
        
        /**
         * The ORM setup.
         * http://www.redbeanphp.com/index.php
         * This method is triggered by the class constructor.
         */
        private function _db() {
            
            \R::setup(
                'mysql:host=' . $this->getConfig('dbHost') . ';dbname=' . 
                    $this->getConfig('dbName'),
                $this->getConfig('dbUser'),
                $this->getConfig('dbPassword')
            );
            
        }
        
        /**
         * Set the master object found in the database
         * by the <sku> as parameter.
         * This method is triggered by the class constructor.
         */
        private function _setMaster(string $sku) {
            
            $this->_master = \R::findOne('master', 'sku = ? ', [ $sku ]);
            
        }
        
        /**
         * Set the registry object found in the database
         * by the <client__id> property of the master object.
         * This method is triggered by the class constructor.
         */
        private function _setRegistry(string $client__id) {
            
            $this->_registry = \R::findOne('registry', 'id = ? ', [ $client__id ]);
            
        }
        
        /**
         * Set the locale code and translations by the appropriate csv file.
         * This method is triggered by the class constructor.
         */
        private function _setLocale(string $locale) {
            
            if ($locale === NULL) {
                $locale = self::DEFAULT_LOCALE;
            }
            
            $__ = array();
            if (file_exists($file = __DIR__ . '/../config/locale/' . $locale . '.csv')) {
                foreach (array_map('str_getcsv', file($file)) as $_) {
                    $__[$_[0]] = $_[1];
                }
            }
            
            $this->_locale = array(
                $locale => $__
            );
            
        }
        
        /**
         * Update the master object saving the record into the database.
         * This method is triggered by the class constructor.
         */
        private function _storeMaster($master) {
            
            return \R::store($master);
            
        }
        
        /**
         * No master has been found and it cannot proceed further.
         * The 404 error is sent to the browser.
         * This method is triggered by the class constructor.
         */
        private function _error404() {
            
            header("HTTP/1.0 404 Not Found");
            exit();
            
        }
        
        /**
         * Set the page title, it never changes.
         * This method is triggered by the class constructor.
         */
        private function _setPageTitle() {
            
            $title = $this->getConfig('brandName');
            $title .= " - ";
            $title .= $this->_('invoiceId');
            $title .= ": ";
            $title .= $this->getMaster()->sku;
            $this->_pageTitle = $title;
            
        }
        
        /**
         * Sets the current item as paid.
         * Params <cycle> to 0 and <status> to 0.
         */
        private function _setItemAsPaid() {
            
            $this->_master->setAttr('cycle', self::ITEM_CYCLE_OFF);
            $this->_master->setAttr('status', self::ITEM_STATUS_CLOSED);
            $this->_storeMaster($this->_master);
            exit('The items has been correctly set as <b>PAID</b>.');
            
        }
        
        /**
         * Sets the current item as dead.
         * Params <cycle> to 0 and <status> to -1.
         */
        private function _setItemAsDead() {
            
            $this->_master->setAttr('cycle', self::ITEM_CYCLE_OFF);
            $this->_master->setAttr('status', self::ITEM_STATUS_DEAD);
            $this->_storeMaster($this->_master);
            exit('The items has been correctly set as <b>DEAD</b>.');
            
        }
        
        /**
         * The class construct contains the bootstrap and mandatory behaviors:
         * $this->_config
         * $this->_db()
         * $this->_forceHttps()
         * $this->_setSku()
         * $this->_setAction()
         * $this->_setMaster($this->_sku)
         * $this->_error404()
         * $this->_setRegistry($this->getMaster()->client__id)
         * $this->_setLocale($this->getRegistry()->locale)
         * $this->_setProcessParams()
         * $this->_setPageTitle()
         * $this->processCheckout()
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
             * Force the HTTPS protocol.
             */
            $this->_forceHttps();
            
            /**
             * Set the unique order ID or <sku>.
             */
            $this->_setSku();
            
            /**
             * Set the action.
             */
            $this->_setAction();
            
            /**
             * If the <sku> exists it sets the master object.
             */
            if ($this->_sku) {
                $this->_setMaster($this->_sku);
            }
            
            /**
             * If the master object doesn't exist, it triggers an exception
             * otherwise it sets the registry object.
             */
            if ($this->getMaster() === NULL) {
                $this->_error404();
            } else {
                $this->_setRegistry($this->getMaster()->client__id);
            }
            
            /**
             * If the <action> exists and matches.
             */
            if ($this->_action) {
                 if ($this->_action === $this->getConfig('actionsHash')['setItemAsPaid']) {
                     $this->_setItemAsPaid();
                 } else if ($this->_action === $this->getConfig('actionsHash')['setItemAsDead']) {
                     $this->_setItemAsDead();
                 } else {
                     $this->_error404();
                 }
            }
            
            /**
             * Set the locale and the translations.
             */
            $this->_setLocale($this->getRegistry()->locale);
            
            /**
             * Validate and set the paramters needed
             * by the Stripe checkout.
             */
            $this->_setProcessParams();
            
            /**
             * Set the page title.
             */
            $this->_setPageTitle();
            
            /**
             * The order is closed.
             */
            if ($this->getMaster()->status == self::ITEM_STATUS_CLOSED) {
                echo $this->_('orderClosed');
                exit();
            }
            
            /**
             * If the parameters for the Stripe checkout exists
             * it processes the transaction.
             */
            if ($this->getProcessParams()) {
                $this->processCheckout();
            }
            
        }
        
        /**
         * The class destruct method triggered when the PHP process dies.
         */
        public function __destruct() {
        
            /**
             * Close the database connection.
             */
            \R::close();
            
        }
        
        /**
         * Retrive one config value by the <key> as a parameter.
         */
        public function getConfig(string $key) {
            
            return $this->_config[$key];
            
        }
        
        /**
         * Get the parameters needed by the Stripe checkout.
         */
        public function getProcessParams() {
            
            return $this->_processParams;
            
        }
        
        /**
         * Retrieve the master object.
         */
        public function getMaster() {
            
            return $this->_master;
            
        }
        
        /**
         * Retrieve the registry object.
         */
        public function getRegistry() {
            
            return $this->_registry;
            
        }
        
        /**
         * Retrieve the translation by the key as a parameter.
         * If the translation is not found it arises an exception.
         */
        public function _(string $key, $replace = array()) {
            
            $response = reset($this->_locale);
            
            if (!isset($response[$key])) {
                return $key;    
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
         * Process the checkout executing the money transaction.
         */
        public function processCheckout() {
            
            try {
        
                \Stripe\Stripe::setApiKey($this->getConfig('stripeSk'));
                
                /*
                 * Create the customer on Stripe if it dosn't exist already.
                 */
                $customer = \Stripe\Customer::create(array(
                    'email' => $this->_processParams['email'],
                    'source'  => $this->_processParams['id']
                ));
                
                /**
                 * Charge the customer credit card.
                 */
                \Stripe\Charge::create(array(
                    'customer' => $customer->id,
                    'amount' => $this->_processParams['amount'],
                    'currency' => $this->_processParams['currency'],
                    'description' => $this->_processParams['description']
                ));
                $_payload = array("status" => "ok");
                
            } catch (Exception $e) {
                $_payload = $e->getMessage();
            }
            
            exit(json_encode($_payload));
            
        }
        
        /**
         * Get the page title used by the view.
         */
        public function getPageTitle() {
            
            return $this->_pageTitle;
            
        }
        
        /**
         * Load all the required CSS and JS.
         */
        public function loadAssets() {
            
            $response = '
                <link href="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/css/bootstrap.min.css" rel="stylesheet">
                <link href="assets/main.css" rel="stylesheet">
                <link href="https://fonts.googleapis.com/css?family=Oswald" rel="stylesheet">
                <link href="https://fonts.googleapis.com/css?family=PT+Sans+Caption" rel="stylesheet">
                <script src="https://code.jquery.com/jquery-3.1.1.min.js"></script>
                <script src="https://checkout.stripe.com/checkout.js"></script>
                <script>
                    window.dsc = {
                        sku: "' . $this->getMaster()->sku . '",
                        brandName: "' . $this->getConfig('brandName') . '",
                        stripeCheckoutLogo: "' . $this->getConfig('stripeCheckoutLogo') . '",
                        stripePublicKey: "' . $this->getConfig('stripePk') . '",
                        tokenDescription: "' . $this->getMaster()->subject . '",
                        tokenCurrency: "' . $this->getMaster()->currency . '",
                        tokenAmount: ' . $this->getMaster()->amount_due * 100 . ',
                        ' . ((!empty($this->getConfig('paypalMeLink'))) ?
                            "paypalPaymeBaseUrl: \"" . $this->getConfig('paypalMeLink') .
                            $this->getMaster()->amount_due . "\" " : NULL) . '
                    }
                </script>
                <script src="assets/main.js"></script>
            ';
            return preg_replace(
                array('/<!--(.*)-->/Uis',"/[[:blank:]]+/"),
                array('',' '),
                str_replace(array("\n","\r","\t"),'', $response));
        }
        
    }
    
}

?>