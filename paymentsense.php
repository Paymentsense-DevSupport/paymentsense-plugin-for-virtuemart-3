<?php
/**
 * Paymentsense Plugin for VirtueMart 3
 * Version: 2.6.0
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; either version 2
 * of the License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * @version     2.6.0
 * @author      Paymentsense
 * @copyright   2019 Paymentsense Ltd.
 * @license     https://www.gnu.org/licenses/gpl-2.0.html
 */

defined('_JEXEC') or die('Restricted access');

if (!class_exists('vmPSPlugin'))
{
	require_once JPATH_VM_PLUGINS . DS . 'vmpsplugin.php';
}

class plgVmPaymentPaymentsense extends vmPSPlugin
{
	public function __construct(&$subject, $config)
	{
		parent::__construct($subject, $config);
		$this->_loggable = true;
		$this->tableFields = array_keys($this->getTableSQLFields());
		$this->_tablepkey = 'id';
		$this->_tableId = 'id';
		$varsToPush = $this->getVarsToPush();
		$this->setConfigParameterable($this->_configTableFieldName, $varsToPush);
	}

	/**
	 * Creates the table for this plugin if it does not yet exist.
	 * @return string
	 * @since N/A
	 */
	public function getVmPluginCreateTableSQL()
	{
		return $this->createTableSQL('Payment Standard Table');
	}

	/**
	 * Gets the fields to create the payment table.
	 * @return array
	 * @since N/A
	 */
	public function getTableSQLFields()
	{
		$SQLfields = array(
			'id'                          => 'int(1) UNSIGNED NOT NULL AUTO_INCREMENT',
			'virtuemart_order_id'         => 'int(1) UNSIGNED',
			'order_number'                => 'char(64)',
			'virtuemart_paymentmethod_id' => 'mediumint(1) UNSIGNED',
			'payment_name'                => 'varchar(5000)',
			'payment_order_total'         => 'decimal(15,5) NOT NULL DEFAULT \'0.00000\'',
			'payment_currency'            => 'char(3)',
			'email_currency'              => 'char(3)',
			'cost_per_transaction'        => 'decimal(10,2)',
			'cost_percent_total'          => 'decimal(10,2)',
			'tax_id'                      => 'smallint(1)'
		);

		return $SQLfields;
	}

