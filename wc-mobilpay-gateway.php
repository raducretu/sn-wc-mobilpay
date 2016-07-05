<?php
class SN_WC_MobilPay extends WC_Payment_Gateway {
	// Setup our Gateway's id, description and other values
	function __construct() {	
		
		$this->id = "sn_wc_mobilpay";
		$this->method_title = __( "MobilPay", 'sn-wc-mobilpay' );
		$this->method_description = __( "MobilPay Payment Gateway Plug-in for WooCommerce", 'sn-wc-mobilpay' );
		$this->title = __( "MobilPay", 'sn-wc-mobilpay' );
		$this->icon = SN_PLUGIN_DIR . 'img/mobilpay.gif';
		$this->has_fields = true;
		$this->notify_url        	= WC()->api_request_url( 'SN_WC_MobilPay' );

		// Supports the default credit card form
		$this->supports = array(
	               'products',
	               'refunds'
	               );//array( 'default_credit_card_form' );
		
		$this->init_form_fields();
		
		$this->init_settings();
		
		// Turn these settings into variables we can use
		foreach ( $this->settings as $setting_key => $value ) {
			$this->$setting_key = $value;
		}
		
		// Lets check for SSL
		add_action( 'admin_notices', array( $this,	'do_ssl_check' ) );
		
		add_action('init', array(&$this, 'check_mobilpay_response'));
		//update for woocommerce >2.0
		add_action( 'woocommerce_api_' . strtolower( get_class( $this ) ), array( $this, 'check_mobilpay_response' ) );

		// Save settings
		if ( is_admin() ) {
			// Versions over 2.0
			// Save our administration options. Since we are not going to be doing anything special
			// we have not defined 'process_admin_options' in this class so the method in the parent
			// class will be used instead
			add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
			//add_action( 'wp_enqueue_scripts',                                       array( $this, 'add_mobilpay_scripts' ) );
		}	

		add_action('woocommerce_receipt_sn_wc_mobilpay', array(&$this, 'receipt_page'));
	} // End __construct()	

