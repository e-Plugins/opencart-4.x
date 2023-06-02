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
require_once ("digiwallet.frontend.php");
require_once(DIR_OPENCART . "/extension/digiwallet/system/library/client/ClientCore.php");

class DigiwalletClient extends DigiwalletFrontEnd
{
    /**
     * Start payment
     */
    public function send()
    {
        $paymentType = $this->paymentType;
        $setting_name = (OC_VERSION == 2) ? '' : 'payment_';
        $setting_model_key = ($this->prefix_code) . ($this->paymentName);

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
            $digiWallet = new \Digiwallet\ClientCore($rtlo, $paymentType, 'nl');
            $params = array(
                'order_id' => $this->session->data['order_id'],
                'payment_type' => $paymentType,
                'customer_token' => (isset($this->session->data['customer_token']) ? $this->session->data['customer_token'] : ''),
                'cookie_id' => $this->session->getId()
            );
            $amount = $order_info['total'];
            if(isset($order_info['currency_value']) && $order_info['currency_value'] > 0) {
                $amount = $amount * $order_info['currency_value'];
            }
            $formData = array(
                'amount' => round($amount * 100),
                'inputAmount' => round($amount * 100),
                'consumerEmail' => $consumer_email,
                'description' => "Order id: " . $this->session->data['order_id'],
                'returnUrl' => html_entity_decode($this->url->link('extension/digiwallet/payment/tp_client_callback|returnurl', $params, true)),
                'reportUrl' => html_entity_decode($this->url->link('extension/digiwallet/payment/tp_client_callback|report', $params, true)),
                'test' => 0
            );
            $apiToken = $this->config->get($setting_name . $setting_model_key . '_api_token');

            /** @var \Digiwallet\Packages\Transaction\Client\Response\CreateTransactionInterface $clientResult */
            $clientResult = $digiWallet->createTransaction($apiToken, $formData);

            if (empty($clientResult)) {
                $this->log->write('DigiWallet start payment failed: ' . $digiWallet->getErrorMessage());
                $err = ($digiWallet->getErrorMessage());
                $json['error'] = 'DigiWallet start payment failed: ' . $digiWallet->getErrorMessage();
            } else {
                $this->storeTxid($this->paymentName, $clientResult->transactionId(), $this->session->data['order_id']);
                $json['success'] = $clientResult->launchUrl();
            }
        }
        
        $this->response->setOutput(json_encode($json));
    }
}