	/**
	 * Prepares and submits the output containing the redirect to the
	 * Hosted Payment Form to the processConfirmedOrderPaymentResponse
	 * function
	 *
	 * @param object $cart  The cart
	 * @param array  $order The order
	 * @return null|bool
	 * @since N/A
	 */
	public function plgVmConfirmedOrder($cart, $order)
	{
		if (!($method = $this->getVmPluginMethod($order['details']['BT']->virtuemart_paymentmethod_id)))
		{
			return null; // Another method was selected, do nothing
		}

		if (!$this->selectedThisElement($method->payment_element))
		{
			return false;
		}

		$lang = JFactory::getLanguage();
		$filename = 'com_virtuemart';
		$lang->load($filename, JPATH_ADMINISTRATOR);

		$this->_debug = true;

		$q = 'SELECT `currency_numeric_code` FROM `#__virtuemart_currencies` WHERE `virtuemart_currency_id` = "' . $order['details']['BT']->order_currency . '" ';
		$db = &JFactory::getDBO();
		$db->setQuery($q);
		$currency_numeric_code = $db->loadResult();

		$countrycode = ShopFunctions::getCountryByID($order['details']['BT']->virtuemart_country_id, 'country_2_code');

		$iso_codes = array(
			'AF'=>'004',
			'AL'=>'008',
			'DZ'=>'012',
			'AS'=>'016',
			'AD'=>'020',
			'AO'=>'024',
			'AI'=>'660',
			'AQ'=>'010',
			'AG'=>'028',
			'AR'=>'032',
			'AM'=>'051',
			'AW'=>'533',
			'AU'=>'036',
			'AT'=>'040',
			'AZ'=>'031',
			'BS'=>'044',
			'BH'=>'048',
			'BD'=>'050',
			'BB'=>'052',
			'BY'=>'112',
			'BE'=>'056',
			'BZ'=>'084',
			'BJ'=>'204',
			'BM'=>'060',
			'BT'=>'064',
			'BO'=>'068',
			'BA'=>'070',
			'BW'=>'072',
			'BV'=>'074',
			'BR'=>'076',
			'IO'=>'086',
			'BN'=>'096',
			'BG'=>'100',
			'BF'=>'854',
			'BI'=>'108',
			'KH'=>'116',
			'CM'=>'120',
			'CA'=>'124',
			'CV'=>'132',
			'KY'=>'136',
			'CF'=>'140',
			'TD'=>'148',
			'CL'=>'152',
			'CN'=>'156',
			'CX'=>'162',
			'CC'=>'166',
			'CO'=>'170',
			'KM'=>'174',
			'CG'=>'178',
			'CK'=>'184',
			'CR'=>'188',
			'CI'=>'384',
			'HR'=>'191',
			'CU'=>'192',
			'CY'=>'196',
			'CZ'=>'203',
			'DK'=>'208',
			'DJ'=>'262',
			'DM'=>'212',
			'DO'=>'214',
			'TP'=>'626',
			'EC'=>'218',
			'EG'=>'818',
			'SV'=>'222',
			'GQ'=>'226',
			'ER'=>'232',
			'EE'=>'233',
			'ET'=>'231',
			'FK'=>'238',
			'FO'=>'234',
			'FJ'=>'242',
			'FI'=>'246',
			'FR'=>'250',
			'FX'=>'249',
			'GF'=>'254',
			'PF'=>'258',
			'TF'=>'260',
			'GA'=>'266',
			'GM'=>'270',
			'GE'=>'268',
			'DE'=>'276',
			'GH'=>'288',
			'GI'=>'292',
			'GR'=>'300',
			'GL'=>'304',
			'GD'=>'308',
			'GP'=>'312',
			'GU'=>'316',
			'GT'=>'320',
			'GN'=>'324',
			'GW'=>'624',
			'GY'=>'328',
			'HT'=>'332',
			'HM'=>'334',
			'VA'=>'336',
			'HN'=>'340',
			'HK'=>'344',
			'HU'=>'348',
			'IS'=>'352',
			'IN'=>'356',
			'ID'=>'360',
			'IR'=>'364',
			'IQ'=>'368',
			'IE'=>'372',
			'IL'=>'376',
			'IT'=>'380',
			'JM'=>'388',
			'JP'=>'392',
			'JO'=>'400',
			'KZ'=>'398',
			'KE'=>'404',
			'KI'=>'296',
			'KP'=>'408',
			'KR'=>'410',
			'KW'=>'414',
			'KG'=>'417',
			'LA'=>'418',
			'LV'=>'428',
			'LB'=>'422',
			'LS'=>'426',
			'LR'=>'430',
			'LY'=>'434',
			'LI'=>'438',
			'LT'=>'440',
			'LU'=>'442',
			'MO'=>'446',
			'MK'=>'807',
			'MG'=>'450',
			'MW'=>'454',
			'MY'=>'458',
			'MV'=>'462',
			'ML'=>'466',
			'MT'=>'470',
			'MH'=>'584',
			'MQ'=>'474',
			'MR'=>'478',
			'MU'=>'480',
			'YT'=>'175',
			'MX'=>'484',
			'FM'=>'583',
			'MD'=>'498',
			'MC'=>'492',
			'MN'=>'496',
			'MS'=>'500',
			'MA'=>'504',
			'MZ'=>'508',
			'MM'=>'104',
			'NA'=>'516',
			'NR'=>'520',
			'NP'=>'524',
			'NL'=>'528',
			'AN'=>'530',
			'NC'=>'540',
			'NZ'=>'554',
			'NI'=>'558',
			'NE'=>'562',
			'NG'=>'566',
			'NU'=>'570',
			'NF'=>'574',
			'MP'=>'580',
			'NO'=>'578',
			'OM'=>'512',
			'PK'=>'586',
			'PW'=>'585',
			'PA'=>'591',
			'PG'=>'598',
			'PY'=>'600',
			'PE'=>'604',
			'PH'=>'608',
			'PN'=>'612',
			'PL'=>'616',
			'PT'=>'620',
			'PR'=>'630',
			'QA'=>'634',
			'RE'=>'638',
			'RO'=>'642',
			'RU'=>'643',
			'RW'=>'646',
			'KN'=>'659',
			'LC'=>'662',
			'VC'=>'670',
			'WS'=>'882',
			'SM'=>'674',
			'ST'=>'678',
			'SA'=>'682',
			'SN'=>'686',
			'SC'=>'690',
			'SL'=>'694',
			'SG'=>'702',
			'SK'=>'703',
			'SI'=>'705',
			'SB'=>'090',
			'SO'=>'706',
			'ZA'=>'710',
			'GS'=>'239',
			'ES'=>'724',
			'LK'=>'144',
			'SH'=>'654',
			'PM'=>'666',
			'SD'=>'736',
			'SR'=>'740',
			'SJ'=>'744',
			'SZ'=>'748',
			'SE'=>'752',
			'CH'=>'756',
			'SY'=>'760',
			'TW'=>'158',
			'TJ'=>'762',
			'TZ'=>'834',
			'TH'=>'764',
			'TG'=>'768',
			'TK'=>'772',
			'TO'=>'776',
			'TT'=>'780',
			'TN'=>'788',
			'TR'=>'792',
			'TM'=>'795',
			'TC'=>'796',
			'TV'=>'798',
			'UG'=>'800',
			'UA'=>'804',
			'AE'=>'784',
			'GB'=>'826',
			'US'=>'840',
			'UM'=>'581',
			'UY'=>'858',
			'UZ'=>'860',
			'VU'=>'548',
			'VE'=>'862',
			'VN'=>'704',
			'VG'=>'092',
			'VI'=>'850',
			'WF'=>'876',
			'EH'=>'732',
			'YE'=>'887',
			'YU'=>'891',
			'ZR'=>'180',
			'ZM'=>'894',
			'ZW'=>'716'
		);

		if (isset($iso_codes[$countrycode]))
		{
			$countrycode = $iso_codes[$countrycode];
		}
		else
		{
			$countrycode = "";
		}

		$new_status = false;

		if (!class_exists('VirtueMartModelOrders'))
		{
			require_once JPATH_VM_ADMINISTRATOR . DS . 'models' . DS . 'orders.php';
		}

		$session = JFactory::getSession();
		$return_context = $session->getId();

		$hash_variables = array();
		$hash_variables['PreSharedKey'] = $method->merchant_psk;
		$hash_variables['MerchantID'] = $method->merchant_id;
		$hash_variables['Password'] = $method->merchant_password;

		$hash_variables['Amount'] = round($order['details']['BT']->order_total, 2)*100;
		$hash_variables['CurrencyCode'] = $currency_numeric_code;
		$hash_variables['OrderID'] = $this->stripGWInvalidChars($order['details']['BT']->order_number);
		$hash_variables['TransactionType'] = "SALE";
		$hash_variables['TransactionDateTime'] = date("Y-m-d H:i:s P");
		$hash_variables['CallbackURL'] = JROUTE::_(JURI::root() . 'index.php?option=com_virtuemart&view=pluginresponse&task=pluginresponsereceived&on=' . $order['details']['BT']->order_number . '&pm=' . $order['details']['BT']->virtuemart_paymentmethod_id . '&module=paymentsense');
		$hash_variables['OrderDescription'] = $this->stripGWInvalidChars("Order Number:" . $order['details']['BT']->order_number);
		$hash_variables['CustomerName'] = $this->stripGWInvalidChars(stripslashes($order['details']['BT']->first_name . ' ' . $order['details']['BT']->last_name));
		$hash_variables['Address1'] = $this->stripGWInvalidChars(stripslashes($order['details']['BT']->address_1));
		$hash_variables['Address2'] = isset($order['details']['BT']->address_2) ? $this->stripGWInvalidChars(stripslashes($order['details']['BT']->address_2)) : '';
		$hash_variables['Address3'] = '';
		$hash_variables['Address4'] = '';
		$hash_variables['City'] = $this->stripGWInvalidChars(stripslashes($order['details']['BT']->city));
		$hash_variables['State'] = isset($order['details']['BT']->virtuemart_state_id) ? $this->stripGWInvalidChars(stripslashes(ShopFunctions::getStateByID($order['details']['BT']->virtuemart_state_id))) : '';
		$hash_variables['PostCode'] = $this->stripGWInvalidChars(stripslashes($order['details']['BT']->zip));
		$hash_variables['CountryCode'] = $countrycode;

		$hash_variables["CV2Mandatory"] = "TRUE";
		$hash_variables["Address1Mandatory"] = "TRUE";
		$hash_variables["CityMandatory"] = "TRUE";
		$hash_variables["PostCodeMandatory"] = "TRUE";
		$hash_variables["StateMandatory"] = "FALSE";
		$hash_variables["CountryMandatory"] = "TRUE";

		$hash_variables["ResultDeliveryMethod"] = "SERVER";
		$hash_variables["ServerResultURL"] = JROUTE::_(JURI::root() . 'index.php?option=com_virtuemart&view=pluginresponse&task=pluginnotification&tmpl=component');

		$hash_variables["PaymentFormDisplaysResult"] = "FALSE";

		$hash_variables["ServerResultURLCookieVariables"] = "";
		$hash_variables["ServerResultURLFormVariables"] = "return_context=" . $return_context . "&pm=" . $order['details']['BT']->virtuemart_paymentmethod_id . "&module=paymentsense";
		$hash_variables["ServerResultURLQueryStringVariables"] = "";

		$plain = "";

		foreach($hash_variables as $k=>$v)
		{
			$plain .= $k . "=" . $v . "&";
		}

		$plain = rtrim($plain,"&");

		$hash = sha1($plain);

		unset($hash_variables['PreSharedKey']);
		unset($hash_variables['Password']);

		$post_variables = array();

		$post_variables['HashDigest'] = $hash;

		foreach($hash_variables as $k=>$v)
		{
			$post_variables[$k] = $v;
		}

		$this->_virtuemart_paymentmethod_id = $order['details']['BT']->virtuemart_paymentmethod_id;
		$dbValues['payment_name'] = parent::renderPluginName($method);
		$dbValues['order_number'] = $order['details']['BT']->order_number;
		$dbValues['virtuemart_paymentmethod_id'] = $this->_virtuemart_paymentmethod_id;
		$dbValues['cost'] = $method->cost;
		$dbValues['tax_id'] = $method->tax_id;

		$html = '<img align="center" src="components/com_virtuemart/assets/images/vm-preloader.gif">';
		$html.= '<form action="https://mms.paymentsensegateway.com/Pages/PublicPages/PaymentForm.aspx" method="post" name="vm_paymentsense_form" >';
		foreach ($post_variables as $name => $value)
		{
			$html.= '<input type="hidden" name="' . $name . '" value="' . $value . '" />';
		}
		$html.= '<input type="hidden" name="ServerResultCompatMode" value="false" />';
		$html.= '</form>';
		$html.= ' <script type="text/javascript">';
		$html.= ' setTimeout(\'document.vm_paymentsense_form.submit()\',3500);';
		$html.= ' </script>';

		return $this->processConfirmedOrderPaymentResponse(2, $cart, $order, $html, $new_status);
	}

