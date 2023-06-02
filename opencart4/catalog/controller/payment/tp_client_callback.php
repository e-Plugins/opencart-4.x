<?php
namespace Opencart\Catalog\Controller\Extension\Digiwallet\Payment;

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
require_once(DIR_OPENCART . "/extension/digiwallet/system/library/client/ClientCore.php");

require_once ("tp_digi_base.php");
define('OC_VERSION', substr(VERSION, 0, 1));

class TPClientCallback extends TPDigiBase
{

    private $message;

    private $errorMsg;

    public $method_mapping = array(
        "EPS" => "eps",
        "GIP" => "gip",
    );

    public $prefix_mapping = array(
        "IDE" => "a01",
        "MRC" => "a02",
        "DEB" => "a09",
        "WAL" => "a07",
        "CC" => "a10",
        "AFP" => "a03",
        "BW" => "a04",
        "PYP" => "a08",
        "GIP" => "a06",
        "EPS" => "a05",
    );

    /**
     * Handle payment result from report url
     * /index.php?route=extension/payment/tp_callback/report&payment_type=...&order_id=...
     * $_POST['trxid']
     * $_POST['payment_type']
     *
     * Handle report URL
     */
    public function report()
    {
        $payment_type = (!empty($this->request->get['payment_type'])) ? $this->request->get['payment_type'] : null;

        switch ($payment_type) {
            default:
                $trxid = (!empty($this->request->post["trxid"])) ? $this->request->post["trxid"] : null;
        }
        if(empty($trxid)) {
            $trxid = (!empty($this->request->post["transactionID"])) ? $this->request->post["transactionID"] : null;
        }

        $order_id = (!empty($this->request->get["order_id"])) ? $this->request->get["order_id"] : null;
        
        if (empty($order_id) || empty($payment_type) || empty($trxid)) {
            $this->log->write('DigiWallet tp_callback(), Invalid request');
            exit("Invalid request");
        }

        if (! $this->execReport($order_id, $trxid, $payment_type)) {
            echo $this->errorMsg;
        }
        echo $this->message;
        exit('done');
    }

    /**
     * /index.php?route=extension/payment/tp_callback/returnurl&payment_type=...&order_id=...&trxid=...
     */
    public function returnurl()
    {
        $customer_token = (!empty($this->request->get['customer_token'])) ? $this->request->get['customer_token'] : null;
        $cookie_id = (!empty($this->request->get['cookie_id'])) ? $this->request->get['cookie_id'] : null;
        $payment_type = (!empty($this->request->get['payment_type'])) ? $this->request->get['payment_type'] : null;
        switch ($payment_type) {
            default:
                $trxid = (!empty($this->request->get["trxid"])) ? $this->request->get["trxid"] : null;
        }
        if(empty($trxid)) {
            $trxid = (!empty($this->request->get["transactionID"])) ? $this->request->get["transactionID"] : null;
        }
        $order_id = (!empty($this->request->get["order_id"])) ? $this->request->get["order_id"] : null;
        
        if (empty($order_id) || empty($payment_type) || empty($trxid)) {
            $this->log->write('DigiWallet tp_callback(), Invalid request');
            exit("Invalid request");
        }
        $option = [
            'expires'  => time() + (int)$this->config->get('config_session_expire'),
            'path'     => $this->config->get('session_path'),
            'secure'   => $this->request->server['HTTPS'],
            'httponly' => false,
            'SameSite' => $this->config->get('config_session_samesite')
        ];
        $this->response->addHeader('Cache-Control: no-store, no-cache, must-revalidate, post-check=0, pre-check=0');
        setcookie($this->config->get('session_name'), $cookie_id, $option);
        header('Set-Cookie: ' . $this->config->get('session_name') . '=' . $this->session->getId() . '; SameSite=None; Secure');

        $params = array('customer_token' => $customer_token, 'language' => $this->config->get('config_language'));
        if ($this->execReport($order_id, $trxid, $payment_type)) {
            $url = str_replace(['&amp;', "\n", "\r"], ['&', '', ''], $this->url->link('checkout/success', $params));
            echo "<div><script>window.location.href = '{$url}';</script></div>";
        } else {
            $this->log->write($this->errorMsg);
            $this->show_callback($this->errorMsg, $this->url->link('checkout/checkout', $params), "Try again");
        }
    }

    /**
     *
     */
    public function execReport($order_id, $trxid, $payment_type)
    {
	    $setting_name = (OC_VERSION == 2) ? '' : 'payment_';
        // Array mapping
        $var_type = $this->method_mapping[$payment_type]; // output: ideal
        $prefix_code = $this->prefix_mapping[$payment_type];
        $conf_var_type = $setting_name . ($prefix_code) . ($this->method_mapping[$payment_type]); // output: payment_ideal

        if ($this->isOcDigiWallet($order_id, $trxid, $var_type) == true) {
            $this->message = 'Already paid';
            return true;
        }
        
        $this->load->model('checkout/order');

        $rtlo = ($this->config->get($conf_var_type . '_rtlo')) ? $this->config->get($conf_var_type . '_rtlo') : \DigiWalletCore::DEFAULT_RTLO; // Default DigiWallet

        $apiToken = $this->config->get($conf_var_type . '_api_token');
        $digiWallet = new \Digiwallet\ClientCore($rtlo, $payment_type, 'nl');
        $checkStatus = $digiWallet->checkTransaction($apiToken, $trxid);

        if ($checkStatus) {
            $this->updateOcDigiWallet($trxid, $var_type);

            $orderComment = '';
            $order_status_id = $this->config->get($conf_var_type . '_pending_status_id');
            if (! $order_status_id) {
                $order_status_id = 1;
            }
            $this->model_checkout_order->addHistory($order_id, $order_status_id, $orderComment);
            $this->message = "Paid... order_id = $order_id, trxid = $trxid, payment_type = $payment_type order_comment = $orderComment\n";
            return true;
        } else {
            $this->errorMsg = "Not paid: " . $digiWallet->getErrorMessage() . "... ";
            $this->log->write($this->errorMsg);
            
            return false;
        }
    }

    /**
     * Check if transaction has paid in oc_digiwallet* tables
     *
     * @param unknown $order_id            
     * @param unknown $txid            
     * @param unknown $var_type            
     * @return boolean
     */
    public function isOcDigiWallet($order_id, $txid, $var_type)
    {
        $sql = "SELECT count(*) as total FROM `" . DB_PREFIX . \DigiWalletCore::DIGIWALLET_PREFIX . $var_type . "` WHERE `order_id`='" . $this->db->escape($order_id) . "' AND `" . $var_type . "_txid`='" . $this->db->escape($txid) . "' AND `paid` is null LIMIT 1";
        $result = $this->db->query($sql);
        
        return $result->rows[0]['total'] > 0 ? false : true;
    }

    /**
     * Update paid status based on txid in database
     */
    public function updateOcDigiWallet($trxid, $var_type)
    {
        $sql = "UPDATE `" . DB_PREFIX . \DigiWalletCore::DIGIWALLET_PREFIX . $var_type . "` SET `paid`=now() WHERE `" . $var_type . "_txid`='" . $trxid . "'";
        
        $this->db->query($sql);
    }
}
