jQuery(document).ready(function($) {
    
    /**
     * This fades out and fading in elements when the page is ready to use.
     */
    $("#loading-spinner").fadeOut("slow", function() {
        $(".pay-button").fadeIn();
    });
    
    /**
     * The pay with payal button event.
     */
    $("#pay-button-paypal").click(function() {
        window.open(window.dsc.paypalPaymeBaseUrl, "_blank");
    });
    
    /**
     * The Stripe checkout handler trigged by the pay button.
     */
    var handler = StripeCheckout.configure({
        key: window.dsc.stripePublicKey,
        image:  window.dsc.stripeCheckoutLogo,
        locale: 'auto',
        token: function(token) {
            
            /**
             * The token definition.
             */
            token.description = window.dsc.tokenDescription;
            token.currency = window.dsc.tokenCurrency;
            token.amount = window.dsc.tokenAmount;
            
            $("#transaction-ko").hide();
            $(".pay-button").fadeOut("fast", function() {
                $("#loading-spinner").fadeIn("fast");
            });
            
            /**
             * It processes the transaction using the ajax.
             */
            $.ajax({
                url: "index.php?sku=" + window.dsc.sku, // The file called is the same.
                type: "post",
                data: token,
                success: function(_payload) {
                    
                    _payload = jQuery.parseJSON(_payload);
                    if (_payload.status === "ok") {
                    
                        /**
                         * The callback.
                         */
                        $("#transaction-ko").hide();
                        $("#loading-spinner").fadeOut("fast", function() {
                            $("#pay-direct-bank-info").fadeOut("fast", function() {
                                $("#transaction-ok").fadeIn("slow");
                            });
                        });
                    
                    } else {
                        
                        /**
                         * The process has failed, showing up a precise message.
                         */
                        $("#loading-spinner").fadeOut("fast", function() {
                            $(".pay-button").fadeIn("fast");
                            $("#transaction-ko").fadeIn("slow");
                        });
                        
                    }
                    
                }
            });
            
        }
        
    });
    
    document.getElementById('pay-button-credit-card').addEventListener('click', function(e) {
        
        /**
         * It sets up the handler for the checkout process.
         */
        handler.open({
            name: window.dsc.brandName,
            description: window.dsc.tokenDescription,
            zipCode: true,
            currency: window.dsc.tokenCurrency,
            amount: window.dsc.tokenAmount
        });
        e.preventDefault();
        
    });
    
    /**
     * It closes the checkout process.
     */
    window.addEventListener('popstate', function() {
        handler.close();
    });
    
});