	/**
	 * Handler of the payment notification sent to the ServerResultURL
	 *
	 * @return null|true
	 * @since N/A
	 */
	public function plgVmOnPaymentNotification()
	{
		$paymentsense_data = JRequest::get('request');

		if ($paymentsense_data['module'] != "paymentsense")
		{
			return null; // Another method was selected, do nothing
		}

		// Retrieve order ID from database
		if (!class_exists('VirtueMartModelOrders'))
		{
			require_once JPATH_VM_ADMINISTRATOR . DS . 'models' . DS . 'orders.php';
		}
		$virtuemart_order_id = VirtueMartModelOrders::getOrderIdByOrderNumber($paymentsense_data['OrderID']);

		if (!$virtuemart_order_id)
		{
			JError::raiseNotice('OrderNotFound', 'StatusCode=30&Message=Order:+' . $paymentsense_data['OrderID'] . '+could+not+be+found');
			return null; //order cannot be found
		}

		//pull out order info using ID
		$order = VirtueMartModelOrders::getOrder($virtuemart_order_id);
		$order_status_code = $order['items'][0]->order_status;

		//check status returned by payment processor
		if ($paymentsense_data['StatusCode'] == 0)
		{
			if (!class_exists('VirtueMartCart'))
			{
				require_once JPATH_VM_SITE . DS . 'helpers' . DS . 'cart.php';
			}
			session_id($paymentsense_data['return_context']);
			session_start();
			$cart = VirtueMartCart::getCart();
			$cart->emptyCart();

			$new_status = "C"; //complete status
		}
		else
		{
			$new_status = "X"; //canceled/declined status
		}

		//check current status - so we don't update an order which has already been updated already
		if ($order_status_code == 'P')
		{
			// save order data
			$modelOrder = new VirtueMartModelOrders();
			$order['order_status'] = $new_status;
			$order['virtuemart_order_id'] = $virtuemart_order_id;
			$order['customer_notified'] = 1;
			$order['comments'] = $paymentsense_data["Message"];
			//update order with new status and success/failure message
			$modelOrder->updateStatusForOneOrder($virtuemart_order_id, $order, true);
		}

		JError::raiseNotice('ServerResultURLReturn', 'StatusCode=0');

		return true;
	}