	// Build the administration fields for this specific Gateway
	public function init_form_fields() {
		$this->form_fields = array(			
			'enabled' => array(
				'title'		=> __( 'Enable / Disable', 'sn-wc-mobilpay' ),
				'label'		=> __( 'Enable this payment gateway', 'sn-wc-mobilpay' ),
				'type'		=> 'checkbox',
				'default'	=> 'no',
			),
			'environment' => array(
				'title'		=> __( 'MobilPay Test Mode', 'sn-wc-mobilpay' ),
				'label'		=> __( 'Enable Test Mode', 'sn-wc-mobilpay' ),
				'type'		=> 'checkbox',
				'description' => __( 'Place the payment gateway in test mode.', 'sn-wc-mobilpay' ),
				'default'	=> 'no',
			),
			'title' => array(
				'title'		=> __( 'Title', 'sn-wc-mobilpay' ),
				'type'		=> 'text',
				'desc_tip'	=> __( 'Payment title the customer will see during the checkout process.', 'sn-wc-mobilpay' ),
				'default'	=> __( 'MobilPay', 'sn-wc-mobilpay' ),
			),
			'description' => array(
				'title'		=> __( 'Description', 'sn-wc-mobilpay' ),
				'type'		=> 'textarea',
				'desc_tip'	=> __( 'Payment description the customer will see during the checkout process.', 'sn-wc-mobilpay' ),				
				'css'		=> 'max-width:350px;'
			),
			'account_id' => array(
				'title'		=> __( 'Seller Account ID', 'sn-wc-mobilpay' ),
				'type'		=> 'text',
				'desc_tip'	=> __( 'This is Account ID provided by MobilPay when you signed up for an account. Unique key for your seller account for the payment process.', 'sn-wc-mobilpay' ),
				'description' => __( 'Login to MobilPay and go to Admin-> Conturi de comerciant->Modifica (iconita creionas)->tab-ul Setari securitate', 'sn-wc-mobilpay' ),
			),
			'public_key' => array(
				'title'		=> __( 'Public certificate file', 'sn-wc-mobilpay' ),
				'type'		=> 'text',
				'desc_tip'	=> __( 'This is the Public Key provided by MobilPay when you signed up for an account. Public key for secure connection with mobilPay', 'sn-wc-mobilpay' ),
				'description' => __( 'Login to MobilPay and go to Admin-> Conturi de comerciant->Modifica (iconita creionas)->tab-ul Setari securitate to download the Public certificate file form MobilPay, then upload to somewhere in this site and enter the path to this file in here. i.e: /home/certificates/public.cer', 'sn-wc-mobilpay' ),				
			),
			'private_key' => array(
				'title'		=> __( 'Private key file', 'sn-wc-mobilpay' ),
				'type'		=> 'text',
				'desc_tip'	=> __( 'This is the Private Key provided by MobilPay when you signed up for an account. Public key for secure connection with mobilPay', 'sn-wc-mobilpay' ),
				'description' => __( 'Login to MobilPay and go to Admin-> Conturi de comerciant->Modifica (iconita creionas)->tab-ul Setari securitate to download the Private key file form MobilPay, then upload to somewhere in this site and enter the path to this file in here. i.e: /home/certificates/private.key', 'sn-wc-mobilpay' ),				
			),	
			'payment_methods'   => array(
		        'title'       => __( 'Payment methods', 'sn-wc-mobilpay' ),
		        'type'        => 'multiselect',
		        'description' => __( 'Select which payment methods to accept.', 'sn-wc-mobilpay' ),
		        'default'     => '',
		        'options'     => array(
		          'credit_card'	      => __( 'Credit Card', 'sn-wc-mobilpay' ),
		          'sms'			        => __('SMS' , 'sn-wc-mobilpay' ),
		          'bank_transfer'		      => __( 'Bank Transfer', 'sn-wc-mobilpay' ),
		          'bitcoin'  => __( 'Bitcoin', 'sn-wc-mobilpay' )
		          ),
		    ),
		    'return_url' => array(
				'title'		=> __( 'Return URL', 'sn-wc-mobilpay' ),
				'type'		=> 'text',
				'desc_tip'	=> __( 'The URL of a page within your Wordpress site that the user will see after finishing the payment on the Mobilpay servers. This is required in order to pass the Mobilpay validation.', 'sn-wc-mobilpay' ),
				'description' => __( 'You must create a new page and in the content field enter the shortcode [snwcstatus] so that the user can see the message that is returned by the Mobilpay server regarding their transaction. Or any content you want to thank you for buy', 'sn-wc-mobilpay' ),
			),		
			'sms_setting' => array(
				'title'       => __( 'For SMS Payment', 'sn-wc-mobilpay' ),
				'type'        => 'title',
				'description' => '',
			),	
			'service_id' => array(
				'title'		=> __( 'Product/service code: ', 'sn-wc-mobilpay' ),
				'type'		=> 'text',
				'desc_tip'	=> __( 'This is Service Code provided by MobilPay when you signed up for an account.', 'sn-wc-mobilpay' ),
				'description' => __( 'Login to MobilPay and go to Admin -> Conturi de comerciant -> Produse si servicii -> Semnul plus', 'sn-wc-mobilpay' ),
			),	
		);		
	}

