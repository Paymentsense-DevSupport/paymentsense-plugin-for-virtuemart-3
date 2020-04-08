<?php
/**
 * Paymentsense Plugin for VirtueMart 3
 * Version: 3.0.2
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
 * @version     3.0.2
 * @author      Paymentsense
 * @copyright   2020 Paymentsense Ltd.
 * @license     https://www.gnu.org/licenses/gpl-2.0.html
 */

defined('_JEXEC') or die('Restricted access');

if (!class_exists('vmPSPlugin')) {
    require_once VMPATH_PLUGINLIBS . DS . 'vmpsplugin.php';
}

class plgVmPaymentPaymentsense extends vmPSPlugin
{
    /**
     * VirtueMart Debug Flag
     */
    const DEBUG = true;

    /**
     * Paymentsense Transaction Result Codes
     */
    const PS_TRX_RESULT_CODE_SUCCESS = '0';

    /**
     * Paymentsense Request Types
     */
    const PS_REQ_NOTIFICATION      = '0';
    const PS_REQ_CUSTOMER_REDIRECT = '1';

    /**
     * Paymentsense Response Status Codes
     */
    const PS_STATUS_CODE_OK    = '0';
    const PS_STATUS_CODE_ERROR = '30';

    /**
     * Paymentsense Hosted Payment Form URL
     */
    const PS_HPF_URL = 'https://mms.paymentsensegateway.com/Pages/PublicPages/PaymentForm.aspx';

    /**
     * VirtueMart Order Statuses
     */
    const VM_ORDER_STATUS_CREATED   = 'P';
    const VM_ORDER_STATUS_CONFIRMED = 'C';
    const VM_ORDER_STATUS_CANCELLED = 'X';

    /**
     * Response messages
     */
    const MSG_SUCCESS           = 'Request processed successfully.';
    const MSG_HASH_DIGEST_ERROR = 'Invalid or empty hash digest.';

    /**
     * @var object
     * VirtueMart Plugin Method
     */
    protected $method;

    /**
     * @var array
     * VirtueMart Order
     */
    protected $order;

    /**
     * @var object
     * VirtueMart Order Info
     */
    protected $orderInfo;

    /**
     * @var array
     * Response variables for the payment notification
     */
    protected $responseVars = array(
        'status_code' => '',
        'message'     => ''
    );