	/**
	 * Handler of the callback request sent to the CallbackURL
	 *
	 * @return null|string|true
	 * @since N/A
	 */
	public function plgVmOnPaymentResponseReceived(&$html)
	{
		$paymentsense_data = JRequest::get('request');

		if ($paymentsense_data['module'] != "paymentsense")
		{
			return null; // Another method was selected, do nothing
		}

		if (!class_exists('VirtueMartCart'))
		{
			require_once JPATH_VM_SITE . DS . 'helpers' . DS . 'cart.php';
		}

		if (!class_exists('shopFunctionsF'))
		{
			require_once JPATH_VM_SITE . DS . 'helpers' . DS . 'shopfunctionsf.php';
		}

		if (!class_exists('VirtueMartModelOrders'))
		{
			require_once JPATH_VM_ADMINISTRATOR . DS . 'models' . DS . 'orders.php';
		}

		$virtuemart_order_id = VirtueMartModelOrders::getOrderIdByOrderNumber($paymentsense_data['OrderID']);

		if (!$virtuemart_order_id)
		{
			return null;
		}

		$order = VirtueMartModelOrders::getOrder($virtuemart_order_id);
		//$order_status_code = $order['items'][0]->order_status;


		$db = JFactory::getDBO();
		$q = "SELECT * FROM #__virtuemart_orders WHERE virtuemart_order_id = " . $virtuemart_order_id;
		$db->setQuery($q);
		if (!($orderTable = $db->loadObject()))
		{
			JError::raiseWarning(500, $db->getErrorMsg());
			return '';
		}

		$order_status_code = $orderTable->order_status;

		$historycount = count($order['history']);

		$orderhistory = $order['history'][$historycount - 1];

		if ($order_status_code == "C")
		{
			$html = "<h3>Payment Was Successful</h3>";
			$html .= "<p>Your payment was successful. Your order will be dispatched shortly, subject to our security checks.</p>";
			$html .= "<p><b>Your Order Number:</b> " . $paymentsense_data['OrderID'] . "<br>";
			$html .= "<b>Authorisation Code:</b> " . str_replace("AuthCode: ","",$orderhistory->comments) . "<br>";
			$html .= "<b>Transaction ID:</b> " . $paymentsense_data['CrossReference'] . "</p>";
		}
		else if ($order_status_code == "X")
		{
			$html = "<h3>Your Payment Failed</h3>";
			$html .= "<p><b>Your payment was not authorised.</b><br>The reason for the decline is shown below.<br>";
			$html .= "<b>" . $orderhistory->comments . "</b><p>";
			$html .= "<p>Please check your billing address and card details. Alternatively, try a different credit/debit card.</p>";
			$html .= JText::sprintf('<p><a href="' . JROUTE::_(JURI::root()) . 'index.php/cart">Click here to return to the checkout</a>.</p>');
		}

		return true;
	}