	function payment_fields() {
		$user = wp_get_current_user();
      	// Description of payment method from settings
      	if ( $this->description ) { ?>
        	<p><?php echo $this->description; ?></p>
  		<?php }
  		if ( $this->payment_methods ) {  
  			$payment_methods = $this->payment_methods;	
  		}else{
  			$payment_methods = array('credit_card');
  		}
  		//echo "<pre>payment_methods: "; print_r($payment_methods); echo "</pre>";
  		$name_methods = array(
		          'credit_card'	      => __( 'Credit Card', 'sn-wc-mobilpay' ),
		          'sms'			        => __('SMS' , 'sn-wc-mobilpay' ),
		          'bank_transfer'		      => __( 'Bank Transfer', 'sn-wc-mobilpay' ),
		          'bitcoin'  => __( 'Bitcoin', 'sn-wc-mobilpay' )
		          );
  		?>
  		<div id="sn-mobilpay-methods">
	  		<ul>
	  		<?php  foreach ($payment_methods as $method) { ?>
	  			<?php 
	  			$checked ='';
	  			if($method == 'credit_card') $checked = 'checked="checked"';
	  			?>
	  				<!-- <p class="form-row form-row-first"> --><!-- onclick="document.getElementById('inspire-new-info').style.display='none'; document.getElementById('inspire-stored-info').style.display='block'" -->
	  				<li>
	  					<input type="radio" name="sn_mobilpay_method_pay" class="sn-mobilpay-method-pay" id="sn-mobilpay-method-<?=$method?>" value="<?=$method?>" <?php echo $checked; ?> /><label for="inspire-use-stored-payment-info-yes" style="display: inline;"><?php echo $name_methods[$method] ?></label>
	  				</li>
	  				<!-- </p> -->  			
	  		<?php } ?>
	  		</ul>
	  		<!-- <div class="billing-shipping" style="display:none;">
				<fieldset>
					<legend>Billing Information</legend>
					<p class="form-row form-row-first">
						<label>Buyer type: </label><select name="billing_type" style="width: 200px;">
							<option value="person" selected>Personal</option>
							<option value="company">Company</option>
						</select>
					</p>
					 <p class="form-row form-row-first">
						<label id="labelFirstName">Firstname:</label><input type="text" name="billing_first_name" id="idFirstName" style="width: 200px;"/>
					</p>
					<p class="form-row form-row-first">
						<label id="labelLastName">Name:</label><input type="text" name="billing_last_name" id="idLastName" style="width: 200px;"/>
					</p>
					<p class="form-row form-row-first">
						<label id="labelFiscalNumber">CNP:</label><input type="text" name="billing_fiscal_number" id="idFiscalNumber" style="width: 200px;"/>
					</p>
					<p class="form-row form-row-first">
						<label id="labelIdentityNumber">CI:</label><input type="text" name="billing_identity_number" id="idIdentityNumber" style="width: 200px;"/>
					</p>

					<p class="form-row form-row-first"><label>Country:</label><input type="text" name="billing_country" style="width: 200px;"/></p>
					<p class="form-row form-row-first"><label>State/Province:</label><input type="text" name="billing_county" style="width: 200px;"/></p>
					<p class="form-row form-row-first"><label>Location:</label><input type="text" name="billing_city" style="width: 200px;"/></p>
					<p class="form-row form-row-first"><label>Postal code:</label><input type="text" name="billing_zip_code" style="width: 200px;"/></p>
					<p class="form-row form-row-first"><label>Address:</label><textarea type="text" name="billing_address" style="width: 200px; height: 150px;"></textarea></p>
					<p class="form-row form-row-first"><label>E-mail:</label><input type="text" name="billing_email" style="width: 200px;"/></p>
					<p class="form-row form-row-first"><label>Mobiphone:</label><input type="text" name="billing_mobile_phone" style="width: 200px;"/></p>
					<p class="form-row form-row-first"><label>Bank:</label><input type="text" name="billing_bank" style="width: 200px;"/></p>
					<p class="form-row form-row-first"><label>IBAN:</label><input type="text" name="billing_iban" style="width: 200px;"/></p> 
				</fieldset>
				<fieldset>
					<legend>Shippng Infomartion</legend>
					<p class="form-row form-row-first">
						<label>Buyer type:</label><select name="shipping_type" style="width: 200px;">
							<option value="person" selected>Personal</option>
							<option value="company">Company</option>
						</select>
					</p>
					<p class="form-row form-row-first"><label id="labelFirstName">Firstname:</label><input type="text" name="shipping_first_name" id="idFirstName" style="width: 200px;"/></p>
					<p class="form-row form-row-first"><label id="labelLastName">Name:</label><input type="text" name="shipping_last_name" id="idLastName" style="width: 200px;"/></p>
					<p class="form-row form-row-first"><label id="labelFiscalNumber">CNP:</label><input type="text" name="shipping_fiscal_number" id="idFiscalNumber" style="width: 200px;"/></p>
					<p class="form-row form-row-first"><label id="labelIdentityNumber">CI:</label><input type="text" name="shipping_identity_number" id="idIdentityNumber" style="width: 200px;"/></p>
					<p class="form-row form-row-first"><label>Country:</label><input type="text" name="shipping_country" style="width: 200px;"/></p>
					<p class="form-row form-row-first"><label>State/Province:</label><input type="text" name="shipping_county" style="width: 200px;"/></p>
					<p class="form-row form-row-first"><label>Location:</label><input type="text" name="shipping_city" style="width: 200px;"/></p>
					<p class="form-row form-row-first"><label>Postal code:</label><input type="text" name="shipping_zip_code" style="width: 200px;"/></p>
					<p class="form-row form-row-first"><label>Address:</label><textarea type="text" name="shipping_address" style="width: 200px; height: 150px;"></textarea></p>
					<p class="form-row form-row-first"><label>E-mail:</label><input type="text" name="shipping_email" style="width: 200px;"/></p>
					<p class="form-row form-row-first"><label>Mobiphone:</label><input type="text" name="shipping_mobile_phone" style="width: 200px;"/></p>
					<p class="form-row form-row-first"><label>Bank:</label><input type="text" name="shipping_bank" style="width: 200px;"/></p>
					<p class="form-row form-row-first"><label>IBAN:</label><input type="text" name="shipping_iban" style="width: 200px;"/></p> 
				</fieldset>
			</div> -->
  		</div>

  		<style type="text/css">
  			#sn-mobilpay-methods{display: inline-block;}
  			#sn-mobilpay-methods ul{margin: 0;}
  			#sn-mobilpay-methods ul li{list-style-type: none;}
		</style>
		<script type="text/javascript">
			jQuery(document).ready(function($){				
				var method_ = $('input[name=sn_mobilpay_method_pay]:checked').val();
				if(method_!='sms'){
					$('.billing-shipping').show('slow');
				}else{
					$('.billing-shipping').hide('slow');
				}

				//console.log('method_: ',method_);
				$('.sn-mobilpay-method-pay').click(function(){
					var method = $(this).val();
					//console.log('method: ',method);
					if(method!='sms'){
						$('.billing-shipping').show('slow');
					}else{
						$('.billing-shipping').hide('slow');
					}					
				});
			});
		</script>
  		<?php
  	}

