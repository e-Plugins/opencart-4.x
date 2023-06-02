<?php

/**
 *  DigiWallet.nl
 * DigiWallet plugin for Opencart 2.0+
 *
 *  (C) Copyright TargetMedia B.V 2014
 *
 * @file       DigiWallet Catalog Controller
 * @author   TargetMedia B.V / www.sofortplugins.nl
 * @release    5 nov 2014
 */
require_once(DIR_OPENCART . "extension/digiwallet/system/library/digiwallet.core.php");
define('OC_VERSION', substr(VERSION, 0, 1));

class DigiwalletFrontEnd extends \Opencart\System\Engine\Controller
{

    public $paymentType;

    public $paymentName;

    /**
     * Select bank
     */
    public function index()
    {
        $setting_model_key = ($this->prefix_code) . ($this->paymentName);
        $this->load->language('extension/digiwallet/payment/' . $setting_model_key);
        $data = [];
        
        $data['text_title'] = $this->language->get('text_title');
        $data['text_wait'] = $this->language->get('text_wait');
        
        $data['entry_bank_id'] = $this->language->get('entry_bank_id');
        $data['button_confirm'] = $this->language->get('button_confirm');
        
        $data = $this->setListConfirm($data);

        return $this->load->view($this->config->get('config_template') . 'extension/digiwallet/payment/' . $this->paymentName, $data);
    }

    /**
     * Get customer email info
     * @param $order
     * @return bool
     */
    public function getConsumerEmail($order)
    {
        if ($this->customer->isLogged()) {
            return $this->customer->getEmail();
        } else if(isset($order['email'])) {
            return $order['email'];
        }
        return false;
    }

    /**
     * Start payment
     */
    public function send()
    {
        try {
            $paymentType = $this->paymentType;
            $setting_name = (OC_VERSION == 2) ? '' : 'payment_';
            $setting_model_key = ($this->prefix_code) . ($this->paymentName);
            $this->load->language('extension/digiwallet/payment/' . $setting_model_key);

            $json = [];
            $this->load->model('checkout/order');

            $order_info = $this->model_checkout_order->getOrder($this->session->data['order_id']);

            $rtlo = ($this->config->get($setting_name . $setting_model_key . '_rtlo')) ? $this->config->get($setting_name . $setting_model_key . '_rtlo') : DigiWalletCore::DEFAULT_RTLO; // Default DigiWallet

            $consumer_email = $this->getConsumerEmail($order_info);
            // Tyson: 2020/10/28 - Remove country limitation from the plugin
            if(false) { //!in_array(strtolower($order_info['payment_iso_code_2']), ['nl', 'be']) || !in_array(strtolower($order_info['shipping_iso_code_2']), ['nl', 'be'])) {
                $this->log->write("Invalid shipping/payment country");
                $json['error'] = "Invalid shipping/payment country";
            }
            else if ($order_info['currency_code'] != "EUR") {
                $this->log->write("Invalid currency code " . $order_info['currency_code']);
                $json['error'] = "Invalid currency code " . $order_info['currency_code'];
            } else {
                $digiWallet = new DigiWalletCore($paymentType, $rtlo, "nl");
                $digiWallet->setAmount(round($order_info['total'] * 100));
                $digiWallet->setDescription("Order id: " . $this->session->data['order_id']);

                $this->setAdditionParameter($digiWallet, $order_info);

                $params = array(
                    'order_id' => $this->session->data['order_id'],
                    'payment_type' => $paymentType,
                    'customer_token' => (isset($this->session->data['customer_token']) ? $this->session->data['customer_token'] : ''),
                    'cookie_id' => $this->session->getId()
                );
                $digiWallet->setReturnUrl(html_entity_decode($this->url->link('extension/digiwallet/payment/tp_callback|returnurl', $params, true)));
                $digiWallet->setReportUrl(html_entity_decode($this->url->link('extension/digiwallet/payment/tp_callback|report', $params, true)));
                // Set consumer email
                if(!empty($consumer_email)) {
                    $digiWallet->bindParam('email', $consumer_email);
                }

                $bankUrl = $digiWallet->startPayment();

                if (! $bankUrl) {
                    $this->log->write('DigiWallet start payment failed: ' . $digiWallet->getErrorMessage());
                    $err = ($digiWallet->getErrorMessage());

                    $json['error'] = 'DigiWallet start payment failed: ' . $digiWallet->getErrorMessage();
                } else {
                    // For bankwire, after starting API, open the instruction page
                    if ($paymentType == 'BW') {
                        //Set order to visible on backend
                        $order_status_id = $this->config->get($setting_name . $setting_model_key . '_pending_status_id');
                        if (! $order_status_id) {
                            $order_status_id = 1;
                        } // Default to 'pending' after payment
                        $this->model_checkout_order->addHistory($this->session->data['order_id'], $order_status_id, $digiWallet->moreInformation);
                        // store order_id and moreInformation into session for instruction page
                        $this->session->data['bw_info'] = ['bw_data' => $digiWallet->moreInformation,'order_total' => $order_info['total'], 'customer_email' => $consumer_email];
                        $bankUrl = $this->url->link('extension/digiwallet/payment/A04Bankwire|bwintro');
                    }

                    $this->storeTxid($digiWallet->getPayMethod(), $digiWallet->getTransactionId(), $this->session->data['order_id']);
                    $json['success'] = $bankUrl;
                }
            }
        } catch (\Exception $exception) {
            $json['error'] = $exception->getMessage();
        }
        
        $this->response->setOutput(json_encode($json));
    }