	/**
	 * @see plgVmPaymentStandard::plgVmOnShowOrderBEPayment
	 * @since N/A
	 */
	public function plgVmOnShowOrderBEPayment($virtuemart_order_id, $payment_method_id)
	{
		if (!$this->selectedThisByMethodId($payment_method_id))
		{
			return null; // Another method was selected, do nothing
		}

		if (!($paymentTable = $this->getDataByOrderId($virtuemart_order_id)))
		{
			return null;
		}
		VmConfig::loadJLang('com_virtuemart');

		$html = '<table class="adminlist table">' . "\n";
		$html .= $this->getHtmlHeaderBE();
		$html .= $this->getHtmlRowBE('COM_VIRTUEMART_PAYMENT_NAME', $paymentTable->payment_name);
		$html .= $this->getHtmlRowBE('STANDARD_PAYMENT_TOTAL_CURRENCY', $paymentTable->payment_order_total . ' ' . $paymentTable->payment_currency);
		if ($paymentTable->email_currency)
		{
			$html .= $this->getHtmlRowBE('STANDARD_EMAIL_CURRENCY', $paymentTable->email_currency );
		}
		$html .= '</table>' . "\n";
		return $html;
	}

	/**
	 * @see plgVmPaymentStandard::checkConditions
	 * @since N/A
	 */
	protected function checkConditions($cart, $method, $cart_prices)
	{
		$address = ($cart->ST == 0) ? $cart->BT : $cart->ST;

		$amount = $cart_prices['salesPrice'];
		$amount_cond = $amount >= $method->min_amount AND $amount <= $method->max_amount OR ($method->min_amount <= $amount AND ($method->max_amount == 0) );
		if (!$amount_cond)
		{
			return false;
		}
		$countries = array();
		if (!empty($method->countries))
		{
			if (!is_array($method->countries))
			{
				$countries[0] = $method->countries;
			}
			else
			{
				$countries = $method->countries;
			}
		}

		// probably did not gave his BT:ST address
		if (!is_array($address))
		{
			$address = array();
			$address['virtuemart_country_id'] = 0;
		}

		if (!isset($address['virtuemart_country_id']))
		{
			$address['virtuemart_country_id'] = 0;
		}
		if (count($countries) == 0 || in_array($address['virtuemart_country_id'], $countries) || count($countries) == 0)
		{
			return true;
		}
		return false;
	}