  	// Submit payment
	public function process_payment( $order_id ) {
		global $woocommerce;		
		$order = new WC_Order( $order_id );			
			if ( version_compare( WOOCOMMERCE_VERSION, '2.1.0', '>=' ) ) {
				/* 2.1.0 */
				$checkout_payment_url = $order->get_checkout_payment_url( true );
			} else {
				/* 2.0.0 */
				$checkout_payment_url = get_permalink( get_option ( 'woocommerce_pay_page_id' ) );
			}

			$method = $this->get_post( 'sn_mobilpay_method_pay' );
			return array(
				'result' => 'success', 
				'redirect' => add_query_arg(
					'method', 
					$method, 
					add_query_arg(
						'key', 
						$order->order_key, 
						$checkout_payment_url						
					)
				)
        	);
    }

	// Validate fields
	public function validate_fields() {
		$method_pay            = $this->get_post( 'sn_mobilpay_method_pay' );
		// Check card number
		if ( empty( $method_pay ) ) {
			wc_add_notice( __( 'Please choosen a method payment.', 'sn-wc-mobilpay' ), $notice_type = 'error' );
			return false;
		}
		return true;
	}

  	/**
	* Receipt Page
	**/
	function receipt_page($order){
		$customer_order = new WC_Order( $order );
		$order_amount = sprintf('%.2f',$customer_order->order_total);
		$amount_RON = sprintf('%.2f',$this->convertAmountToRON($customer_order->order_total, $customer_order->get_order_currency()));
		echo '<p>'.__('Thank you for your order, please click the button below to pay with MobilPay.', 'sn-wc-mobilpay').'</p>';
		echo '<p><strong>'.__('Total', 'sn-wc-mobilpay').": ".$amount_RON.' RON ('.$customer_order->order_total.' '.$customer_order->get_order_currency().')</strong></p>';
		echo $this->generate_mobilpay_form($order);
	}