    /**
     * Save txid/order_id pair in database
     */
    public function storeTxid($method, $txid, $order_id)
    {
        $sql = "INSERT INTO `" . DB_PREFIX . DigiWalletCore::DIGIWALLET_PREFIX . $this->paymentName . "` SET " . "`order_id`='" . $this->db->escape($order_id) . "', " . "`method`='" . $this->db->escape($method) . "', `" . $this->paymentName . "_txid`='" . $this->db->escape($txid) . "'";
        
        $this->db->query($sql);
    }

    /**
     *
     * @param unknown $country
     * @param unknown $phone
     * @return unknown
     * @throws Exception
     */
    private static function format_phone($country, $phone) {
        if(empty($country)) return $phone;
        $function = 'format_phone_' . strtolower($country);
        if(method_exists('DigiwalletFrontEnd', $function)) {
            return self::$function($phone);
        }
        return $phone;
    }
    
    /**
     * 
     * @param unknown $phone
     * @return string|mixed
     */
    private static function format_phone_nld($phone) {
        // note: making sure we have something
        $phone = preg_replace("/[^0-9]/", "", $phone);
        $length = strlen($phone);
        switch($length) {
            case 9:
                return "+31".$phone;
                break;
            case 10:
                return "+31".substr($phone, 1);
                break;
            case 11:
            case 12:
                return "+".$phone;
                break;
            default:
                return $phone;
                break;
        }
    }
    
    /**
     * 
     * @param unknown $phone
     * @return string|mixed
     */
    private static function format_phone_bel($phone) {
        // note: strip out everything but numbers
        $phone = preg_replace("/[^0-9]/", "", $phone);
        $length = strlen($phone);
        switch($length) {
            case 9:
                return "+32".$phone;
                break;
            case 10:
                return "+32".substr($phone, 1);
                break;
            case 11:
            case 12:
                return "+".$phone;
                break;
            default:
                return $phone;
                break;
        }
    }
    
    /**
     * 
     * @param unknown $street
     * @return NULL[]|string[]|unknown[]
     */
    private static function breakDownStreet($street)
    {
        $out = [];
        $addressResult = null;
        preg_match("/(?P<address>\D+) (?P<number>\d+) (?P<numberAdd>.*)/", $street, $addressResult);
        if(!$addressResult) {
            preg_match("/(?P<address>\D+) (?P<number>\d+)/", $street, $addressResult);
        }
        $out['street'] = array_key_exists('address', $addressResult) ? $addressResult['address'] : null;
        $out['houseNumber'] = array_key_exists('number', $addressResult) ? $addressResult['number'] : null;
        $out['houseNumberAdd'] = array_key_exists('numberAdd', $addressResult) ? trim(strtoupper($addressResult['numberAdd'])) : null;
        return $out;
    }

    /**
     *
     * @param unknown $payMethod
     * @param unknown $order
     * @param DigiWalletCore $digiWallet
     * @return bool
     */
    public function setAdditionParameter($digiWallet, $order = null)
    {
        if ($digiWallet->getPayMethod() == 'AFP') {
            $this->additionalParametersAFP($digiWallet, $order); // add addtitional params for afterpay and bankwire
        }
        if ($digiWallet->getPayMethod() == 'BW') {
            $this->additionalParametersBW($digiWallet, $order); // add addtitional params for afterpay and bankwire
        }
        return true;
    }

    /**
     * 
     * @param unknown $data
     * @return unknown
     */
    public function setListConfirm($data)
    {
        return $data;
    }

    /**
     * Get voucher info (If any)
     * @param string $field
     * @return mixed
     */
    protected function getOrderTotals($field = "") {
        $this->load->model('setting/extension');
        $results = $this->model_setting_extension->getExtensionsByType('total');
        $sort_order = array();
        $totals = array();
        $taxes = $this->cart->getTaxes();
        $total = 0;

        foreach ($results as $key => $value) {
            $sort_order[$key] = $this->config->get('total_' . $value['code'] . '_sort_order');
        }

        array_multisort($sort_order, SORT_ASC, $results);
        foreach ($results as $result) {
            if ($this->config->get('total_' . $result['code'] . '_status')) {
                $this->load->model('extension/digiwallet/total/' . $result['code']);
                // We have to put the totals in an array so that they pass by reference.
                $this->{'model_extension_opencart_total_' . $result['code']}->getTotal($totals, $taxes, $total);
            }
        }
        if(!empty($field)) {
            $field_total = 0;
            foreach ($totals as $key => $item) {
                if(isset($item['code']) && $item['code'] == $field) {
                    $field_total = $item['value'];
                }
            }
            return $field_total;
        }
        return $totals;
    }