	/**
	 * @see plgVmPaymentStandard::plgVmOnStoreInstallPaymentPluginTable
	 * @since N/A
	 */
	public function plgVmOnStoreInstallPaymentPluginTable($jplugin_id)
	{
		return $this->onStoreInstallPluginTable($jplugin_id);
	}

	/**
	 * @see plgVmPaymentStandard::plgVmOnSelectCheckPayment
	 * @since N/A
	 */
	public function plgVmOnSelectCheckPayment(VirtueMartCart $cart, &$msg)
	{
		return $this->OnSelectCheck($cart);
	}

	/**
	 * @see plgVmPaymentStandard::plgVmDisplayListFEPayment
	 * @since N/A
	 */
	public function plgVmDisplayListFEPayment(VirtueMartCart $cart, $selected = 0, &$htmlIn)
	{
		return $this->displayListFE($cart, $selected, $htmlIn);
	}

	/**
	 * @see plgVmPaymentStandard::plgVmonSelectedCalculatePricePayment
	 * @since N/A
	 */
	public function plgVmonSelectedCalculatePricePayment(VirtueMartCart $cart, array &$cart_prices, &$cart_prices_name)
	{
		return $this->onSelectedCalculatePrice($cart, $cart_prices, $cart_prices_name);
	}

	/**
	 * @see plgVmPaymentStandard::plgVmgetPaymentCurrency
	 * @since N/A
	 */
	public function plgVmgetPaymentCurrency($virtuemart_paymentmethod_id, &$paymentCurrencyId)
	{
		if (!($method = $this->getVmPluginMethod($virtuemart_paymentmethod_id)))
		{
			return null; // Another method was selected, do nothing
		}
		if (!$this->selectedThisElement($method->payment_element))
		{
			return false;
		}
		$this->getPaymentCurrency($method);

		$paymentCurrencyId = $method->payment_currency;
		return null;
	}