	/**
	* Generate payu button link
	**/
	function generate_mobilpay_form($order_id){
		global $woocommerce;
		// Get this Order's information so that we know
		// who to charge and how much
		$customer_order = new WC_Order( $order_id );
		$user = new WP_User( $customer_order->user_id );
		
		// Are we testing right now or is it a real transaction
		//$environment = ( $this->environment == 'yes' ) ? 'TRUE' : 'FALSE';

		// Decide which URL to post to
		$paymentUrl = ( $this->environment == 'yes' ) 
						   ? 'http://sandboxsecure.mobilpay.ro/'
						   : 'https://secure.mobilpay.ro/';
		$x509FilePath 	= trim($this->public_key);

		require_once 'Mobilpay/Payment/Request/Abstract.php';		
		require_once 'Mobilpay/Payment/Invoice.php';
		require_once 'Mobilpay/Payment/Address.php';

		$method = $this->get_post( 'method' );
		$name_methods = array(
		          'credit_card'	      => __( 'Credit Card', 'sn-wc-mobilpay' ),
		          'sms'			        => __('SMS' , 'sn-wc-mobilpay' ),
		          'bank_transfer'		      => __( 'Bank Transfer', 'sn-wc-mobilpay' ),
		          'bitcoin'  => __( 'Bitcoin', 'sn-wc-mobilpay' )
		          );
		switch ($method) {
			case 'sms':		
				require_once 'Mobilpay/Payment/Request/Sms.php';
				$objPmReq = new Mobilpay_Payment_Request_Sms();	
				$objPmReq->service 		= $this->service_id;	
				break;
			case 'bank_transfer':
				require_once 'Mobilpay/Payment/Request/Transfer.php';
				$objPmReq = new Mobilpay_Payment_Request_Transfer();	
				$paymentUrl .= '/transfer';
				break;
			case 'bitcoin':	
				require_once 'Mobilpay/Payment/Request/Bitcoin.php';
				$objPmReq = new Mobilpay_Payment_Request_Bitcoin();			
				$paymentUrl = 'https://secure.mobilpay.ro/bitcoin'; //for both sanbox and live
				break;
			default: // credit_card
				require_once 'Mobilpay/Payment/Request/Card.php';
				$objPmReq = new Mobilpay_Payment_Request_Card();
				break;
		}
		$amount_RON = $this->convertAmountToRON($customer_order->order_total, $customer_order->get_order_currency());

		srand((double) microtime() * 1000000);
		$objPmReq->signature 			= $this->account_id;
		$objPmReq->orderId 				= md5(uniqid(rand()));//$order_id;//$customer_order->get_order_number();
		$objPmReq->confirmUrl 			= $this->notify_url;
		$objPmReq->returnUrl 			= trim($this->return_url).'?order_id='.$order_id;
		//$objPmReq->cancelUrl 			= trim($this->return_url).'?test='.$order_id;
		if($method != 'sms'){
			$objPmReq->invoice = new Mobilpay_Payment_Invoice();
			$objPmReq->invoice->currency	= 'RON';//$customer_order->get_order_currency();//;get_woocommerce_currency();
			$objPmReq->invoice->amount		= sprintf('%.2f',$amount_RON);//sprintf('%.2f',$customer_order->order_total);
			$objPmReq->invoice->details		= 'Payment for order ID: '.$order_id.' with '.$name_methods[$method];

			$billingAddress 				= new Mobilpay_Payment_Address();
			$billingAddress->type			= 'person';//$_POST['billing_type'];
			$billingAddress->firstName		= $customer_order->billing_first_name;
			$billingAddress->lastName		= $customer_order->billing_last_name;
			//$billingAddress->fiscalNumber	= $_POST['billing_fiscal_number'];
			//$billingAddress->identityNumber	= $_POST['billing_identity_number'];
			$billingAddress->country		= $customer_order->billing_country;	
			$billingAddress->county			= $customer_order->billing_state;		
			$billingAddress->city			= $customer_order->billing_city;
			$billingAddress->zipCode		= $customer_order->billing_postcode;
			$billingAddress->address		= $customer_order->billing_address_1;
			$billingAddress->email			= $customer_order->billing_email;
			$billingAddress->mobilePhone	= $customer_order->billing_phone;
			//$billingAddress->bank			= $_POST['billing_bank'];
			//$billingAddress->iban			= $_POST['billing_iban'];
			$objPmReq->invoice->setBillingAddress($billingAddress);

			$shippingAddress 				= new Mobilpay_Payment_Address();
			$shippingAddress->type			= 'person';//$_POST['shipping_type'];
			$shippingAddress->firstName		= $customer_order->shipping_first_name;
			$shippingAddress->lastName		= $customer_order->shipping_last_name;
			//$shippingAddress->fiscalNumber	= $_POST['shipping_fiscal_number'];
			//$shippingAddress->identityNumber= $_POST['shipping_identity_number'];
			$shippingAddress->country		= $customer_order->shipping_country;	
			$shippingAddress->county		= $customer_order->shipping_state;		
			$shippingAddress->city			= $customer_order->shipping_city;
			$shippingAddress->zipCode		= $customer_order->shipping_postcode;
			$shippingAddress->address		= $customer_order->shipping_address_1;
			$shippingAddress->email			= $customer_order->billing_email;//$_POST['shipping_email'];
			$shippingAddress->mobilePhone	= $customer_order->billing_phone;//$_POST['shipping_mobile_phone'];
			//$shippingAddress->bank			= $_POST['shipping_bank'];
			//$shippingAddress->iban			= $_POST['shipping_iban'];
			$objPmReq->invoice->setShippingAddress($shippingAddress);
		}		
		
		$objPmReq->params = array('order_id'=>$order_id,'customer_id'=>$customer_order->user_id,'customer_ip'=>$_SERVER['REMOTE_ADDR'],'method'=>$method);		
		$objPmReq->encrypt($x509FilePath);
		//echo "<pre>objPmReq: "; print_r($objPmReq); echo "</pre>";
		return '	<form action="'.$paymentUrl.'" method="post" id="frmPaymentRedirect">
				<input type="hidden" name="env_key" value="'.$objPmReq->getEnvKey().'"/>
				<input type="hidden" name="data" value="'.$objPmReq->getEncData().'"/>				
				<input type="submit" class="button-alt" id="submit_mobilpay_payment_form" value="'.__('Pay via MobilPay', 'sn-wc-mobilpay').'" /> <a class="button cancel" href="'.$customer_order->get_cancel_order_url().'">'.__('Cancel order &amp; restore cart', 'sn-wc-mobilpay').'</a>
				<script type="text/javascript">
				jQuery(function(){
				jQuery("body").block({
					message: "'.__('Thank you for your order. We are now redirecting you to Payment Gateway to make payment.', 'sn-wc-mobilpay').'",
					overlayCSS: {
						background		: "#fff",
						opacity			: 0.6
					},
					css: {
						padding			: 20,
						textAlign		: "center",
						color			: "#555",
						border			: "3px solid #aaa",
						backgroundColor	: "#fff",
						cursor			: "wait",
						lineHeight		: "32px"
					}
				});
				//jQuery("#submit_mobilpay_payment_form").click();});
				</script>
			</form>';
	}	

	/**
	* Check for valid MobilPay server callback
	**/
	function check_mobilpay_response(){
		$this->bpLog('response ========================================');
		$this->bpLog($_POST);
		global $woocommerce;

		require_once 'Mobilpay/Payment/Request/Abstract.php';

		require_once 'Mobilpay/Payment/Request/Card.php';
		require_once 'Mobilpay/Payment/Request/Sms.php';
		require_once 'Mobilpay/Payment/Request/Transfer.php';
		require_once 'Mobilpay/Payment/Request/Bitcoin.php';

		require_once 'Mobilpay/Payment/Request/Notify.php';
		require_once 'Mobilpay/Payment/Invoice.php';
		require_once 'Mobilpay/Payment/Address.php';

		$errorCode 		= 0;
		$errorType		= Mobilpay_Payment_Request_Abstract::CONFIRM_ERROR_TYPE_NONE;
		$errorMessage	= '';
		
		$msg_errors = array('16'=>'card has a risk (i.e. stolen card)', '17'=>'card number is incorrect', '18'=>'closed card', '19'=>'card is expired', '20'=>'insufficient funds', '21'=>'cVV2 code incorrect', '22'=>'issuer is unavailable', '32'=>'amount is incorrect', '33'=>'currency is incorrect', '34'=>'transaction not permitted to cardholder', '35'=>'transaction declined', '36'=>'transaction rejected by antifraud filters', '37'=>'transaction declined (breaking the law)', '38'=>'transaction declined', '48'=>'invalid request', '49'=>'duplicate PREAUTH', '50'=>'duplicate AUTH', '51'=>'you can only CANCEL a preauth order', '52'=>'you can only CONFIRM a preauth order', '53'=>'you can only CREDIT a confirmed order', '54'=>'credit amount is higher than auth amount', '55'=>'capture amount is higher than preauth amount', '56'=>'duplicate request', '99'=>'generic error');
		$privateKeyFilePath =trim($this->private_key);		

		if (strcasecmp($_SERVER['REQUEST_METHOD'], 'post') == 0){
			if(isset($_POST['env_key']) && isset($_POST['data'])){
				try
				{
					$objPmReq = Mobilpay_Payment_Request_Abstract::factoryFromEncrypted($_POST['env_key'], $_POST['data'], $privateKeyFilePath);
					$action = $objPmReq->objPmNotify->action;

					$this->bpLog('THParams :     ');
					$this->bpLog($objPmReq->params);
					$this->bpLog('Notify :     ');
					$this->bpLog($objPmReq->objPmNotify);

					$params = $objPmReq->params;
					$order = new WC_Order( $params['order_id'] );
					$user = new WP_User( $params['customer_id'] );
					$transaction_id = $objPmReq->objPmNotify->purchaseId;
					if($objPmReq->objPmNotify->errorCode==0){
						switch($action)
			    		{
			    			case 'confirmed':
								#cand action este confirmed avem certitudinea ca banii au plecat din contul posesorului de card si facem update al starii comenzii si livrarea produsului
								//update DB, SET status = "confirmed/captured"
								$errorMessage = $objPmReq->objPmNotify->errorMessage;
								
								$amountorder_RON = $this->convertAmountToRON($order->order_total, $order->get_order_currency());
								$amount_paid = is_null($objPmReq->objPmNotify->originalAmount) ? 0:$objPmReq->objPmNotify->originalAmount;
								//$objPmReq->objPmNotify->originalAmount
								//original_amount – the original amount processed;
								//processed_amount – the processed amount at the moment of the response. It can be lower than the original amount, ie for capturing a smaller amount or for a partial credit
								if( $amount_paid < $amountorder_RON ) {
					                //Update the order status
									$order->update_status('on-hold', '');

									//Error Note
									$message = 'Thank you for shopping with us.<br />Your payment transaction was successful, but the amount paid is not the same as the total order amount.<br />Your order is currently on-hold.<br />Kindly contact us for more information regarding your order and payment status.';
									$message_type = 'notice';

									//Add Customer Order Note
				                    $order->add_order_note($message.'<br />MobilPay Transaction ID: '.$transaction_id, 1);

				                    //Add Admin Order Note
				                    $order->add_order_note('Look into this order. <br />This order is currently on hold.<br />Reason: Amount paid is less than the total order amount.<br />Amount Paid was &#8358; '.$amount_paid.' RON while the total order amount is &#8358; '.$amountorder_RON.' RON<br />MobilPay Transaction ID: '.$transaction_id);

									// Reduce stock levels
									$order->reduce_order_stock();

									// Empty cart
									wc_empty_cart();
								}
								else {
									if( $order->status == 'processing' ) {
					                    $order->add_order_note('Payment Via MobilPay<br />Transaction ID: '.$transaction_id);

					                    //Add customer order note
					 					$order->add_order_note('Payment Received.<br />Your order is currently being processed.<br />We will be shipping your order to you soon.<br />MobilPay Transaction ID: '.$transaction_id, 1);

										// Reduce stock levels
										$order->reduce_order_stock();

										// Empty cart
										wc_empty_cart();

										//$message = 'Thank you for shopping with us.<br />Your transaction was successful, payment was received.<br />Your order is currently being processed.';
										//$message_type = 'success';
					                }
					                else {

					                	if( $order->has_downloadable_item() ) {

					                		//Update order status
											$order->update_status( 'completed', 'Payment received, your order is now complete.' );

						                    //Add admin order note
						                    $order->add_order_note('Payment Via MobilPay Payment Gateway<br />Transaction ID: '.$transaction_id);

						                    //Add customer order note
						 					$order->add_order_note('Payment Received.<br />Your order is now complete.<br />MobilPay Transaction ID: '.$transaction_id, 1);

											//$message = 'Thank you for shopping with us.<br />Your transaction was successful, payment was received.<br />Your order is now complete.';
											//$message_type = 'success';

					                	}
					                	else {

					                		//Update order status
											$order->update_status( 'processing', 'Payment received, your order is currently being processed.' );

											//Add admin order noote
						                    $order->add_order_note('Payment Via MobilPay Payment Gateway<br />Transaction ID: '.$transaction_id);

						                    //Add customer order note
						 					$order->add_order_note('Payment Received.<br />Your order is currently being processed.<br />We will be shipping your order to you soon.<br />MobilPay Transaction ID: '.$transaction_id, 1);

											$message = 'Thank you for shopping with us.<br />Your transaction was successful, payment was received.<br />Your order is currently being processed.';
											$message_type = 'success';
					                	}

										// Reduce stock levels
										$order->reduce_order_stock();

										// Empty cart
										wc_empty_cart();
					                }
					            }
						    	break;
						    case 'canceled':
								#cand action este canceled inseamna ca tranzactia este anulata. Nu facem livrare/expediere.
								//update DB, SET status = "canceled"
								$errorMessage = $objPmReq->objPmNotify->errorMessage;							

								$message = 	'Thank you for shopping with us. <br />However, the transaction wasn\'t successful, payment wasn\'t received.';
								//Add Customer Order Note
			                   	$order->add_order_note($message.'<br />MobilPay Transaction ID: '.$transaction_id, 1);

			                    //Add Admin Order Note
			                  	$order->add_order_note($message.'<br />MobilPay Transaction ID: '.$transaction_id);

				                //Update the order status
								$order->update_status('cancelled', '');
							    break;
							case 'credit':
								#cand action este credit inseamna ca banii sunt returnati posesorului de card. Daca s-a facut deja livrare, aceasta trebuie oprita sau facut un reverse. 
								//update DB, SET status = "refunded"
								$errorMessage = $objPmReq->objPmNotify->errorMessage;
								$message = 	'Thank you for shopping with us. <br />However, the transaction wasn\'t successful, payment wasn\'t received.';
								//Add Customer Order Note
			                   	$order->add_order_note($message.'<br />MobilPay Transaction ID: '.$transaction_id, 1);

			                    //Add Admin Order Note
			                  	$order->add_order_note($message.'<br />MobilPay Transaction ID: '.$transaction_id);

				                //Update the order status
								$order->update_status('refunded', '');
							    break;	
			    		}
					}else{
						$order->update_status('failed', '');

						//Error Note
						$message = $objPmReq->objPmNotify->errorMessage;
						if(empty($message) && isset($msg_errors[$objPmReq->objPmNotify->errorCode])) $message = $msg_errors[$objPmReq->objPmNotify->errorCode];
						$message_type = 'error';
						//Add Customer Order Note
	                    $order->add_order_note($message.'<br />MobilPay Transaction ID: '.$transaction_id, 1);
					}					
				}catch(Exception $e)
				{
					$errorType 		= Mobilpay_Payment_Request_Abstract::CONFIRM_ERROR_TYPE_TEMPORARY;
					$errorCode		= $e->getCode();
					$errorMessage 	= $e->getMessage();
				}
			}else
			{
				$errorType 		= Mobilpay_Payment_Request_Abstract::CONFIRM_ERROR_TYPE_PERMANENT;
				$errorCode		= Mobilpay_Payment_Request_Abstract::ERROR_CONFIRM_INVALID_POST_PARAMETERS;
				$errorMessage 	= 'mobilpay.ro posted invalid parameters';
			}
		}else 
		{
			$errorType 		= Mobilpay_Payment_Request_Abstract::CONFIRM_ERROR_TYPE_PERMANENT;
			$errorCode		= Mobilpay_Payment_Request_Abstract::ERROR_CONFIRM_INVALID_POST_METHOD;
			$errorMessage 	= 'invalid request method for payment confirmation';
		}
		$this->bpLog('errorType: '.$errorType.' -- errorCode: '.$errorCode.' -- errorMessage: '.$errorMessage);
		header('Content-type: application/xml');
		echo "<?xml version=\"1.0\" encoding=\"utf-8\"?>\n";
		if($errorCode == 0)
		{
			echo "<crc>{$errorMessage}</crc>";
		}
		else
		{
			echo "<crc error_type=\"{$errorType}\" error_code=\"{$errorCode}\">{$errorMessage}</crc>";
			
		}	
		wc_empty_cart();
		die();
	}


	// Check if we are forcing SSL on checkout pages
	// Custom function not required by the Gateway
	public function do_ssl_check() {
		if( $this->enabled == "yes" ) {
			if( get_option( 'woocommerce_force_ssl_checkout' ) == "no" ) {
				echo "<div class=\"error\"><p>". sprintf( __( "<strong>%s</strong> is enabled and WooCommerce is not forcing the SSL certificate on your checkout page. Please ensure that you have a valid SSL certificate and that you are <a href=\"%s\">forcing the checkout pages to be secured.</a>" ), $this->method_title, admin_url( 'admin.php?page=wc-settings&tab=checkout' ) ) ."</p></div>";	
			}
		}		
	}

	/**
	 * Get post data if set
	 */
	private function get_post( $name ) {
		if ( isset( $_REQUEST[ $name ] ) ) {
			return $_REQUEST[ $name ];
		}
		return null;
	}

	public function convertAmountToRON($amount, $currency){		
		$rates = array();
		if(strtoupper($currency) == 'RON'){
			$new_amount = $amount;
		}
		else{
			$new_amount = null;
			if($feed = @file_get_contents('http://api.fixer.io/latest?base=RON')){
				$rates=json_decode($feed, true);
				$rates_RON = $rates['rates'];
				//echo "<pre>currency: "; print_r($currency); echo "</pre>"; 
				//echo "<pre>Rates: "; print_r($rates_RON); echo "</pre>"; 
				if(isset($rates_RON[$currency])){
					$rate_RON_cur = $rates_RON[$currency];
					$new_amount = $amount/$rate_RON_cur;
				}
			}	
		}
		
		return $new_amount;
	}

	public function bpLog($contents,$file=false){
		if(!$file)	$file = dirname(__FILE__).'/bplog.txt';
		file_put_contents($file, date('m-d H:i:s').": ", FILE_APPEND);
		
		if (is_array($contents))
			$contents = var_export($contents, true);	
		else if (is_object($contents))
			$contents = json_encode($contents);
			
		file_put_contents($file, $contents."\n", FILE_APPEND);			
	}

}