    /**
     * 
     * @param unknown $order
     * @param DigiWalletCore $digiWallet
     */
    function additionalParametersAFP(DigiWalletCore $digiWallet, $order)
    {
        $coupon_discount = $this->getOrderTotals("coupon");
        $digiWallet->bindParam('coupon_discount', floatval($coupon_discount) * 100);
        // Supported countries are: Netherlands (NLD) and in Belgium (BEL)
        $streetParts = self::breakDownStreet($order['payment_address_1']);
        $digiWallet->bindParam('billingstreet', $streetParts['street']);
        $digiWallet->bindParam('billinghousenumber', trim($streetParts['houseNumber'] . ' ' . $streetParts['houseNumberAdd'] . (empty($order['payment_address_2']) ? '' : (' - ' . $order['payment_address_2']))));

        $digiWallet->bindParam('billingpostalcode', $order['payment_postcode']);
        $digiWallet->bindParam('billingcity', $order['payment_city']);
        $digiWallet->bindParam('billingpersonemail', $order['email']);
        $digiWallet->bindParam('billingpersoninitials', (!empty($order['payment_firstname'])) ? substr($order['payment_firstname'], 0, 1) : '');
        $digiWallet->bindParam('billingpersongender', "");
        $digiWallet->bindParam('billingpersonsurname', $order['payment_lastname']);
        $digiWallet->bindParam('billingpersonfirstname', $order['payment_firstname']);

        // var_dump($order);die;
        $digiWallet->bindParam('billingcountrycode', $order['payment_iso_code_3']);
        $digiWallet->bindParam('billingpersonlanguagecode', $order['payment_iso_code_3']);
        $digiWallet->bindParam('billingpersonbirthdate', "");
        $digiWallet->bindParam('billingpersonphonenumber', self::format_phone($order['payment_iso_code_3'], $order['telephone']));

        $streetParts = self::breakDownStreet($order['shipping_address_1']);
        $digiWallet->bindParam('shippingstreet', $streetParts['street']);
        $digiWallet->bindParam('shippinghousenumber', trim($streetParts['houseNumber'] . ' ' . $streetParts['houseNumberAdd'] . (empty($order['shipping_address_2']) ? '' : (' - ' . $order['shipping_address_2']))));

        $digiWallet->bindParam('shippingpostalcode', $order['shipping_postcode']);
        $digiWallet->bindParam('shippingcity', $order['shipping_city']);
        $digiWallet->bindParam('shippingpersonemail', $order['email']);
        $digiWallet->bindParam('shippingpersoninitials', (!empty($order['shipping_firstname'])) ? substr($order['shipping_firstname'], 0, 1) : '');
        $digiWallet->bindParam('shippingpersongender', "");
        $digiWallet->bindParam('shippingpersonsurname', $order['shipping_lastname']);
        $digiWallet->bindParam('shippingpersonfirstname', $order['shipping_firstname']);

        $digiWallet->bindParam('shippingcountrycode', $order['shipping_iso_code_3']);
        $digiWallet->bindParam('shippingpersonlanguagecode', $order['shipping_iso_code_3']);
        $digiWallet->bindParam('shippingpersonbirthdate', "");
        $digiWallet->bindParam('shippingpersonphonenumber', self::format_phone($order['shipping_iso_code_3'], $order['telephone']));

        // Getting the items in the order
        $invoicelines = [];
        $total_amount_by_products = 0;
        
        // Iterating through each item in the order
        foreach ($this->cart->getProducts() as $product) {
            $total_amount_by_products += $product['total'];
            $priceAfterTax = $this->tax->calculate($product['price'], $product['tax_class_id'], $this->config->get('config_tax'));
            $taxPercent = ($priceAfterTax / $product['price'] * 100) - 100;
            
            $invoicelines[] = [
                'productCode' => $product['product_id'],
                'productDescription' => $product['name'],
                'quantity' => $product['quantity'],
                'price' => (float)$product['total'],   //(Total, after quantity) price of this product in the order, excluding VAT (see below), decimal
                'taxCategory' => $digiWallet->getTax($taxPercent)
                
            ];
        }
        $difference_price = floatval($order['total']) - $total_amount_by_products;
        if (abs($difference_price) > 0) {
            $invoicelines[] = [
                'productCode' => '000000',
                'productDescription' => "Other fees (shipping, additional fees)",
                'quantity' => 1,
                'price' => $difference_price,
                'taxCategory' => 4
            ];
        }

        $digiWallet->bindParam('invoicelines', json_encode($invoicelines));
        $digiWallet->bindParam('userip', $_SERVER["REMOTE_ADDR"]);
    }

    /**
     *
     * @param unknown $order            
     * @param DigiWalletCore $digiWallet
     */
    function additionalParametersBW(DigiWalletCore $digiWallet, $order)
    {
        $digiWallet->bindParam('salt', $digiWallet->bwSalt);
        $digiWallet->bindParam('userip', $_SERVER["REMOTE_ADDR"]);
    }
}
