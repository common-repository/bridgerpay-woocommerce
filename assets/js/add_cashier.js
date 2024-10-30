jQuery(document).ready(function($){
    var current_selected_payment_option = '';
    var cashier_loaded = false;
    
    //payment_method_bridgerpay_gateway
    if (typeof hide_default_place_holder != 'function') {
        function hide_default_place_holder(){

            if($("ul.wc_payment_methods").length >= 1){
                current_selected_payment_option = $('input[name="payment_method"]:checked').val();
            }

            if(current_selected_payment_option && current_selected_payment_option == 'bridgerpay_gateway' && cashier_loaded){
                $("div.place-order").hide();
                $("#place_order").hide();
            }else{
                $("div.place-order").show();
                $("#place_order").show();
            }
            // current_selected_payment_option = $("input[name='payment-option']:checked").attr('id');
            // if(current_selected_payment_option+"-additional-information" == $(".payment_method_bridgerpay_gateway_wrapper").attr("id")){
            //     if ($("#conditions-to-approve input").is(':checked')) {
            //         load_bridgerpay_cashier_iframe();
            //     }
            // }
        }
    }
    hide_default_place_holder();
    $('body').on('change','input[name="payment_method"]', function(e){
        hide_default_place_holder();
    });

    if (typeof add_bridgerpay_cashier_description != 'function') {
        function add_bridgerpay_cashier_description(){
            if(typeof bridgerpay_cashier_description != 'undefined' && bridgerpay_cashier_description[0] != ''){
                $("div.payment_method_bridgerpay_gateway").html(bridgerpay_cashier_description[0]);
            }
        };
    }

    if (typeof add_bridgerpay_cashier_iframe != 'function') {
        function add_bridgerpay_cashier_iframe(){
            
            
            add_bridgerpay_cashier_description();
            if(typeof cashier_has_token != 'undefined' && cashier_has_token == 'yes'){
                var script = document.createElement('script');
                script.src = cashier_url + '/'+version +'/loader';
                $(script).attr('data-cashier-key', data_cashier_key);
                $(script).attr('data-cashier-token', data_cashier_token);
                $(script).attr('data-language', data_lang);
                $(script).attr('data-theme', data_theme);
                $(script).attr('data-deposit-button-text', data_deposit_button_text);
                // $(script).attr('data-pay-mode', data_pay_mode);
                // $(script).attr('data-deposit-button-text', data_deposit_button_text);
                // document.getElementsByClassName('woocommerce')[0].appendChild(script);
                // document.getElementsByClassName('')[0].appendChild(script);
                $("div.payment_method_bridgerpay_gateway").html("");
                $("div.payment_method_bridgerpay_gateway")[0].appendChild(script);
                cashier_loaded = true;
                // console.log(data_pay_mode);  
            }
            hide_default_place_holder();
        };
    }

    
    
    add_bridgerpay_cashier_description();
    $('body').on('updated_checkout', function(){
        add_bridgerpay_cashier_iframe();
    });
    if(typeof bp_checkout_url != 'undefined' && bp_checkout_url == 'order-pay'){
        add_bridgerpay_cashier_iframe();
    }
    

    function bridgerpay_cashier_iframe_adjust_height (){
              
        bridgerpay_cashier_iframe_object = jQuery(".wc_payment_method .payment_box.payment_method_bridgerpay_gateway iframe.bp-cashier-iframe");
        if(bridgerpay_cashier_iframe_object.length >= 1){
            bridgerpay_cashier_iframe_object.each(function(index, ele){
                current_height = jQuery(this).css('height');
                if(typeof jQuery(this).get(0).style != 'undefined' &&  typeof jQuery(this).get(0).style.height != 'undefined'){
                    acutual_height = jQuery(this).get(0).style.height;
                    
                    if(current_height != acutual_height){

                        jQuery(this).attr('style', function(i,s) { return (s || '') + 'height:' +acutual_height+' !important;' });
                    }
                }

            });
        }
        
    }
    
    
    setInterval(function(){
        bridgerpay_cashier_iframe_adjust_height()
    }, 3000);
    
});