	/**
	 * @see plgVmPaymentStandard::plgVmOnCheckAutomaticSelectedPayment
	 * @since N/A
	 */
	public function plgVmOnCheckAutomaticSelectedPayment(VirtueMartCart $cart, array $cart_prices = array(), &$paymentCounter)
	{
		return $this->onCheckAutomaticSelected($cart, $cart_prices, $paymentCounter);
	}

	/**
	 * @see plgVmPaymentStandard::plgVmOnShowOrderFEPayment
	 * @since N/A
	 */
	public function plgVmOnShowOrderFEPayment($virtuemart_order_id, $virtuemart_paymentmethod_id, &$payment_name)
	{
		$this->onShowOrderFE($virtuemart_order_id, $virtuemart_paymentmethod_id, $payment_name);
	}

	/**
	 * @see plgVmPaymentStandard::plgVmOnUserInvoice
	 * @since N/A
	 */
	public function plgVmOnUserInvoice($orderDetails, &$data)
	{
		if (!($method = $this->getVmPluginMethod($orderDetails['virtuemart_paymentmethod_id'])))
		{
			return null;
		}
		if (!$this->selectedThisElement($method->payment_element))
		{
			return null;
		}
		if (!isset($method->send_invoice_on_order_null) or $method->send_invoice_on_order_null==1 or $orderDetails['order_total'] > 0.00)
		{
			return null;
		}
		if ($orderDetails['order_salesPrice']==0.00)
		{
			$data['invoice_number'] = 'reservedByPayment_' . $orderDetails['order_number'];
		}
	}

	/**
	 * @see plgVmPaymentStandard::plgVmgetEmailCurrency
	 * @since N/A
	 */
	public function plgVmgetEmailCurrency($virtuemart_paymentmethod_id, $virtuemart_order_id, &$emailCurrencyId)
	{
		if (!($method = $this->getVmPluginMethod($virtuemart_paymentmethod_id)))
		{
			return null;
		}
		if (!$this->selectedThisElement($method->payment_element))
		{
			return false;
		}
		if (!($payments = $this->getDatasByOrderId($virtuemart_order_id)))
		{
			return '';
		}
		if (empty($payments[0]->email_currency))
		{
			$vendorId = 1;
			$db = JFactory::getDBO();
			$q = 'SELECT   `vendor_currency` FROM `#__virtuemart_vendors` WHERE `virtuemart_vendor_id`=' . $vendorId;
			$db->setQuery($q);
			$emailCurrencyId = $db->loadResult();
		}
		else
		{
			$emailCurrencyId = $payments[0]->email_currency;
		}
		return null;
	}

	/**
	 * @see plgVmPaymentStandard::plgVmonShowOrderPrintPayment
	 * @since N/A
	 */
	public function plgVmonShowOrderPrintPayment($order_number, $method_id)
	{
		return $this->onShowOrderPrint($order_number, $method_id);
	}

	/**
	 * @see plgVmPaymentStandard::plgVmDeclarePluginParamsPaymentVM3
	 * @since N/A
	 */
	public function plgVmDeclarePluginParamsPaymentVM3(&$data)
	{
		return $this->declarePluginParams('payment', $data);
	}

	/**
	 * @see plgVmPaymentStandard::plgVmSetOnTablePluginParamsPayment
	 * @since N/A
	 */
	public function plgVmSetOnTablePluginParamsPayment($name, $id, &$table)
	{
		return $this->setOnTablePluginParams($name, $id, $table);
	}

	/**
	 * @see plgVmPaymentStandard::plgVmOnShowOrderLineFE
	 * @since N/A
	 */
	public function plgVmOnShowOrderLineFE($_orderId, $_lineId)
	{
		return null;
	}

	/**
	 * Strips invalid characters from a string
	 *
	 * @param $strToCheck string The string
	 * @return string
	 * @since N/A
	 */
	protected function stripGWInvalidChars($strToCheck)
	{
		$toReplace = array("#","\\",">","<", "\"", "[", "]", "_");
		$cleanString = str_replace($toReplace, "", $strToCheck);
		return $cleanString;
	}
}