    public function __construct(&$subject, $config)
	{
		parent::__construct($subject, $config);
        $this->tableFields = array_keys($this->getTableSQLFields());
		$this->setConfigParameterable($this->_configTableFieldName, $this->getVarsToPush());
        $this->_debug = self::DEBUG;
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
	 * Prepares and submits the output containing the redirect to the Hosted Payment Form
	 * to the processConfirmedOrderPaymentResponse function
	 *
	 * @param object $cart  The cart
	 * @param array  $order The order
     * @return bool|null
     *
	 * @since N/A
	 */
	public function plgVmConfirmedOrder($cart, $order)
	{
	    $result = true;
	    $this->setOrder($order);
        $method = $this->getMethodByOrderInfo();
        if ($this->isMethodSelected($method)) {
            $this->setMethod($method);
            $html = $this->renderByLayout ('redirect', $this->buildHpfFields());
            $this->processConfirmedOrderPaymentResponse(2, $cart, $order, $html, $this->_name);
        } else {
            $result = $method;
        }
        $this->logInfo(__METHOD__ . ': ' . var_export($result, true), 'message');
		return $result;
	}

    /**
     * Handler of the payment notification sent to the ServerResultURL
     *
     * @return bool|null
     *
     * @since N/A
     */
    public function plgVmOnPaymentNotification()
    {
        $result = $this->validateRequest();
        if ($result) {
            $request = $_REQUEST;
            $order = $this->getOrder();
            $virtuemart_order_id = VirtueMartModelOrders::getOrderIdByOrderNumber($request['OrderID']);
            if (!$this->isHashDigestValid(self::PS_REQ_NOTIFICATION, $request)) {
                $this->setErrorResponse(self::MSG_HASH_DIGEST_ERROR);
                $this->logInfo(__METHOD__ . ': ' . self::MSG_HASH_DIGEST_ERROR, 'error', true);
            } else {
                $orderHistory = array_pop($order['history']);
                if ($orderHistory->order_status_code == self::VM_ORDER_STATUS_CREATED) {
                    $status = ($request['StatusCode'] == self::PS_TRX_RESULT_CODE_SUCCESS)
                        ? self::VM_ORDER_STATUS_CONFIRMED
                        : self::VM_ORDER_STATUS_CANCELLED;
                    $order['order_status'] = $status;
                    $order['virtuemart_order_id'] = $virtuemart_order_id;
                    $order['customer_notified'] = 1;
                    $order['comments'] = $request['Message'];
                    $modelOrder = new VirtueMartModelOrders();
                    $modelOrder->updateStatusForOneOrder($order['virtuemart_order_id'], $order, true);
                    $this->logInfo(
                        __METHOD__ . ':' .
                        ' Order: ' . $request['OrderID'] .
                        ', Paymentsense Status Code: ' . $request['StatusCode'] .
                        ', Paymentsense Message: ' . $request['Message'] .
                        ', Order Status Code: ' . $status,
                        'message'
                    );
                }
                $this->setSuccessResponse();
            }
            $this->outputResponse();
        }
        $this->logInfo(__METHOD__ . ': ' . var_export($result, true), 'message');
        return $result;
    }

    /**
     * Handler of the callback request sent to the CallbackURL
     *
     * @param string $html Payment response HTML content
     * @param string $paymentResponse Payment response title
     * @return bool|null
     *
     * @since N/A
     */
    public function plgVmOnPaymentResponseReceived(&$html, &$paymentResponse)
    {
        $result = $this->validateRequest();
        if ($result) {
            $request = $_REQUEST;
            $order = $this->getOrder();
            if (!$this->isHashDigestValid(self::PS_REQ_CUSTOMER_REDIRECT, $request)) {
                $paymentResponse = 'Payment Processing Error';
                $html = $this->renderByLayout (
                    'payment_error',
                    array()
                );
                $this->logInfo(__METHOD__ . ': ' . self::MSG_HASH_DIGEST_ERROR, 'error', true);
            } else {
                $orderHistory = array_pop($order['history']);
                switch ($orderHistory->order_status_code) {
                    case self::VM_ORDER_STATUS_CONFIRMED:
                        $cart = VirtueMartCart::getCart();
                        $cart->emptyCart();
                        $html = $this->renderByLayout (
                            'payment_successful',
                            array(
                                'OrderID' => $request['OrderID']
                            )
                        );
                        $this->logInfo(
                            __METHOD__ . ':' .
                            ' Order: ' . $request['OrderID'] .
                            ', Order Status Code: ' . $orderHistory->order_status_code .
                            ', Paymentsense Message: ' . $orderHistory->comments,
                            'message'
                        );
                        break;
                    case self::VM_ORDER_STATUS_CANCELLED:
                        $paymentResponse = 'Payment Failed';
                        $html = $this->renderByLayout (
                            'payment_failed',
                            array(
                                'message'  => $orderHistory->comments,
                                'cart_url' => $this->getCartUrl(),
                            )
                        );
                        $this->logInfo(
                            __METHOD__ . ':' .
                            ' Order: ' . $request['OrderID'] .
                            ', Order Status Code: ' . $orderHistory->order_status_code .
                            ', Paymentsense Message: ' . $orderHistory->comments,
                            'message'
                        );
                        break;
                    default:
                        $paymentResponse = 'Payment Processing Error';
                        $html = $this->renderByLayout (
                            'payment_error',
                            array()
                        );
                        $this->logInfo(
                            __METHOD__ . ':' .
                            ' Order: ' . $request['OrderID'] .
                            ', Order Status Code: ' . $orderHistory->order_status_code .
                            ', Paymentsense Message: ' . $orderHistory->comments,
                            'error',
                            true
                        );
                }
            }
        }
        $this->logInfo(__METHOD__ . ': ' . var_export($result, true), 'message');
        return $result;
    }

    /**
     * Checks the conditions of using the payment method
     *
     * @param VirtueMartCart $cart
     * @param object         $method
     * @param array          $cart_prices
     * @return bool
     *
     * @since N/A
     */
    protected function checkConditions($cart, $method, $cart_prices)
    {
        $this->setMethod($method);
        $result = !empty($this->getMerchantId())
            && !empty($this->getPassword())
            && !empty($this->getPresharedKey())
            && !empty($this->getHashMethod());
        $this->logInfo(__METHOD__ . ': ' . var_export($result, true), 'message');
        return $result;
    }

    /**
     * Sets the order
     * @param array $order
     */
    protected function setOrder($order)
    {
        $this->order = $order;
        if (isset($order['details']['BT'])) {
            $this->orderInfo = $order['details']['BT'];
        }
    }

    /**
     * Gets the order
     *
     * @return array $order
     */
    protected function getOrder()
    {
        return $this->order;
    }

    /**
     * Gets the order info
     *
     * @return object
     */
    protected function getOrderInfo()
    {
        return $this->orderInfo;
    }

    /**
     * Sets the VirtueMart Plugin Method
     *
     * @param object $method
     */
    protected function setMethod($method)
    {
        $this->method = $method;
    }

    /**
     * Gets the VirtueMart Plugin Method by the order info
     *
     * @return object|bool
     */
    protected function getMethodByOrderInfo()
    {
        $result = false;
        if (is_object($this->getOrderInfo())) {
            $result = $this->getVmPluginMethod(($this->getOrderInfo()->virtuemart_paymentmethod_id));
        }
        return $result;
    }

    /**
     * Checks whether the Paymentsense Plugin Method is selected
     *
     * @param object $method VirtueMart Plugin Method
     * @return bool|null
     */
    protected function isMethodSelected($method)
    {
        $result = null;
        if (is_object($method)) {
            $result = $this->selectedThisElement($method->payment_element);
        }
        return $result;
    }

    /**
     * Gets the payment gateway Merchant ID
     *
     * @return string
     */
    protected function getMerchantId()
    {
        return trim($this->method->gateway_merchant_id);
    }

    /**
     * Gets the payment gateway Password
     *
     * @return string
     */
    protected function getPassword()
    {
        return trim($this->method->gateway_password);
    }

    /**
     * Gets the payment gateway Pre-shared Key
     *
     * @return string
     */
    protected function getPresharedKey()
    {
        return trim($this->method->gateway_presharedkey);
    }

    /**
     * Gets the payment gateway Hash Method
     *
     * @return string
     */
    protected function getHashMethod()
    {
        return $this->method->gateway_hash_method;
    }

    /**
     * Gets the setting for forcing the notifications to HTTP
     *
     * @return string
     */
    protected function getForceNotificationsToHttp()
    {
        return $this->method->force_notif_to_http;
    }

    /**
     * Gets the Paymentsense Hosted Payment Form URL
     *
     * @return string
     */
    protected function getPaymentFormUrl()
    {
        return self::PS_HPF_URL;
    }

    /**
     * Gets the callback URL
     *
     * @return string
     */
    protected function getCallbackUrl()
    {
        return JROUTE::_(
            JURI::root() .
            'index.php?option=com_virtuemart' .
            '&view=pluginresponse' .
            '&task=pluginresponsereceived' .
            '&pm=' . $this->orderInfo->virtuemart_paymentmethod_id
        );
    }

    /**
     * Gets the notification URL
     *
     * @return string
     */
    protected function getNotificationUrl()
    {
        $url = JURI::root();

        if ($this->getForceNotificationsToHttp() == '1') {
            $url = preg_replace('/^https:/i', 'http:', $url);
        }

        return JROUTE::_(
            $url .
            'index.php?option=com_virtuemart' .
            '&view=pluginresponse' .
            '&task=pluginnotification' .
            '&pm=' . $this->orderInfo->virtuemart_paymentmethod_id
        );
    }

    /**
     * Gets the cart URL
     *
     * @return string
     */
    protected function getCartUrl()
    {
        return JROUTE::_(
            JURI::root() .
            'index.php?option=com_virtuemart'.
            '&view=cart'
        );
    }

    /**
     * Builds the fields for the Hosted Payment Form as an associative array
     *
     * @return array An associative array containing the Required Input Variables for the API of the Hosted Payment Form
     */
    protected function buildHpfFields()
    {
        $fields = $this->buildPaymentFields();

        $fields = array_map(
            function ($value) {
                return $value === null ? '' : $value;
            },
            $fields
        );

        $data  = 'MerchantID=' . $this->getMerchantId();
        $data .= '&Password=' . $this->getPassword();

        foreach ($fields as $key => $value) {
            $data .= '&' . $key . '=' . $value;
        }

        $additionalFields = array(
            'HashDigest' => $this->calculateHashDigest($data, $this->getHashMethod(), $this->getPresharedKey()),
            'MerchantID' => $this->getMerchantId(),
        );

        $fields = array_merge($additionalFields, $fields);
        $fields = array_map(
            function( $value ) {
                return str_replace( '"', '\"', $value );
            },
            $fields
        );

        return [
            'url'      => $this->getPaymentFormUrl(),
            'elements' => $fields
        ];
    }

    /**
     * Builds the redirect form variables for the Hosted Payment Form
     *
     * @return array
     */
    protected function buildPaymentFields()
    {
        $orderInfo = $this->getOrderInfo();
        $currencyNumericCode = ShopFunctions::getCurrencyByID($orderInfo->order_currency,'currency_numeric_code');
        $countryAlpha2Code = ShopFunctions::getCountryByID($orderInfo->virtuemart_country_id, 'country_2_code');
        $countryNumericCode = $this->getCountryIsoCode($countryAlpha2Code);
        $state = ShopFunctions::getStateByID($orderInfo->virtuemart_state_id);
        return array(
            'Amount'                    => round($orderInfo->order_total, 2) * 100,
            'CurrencyCode'              => $currencyNumericCode,
            'OrderID'                   => $orderInfo->order_number,
            'TransactionType'           => 'SALE',
            'TransactionDateTime'       => date('Y-m-d H:i:s P'),
            'CallbackURL'               => $this->getCallbackUrl(),
            'OrderDescription'          => 'Order Number:' . $orderInfo->order_number,
            'CustomerName'              => $orderInfo->first_name . ' ' . $orderInfo->last_name,
            'Address1'                  => $orderInfo->address_1,
            'Address2'                  => $orderInfo->address_2,
            'Address3'                  => '',
            'Address4'                  => '',
            'City'                      => $orderInfo->city,
            'State'                     => $state,
            'PostCode'                  => $orderInfo->zip,
            'CountryCode'               => $countryNumericCode,
            'EmailAddress'              => $orderInfo->email,
            'PhoneNumber'               => $orderInfo->phone_1,
            'EmailAddressEditable'      => 'false',
            'PhoneNumberEditable'       => 'false',
            'CV2Mandatory'              => 'true',
            'Address1Mandatory'         => 'true',
            'CityMandatory'             => 'true',
            'PostCodeMandatory'         => 'true',
            'StateMandatory'            => 'false',
            'CountryMandatory'          => 'true',
            'ResultDeliveryMethod'      => 'SERVER',
            'ServerResultURL'           => $this->getNotificationUrl(),
            'PaymentFormDisplaysResult' => 'false'
        );
    }

    /**
     * Builds a string containing the expected fields from the request received from the payment gateway
     *
     * @param string $requestType Type of the request (notification or customer redirect)
     * @param array $data POST/GET data received with the request from the payment gateway
     * @return bool
     */
    protected function buildPostString($requestType, $data)
    {
        $result = false;
        $fields = array(
            // Variables for hash digest calculation for notification requests (excluding configuration variables)
            self::PS_REQ_NOTIFICATION => array(
                'StatusCode',
                'Message',
                'PreviousStatusCode',
                'PreviousMessage',
                'CrossReference',
                'Amount',
                'CurrencyCode',
                'OrderID',
                'TransactionType',
                'TransactionDateTime',
                'OrderDescription',
                'CustomerName',
                'Address1',
                'Address2',
                'Address3',
                'Address4',
                'City',
                'State',
                'PostCode',
                'CountryCode',
                'EmailAddress',
                'PhoneNumber'
            ),
            // Variables for hash digest calculation for customer redirects (excluding configuration variables)
            self::PS_REQ_CUSTOMER_REDIRECT => array(
                'CrossReference',
                'OrderID',
            ),
        );

        if (array_key_exists($requestType, $fields)) {
            $result = 'MerchantID=' . $this->getMerchantId() . '&Password=' . $this->getPassword();
            foreach ($fields[$requestType] as $field) {
                $result .= '&' . $field . '=' . str_replace('&amp;', '&', $data[$field]);
            }
        }

        return $result;
    }

    /**
     * Gets country ISO 3166-1 code
     *
     * @param  string $countryCode Country 3166-1 code.
     * @return string
     *
     * @SuppressWarnings(PHPMD.ExcessiveMethodLength)
     */
    protected function getCountryIsoCode($countryCode)
    {
        $result   = '';
        $isoCodes = array(
            'AL' => '8',
            'DZ' => '12',
            'AS' => '16',
            'AD' => '20',
            'AO' => '24',
            'AI' => '660',
            'AG' => '28',
            'AR' => '32',
            'AM' => '51',
            'AW' => '533',
            'AU' => '36',
            'AT' => '40',
            'AZ' => '31',
            'BS' => '44',
            'BH' => '48',
            'BD' => '50',
            'BB' => '52',
            'BY' => '112',
            'BE' => '56',
            'BZ' => '84',
            'BJ' => '204',
            'BM' => '60',
            'BT' => '64',
            'BO' => '68',
            'BA' => '70',
            'BW' => '72',
            'BR' => '76',
            'BN' => '96',
            'BG' => '100',
            'BF' => '854',
            'BI' => '108',
            'KH' => '116',
            'CM' => '120',
            'CA' => '124',
            'CV' => '132',
            'KY' => '136',
            'CF' => '140',
            'TD' => '148',
            'CL' => '152',
            'CN' => '156',
            'CO' => '170',
            'KM' => '174',
            'CG' => '178',
            'CD' => '180',
            'CK' => '184',
            'CR' => '188',
            'CI' => '384',
            'HR' => '191',
            'CU' => '192',
            'CY' => '196',
            'CZ' => '203',
            'DK' => '208',
            'DJ' => '262',
            'DM' => '212',
            'DO' => '214',
            'EC' => '218',
            'EG' => '818',
            'SV' => '222',
            'GQ' => '226',
            'ER' => '232',
            'EE' => '233',
            'ET' => '231',
            'FK' => '238',
            'FO' => '234',
            'FJ' => '242',
            'FI' => '246',
            'FR' => '250',
            'GF' => '254',
            'PF' => '258',
            'GA' => '266',
            'GM' => '270',
            'GE' => '268',
            'DE' => '276',
            'GH' => '288',
            'GI' => '292',
            'GR' => '300',
            'GL' => '304',
            'GD' => '308',
            'GP' => '312',
            'GU' => '316',
            'GT' => '320',
            'GN' => '324',
            'GW' => '624',
            'GY' => '328',
            'HT' => '332',
            'VA' => '336',
            'HN' => '340',
            'HK' => '344',
            'HU' => '348',
            'IS' => '352',
            'IN' => '356',
            'ID' => '360',
            'IR' => '364',
            'IQ' => '368',
            'IE' => '372',
            'IL' => '376',
            'IT' => '380',
            'JM' => '388',
            'JP' => '392',
            'JO' => '400',
            'KZ' => '398',
            'KE' => '404',
            'KI' => '296',
            'KP' => '408',
            'KR' => '410',
            'KW' => '414',
            'KG' => '417',
            'LA' => '418',
            'LV' => '428',
            'LB' => '422',
            'LS' => '426',
            'LR' => '430',
            'LY' => '434',
            'LI' => '438',
            'LT' => '440',
            'LU' => '442',
            'MO' => '446',
            'MK' => '807',
            'MG' => '450',
            'MW' => '454',
            'MY' => '458',
            'MV' => '462',
            'ML' => '466',
            'MT' => '470',
            'MH' => '584',
            'MQ' => '474',
            'MR' => '478',
            'MU' => '480',
            'MX' => '484',
            'FM' => '583',
            'MD' => '498',
            'MC' => '492',
            'MN' => '496',
            'MS' => '500',
            'MA' => '504',
            'MZ' => '508',
            'MM' => '104',
            'NA' => '516',
            'NR' => '520',
            'NP' => '524',
            'NL' => '528',
            'AN' => '530',
            'NC' => '540',
            'NZ' => '554',
            'NI' => '558',
            'NE' => '562',
            'NG' => '566',
            'NU' => '570',
            'NF' => '574',
            'MP' => '580',
            'NO' => '578',
            'OM' => '512',
            'PK' => '586',
            'PW' => '585',
            'PA' => '591',
            'PG' => '598',
            'PY' => '600',
            'PE' => '604',
            'PH' => '608',
            'PN' => '612',
            'PL' => '616',
            'PT' => '620',
            'PR' => '630',
            'QA' => '634',
            'RE' => '638',
            'RO' => '642',
            'RU' => '643',
            'RW' => '646',
            'SH' => '654',
            'KN' => '659',
            'LC' => '662',
            'PM' => '666',
            'VC' => '670',
            'WS' => '882',
            'SM' => '674',
            'ST' => '678',
            'SA' => '682',
            'SN' => '686',
            'SC' => '690',
            'SL' => '694',
            'SG' => '702',
            'SK' => '703',
            'SI' => '705',
            'SB' => '90',
            'SO' => '706',
            'ZA' => '710',
            'ES' => '724',
            'LK' => '144',
            'SD' => '736',
            'SR' => '740',
            'SJ' => '744',
            'SZ' => '748',
            'SE' => '752',
            'CH' => '756',
            'SY' => '760',
            'TW' => '158',
            'TJ' => '762',
            'TZ' => '834',
            'TH' => '764',
            'TG' => '768',
            'TK' => '772',
            'TO' => '776',
            'TT' => '780',
            'TN' => '788',
            'TR' => '792',
            'TM' => '795',
            'TC' => '796',
            'TV' => '798',
            'UG' => '800',
            'UA' => '804',
            'AE' => '784',
            'GB' => '826',
            'US' => '840',
            'UY' => '858',
            'UZ' => '860',
            'VU' => '548',
            'VE' => '862',
            'VN' => '704',
            'VG' => '92',
            'VI' => '850',
            'WF' => '876',
            'EH' => '732',
            'YE' => '887',
            'ZM' => '894',
            'ZW' => '716',
        );
        if (array_key_exists($countryCode, $isoCodes)) {
            $result = $isoCodes[$countryCode];
        }
        return $result;
    }

    /**
     * Checks whether the hash digest received from the payment gateway is valid
     *
     * @param string $requestType Type of the request (notification or customer redirect)
     * @param array $data POST/GET data received with the request from the payment gateway
     * @return bool
     */
    protected function isHashDigestValid($requestType, $data)
    {
        $result = false;
        if (isset($data['HashDigest'])) {
            $dataString = $this->buildPostString($requestType, $data);
            if ($dataString) {
                $hashDigestReceived   = $data['HashDigest'];
                $hashDigestCalculated = $this->calculateHashDigest(
                    $dataString,
                    $this->getHashMethod(),
                    $this->getPresharedKey()
                );
                $result = strToUpper($hashDigestReceived) === strToUpper($hashDigestCalculated);
            }
        }
        return $result;
    }

    /**
     * Calculates the hash digest.
     * Supported hash methods: MD5, SHA1, HMACMD5, HMACSHA1
     *
     * @param string $data Data to be hashed.
     * @param string $hashMethod Hash method.
     * @param string $key Secret key to use for generating the hash.
     * @return string
     */
    protected function calculateHashDigest($data, $hashMethod, $key)
    {
        $result     = '';
        $includeKey = in_array($hashMethod, ['MD5', 'SHA1'], true);
        if ($includeKey) {
            $data = 'PreSharedKey=' . $key . '&' . $data;
        }
        switch ($hashMethod) {
            case 'MD5':
                $result = md5($data);
                break;
            case 'SHA1':
                $result = sha1($data);
                break;
            case 'HMACMD5':
                $result = hash_hmac('md5', $data, $key);
                break;
            case 'HMACSHA1':
                $result = hash_hmac('sha1', $data, $key);
                break;
        }
        return $result;
    }

    /**
     * Validates the request sent from the Paymentsense gateway
     *
     * @return bool|null
     *
     * @since N/A
     */
    protected function validateRequest()
    {
        $result = null;
        $request = $_REQUEST;
        if (array_key_exists('pm', $request) && array_key_exists('OrderID', $request) )
        {
            $virtuemart_order_id = VirtueMartModelOrders::getOrderIdByOrderNumber($request['OrderID']);
            if ($virtuemart_order_id)
            {
                $orderModel = VmModel::getModel('orders');
                $order = $orderModel->getOrder($virtuemart_order_id);
                $this->setOrder($order);
                $orderInfo = $this->getOrderInfo();
                if ($orderInfo->virtuemart_paymentmethod_id == $request['pm'])
                {
                    $method = $this->getMethodByOrderInfo();
                    if ($this->isMethodSelected($method)) {
                        $this->setMethod($method);
                        $result = true;
                    } else {
                        $result = $method;
                    }
                }
            }
        }
        return $result;
    }

    /**
     * Sets the success response message and status code
     */
    protected function setSuccessResponse() {
        $this->setResponse(self::PS_STATUS_CODE_OK, self::MSG_SUCCESS);
    }

    /**
     * Sets the error response message and status code
     *
     * @param string $message Response message
     */
    protected function setErrorResponse($message) {
        $this->setResponse(self::PS_STATUS_CODE_ERROR, $message);
    }

    /**
     * Sets the response variables
     *
     * @param string $statusCode Response status code
     * @param string $message Response message
     */
    protected function setResponse($statusCode, $message) {
        $this->responseVars['status_code'] = $statusCode;
        $this->responseVars['message']     = $message;
    }

    /**
     * Outputs the response and exists
     */
    protected function outputResponse() {
        echo "StatusCode={$this->responseVars['status_code']}&Message={$this->responseVars['message']}";
        exit;
    }

    /**
     * The methods below implement the methods from the plgVmPaymentStandard class as they are
     * with the exception of non-functional changes
     */

    /**
     * @see plgVmPaymentStandard::plgVmOnShowOrderBEPayment
     * @since N/A
     */
    function plgVmOnShowOrderBEPayment($virtuemart_order_id, $virtuemart_payment_id)
    {
        if (!$this->selectedThisByMethodId($virtuemart_payment_id)) {
            return NULL; // Another method was selected, do nothing
        }

        if (!($paymentTable = $this->getDataByOrderId($virtuemart_order_id))) {
            return NULL;
        }
        vmLanguage::loadJLang('com_virtuemart');

        $html = '<table class="adminlist table">' . "\n";
        $html .= $this->getHtmlHeaderBE();
        $html .= $this->getHtmlRowBE('COM_VIRTUEMART_PAYMENT_NAME', $paymentTable->payment_name);
        $html .= $this->getHtmlRowBE('STANDARD_PAYMENT_TOTAL_CURRENCY', $paymentTable->payment_order_total . ' ' . $paymentTable->payment_currency);
        if ($paymentTable->email_currency) {
            $html .= $this->getHtmlRowBE('STANDARD_EMAIL_CURRENCY', $paymentTable->email_currency );
        }
        $html .= '</table>' . "\n";
        return $html;
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
        if (!($method = $this->getVmPluginMethod($virtuemart_paymentmethod_id))) {
            return NULL; // Another method was selected, do nothing
        }
        if (!$this->selectedThisElement($method->payment_element)) {
            return FALSE;
        }
        $this->getPaymentCurrency($method);

        $paymentCurrencyId = $method->payment_currency;
        return;
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
        if (!($method = $this->getVmPluginMethod($orderDetails['virtuemart_paymentmethod_id']))) {
            return NULL; // Another method was selected, do nothing
        }
        if (!$this->selectedThisElement($method->payment_element)) {
            return NULL;
        }

        if (!isset($method->send_invoice_on_order_null) or $method->send_invoice_on_order_null==1 or $orderDetails['order_total'] > 0.00) {
            return NULL;
        }

        if ($orderDetails['order_salesPrice']==0.00) {
            $data['invoice_number'] = 'reservedByPayment_' . $orderDetails['order_number']; // Never send the invoice via email
        }
    }

    /**
     * @see plgVmPaymentStandard::plgVmgetEmailCurrency
     * @since N/A
     */
    public function plgVmgetEmailCurrency($virtuemart_paymentmethod_id, $virtuemart_order_id, &$emailCurrencyId)
    {
        if (!($method = $this->getVmPluginMethod($virtuemart_paymentmethod_id))) {
            return NULL; // Another method was selected, do nothing
        }
        if (!$this->selectedThisElement($method->payment_element)) {
            return FALSE;
        }

        if (empty($method->email_currency)) {

        } else if ($method->email_currency == 'vendor') {
            $vendor_model = VmModel::getModel('vendor');
            $vendor = $vendor_model->getVendor($method->virtuemart_vendor_id);
            $emailCurrencyId = $vendor->vendor_currency;
        } else if ($method->email_currency == 'payment') {
            $emailCurrencyId = $this->getPaymentCurrency($method);
        }
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
}
