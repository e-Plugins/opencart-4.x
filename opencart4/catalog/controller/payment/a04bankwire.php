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
 * @release 2    June 2017
 */
require_once ("digiwallet.frontend.php");

class a04bankwire extends \DigiwalletFrontEnd
{

    public $paymentType = 'BW';

    public $paymentName = \DigiWalletCore::METHOD_BANKWIRE;

    public $prefix_code = "a04";

    /**
     * https://www.digiwallet.nl/documentation/bankwire
     * 
     * @return boolean
     */
    public function bwintro()
    {
        if (empty($this->session->data['bw_info'])) {
            $this->response->redirect($this->url->link('common/home', '', true));
        }
        $setting_model_key = ($this->prefix_code) . ($this->paymentName);

        $this->language->load('extension/digiwallet/payment/' . $setting_model_key);

        $data_bw = $this->session->data['bw_info'];
        list ($trxid, $accountNumber, $iban, $bic, $beneficiary, $bank) = explode("|", $data_bw['bw_data']);
        
        $data = [];
        $data['intro_thx'] = $this->language->get('intro_thx');
        $data['intro_l1'] = sprintf($this->language->get('intro_l1'), $this->currency->format($data_bw['order_total'], $this->session->data['currency']), $iban, $beneficiary);
        $data['intro_l2'] = sprintf($this->language->get('intro_l2'), $trxid, $data_bw['customer_email']);
        $data['intro_l3'] = sprintf($this->language->get('intro_l3'), $bic, $bank);

        $this->clearCart();
        $this->document->setTitle('Bankwire instruction');
        
        $data['breadcrumbs'][] = array('text' => $this->language->get('text_home'),'href' => $this->url->link('common/home'));
        $data['breadcrumbs'][] = array('text' => $this->language->get('Account'),'href' => $this->url->link('account/account', '', true));
        
        $data['footer'] = $this->load->controller('common/footer');
        $data['header'] = $this->load->controller('common/header');

        $this->response->setOutput($this->load->view('extension/digiwallet/payment/bwintro', $data));
    }
    
    public function clearCart()
    {
        if (isset($this->session->data['order_id'])) {
            $this->cart->clear();
        
            // Add to activity log
            if ($this->config->get('config_customer_activity')) {
                $this->load->model('account/activity');
        
                if ($this->customer->isLogged()) {
                    $activity_data = array(
                        'customer_id' => $this->customer->getId(),
                        'name'        => $this->customer->getFirstName() . ' ' . $this->customer->getLastName(),
                        'order_id'    => $this->session->data['order_id']
                    );
        
                    $this->model_account_activity->addActivity('order_account', $activity_data);
                } else {
                    $activity_data = array(
                        'name'     => $this->session->data['guest']['firstname'] . ' ' . $this->session->data['guest']['lastname'],
                        'order_id' => $this->session->data['order_id']
                    );
        
                    $this->model_account_activity->addActivity('order_guest', $activity_data);
                }
            }
        
            unset($this->session->data['shipping_method']);
            unset($this->session->data['shipping_methods']);
            unset($this->session->data['payment_method']);
            unset($this->session->data['payment_methods']);
            unset($this->session->data['guest']);
            unset($this->session->data['comment']);
            unset($this->session->data['order_id']);
            unset($this->session->data['coupon']);
            unset($this->session->data['reward']);
            unset($this->session->data['voucher']);
            unset($this->session->data['vouchers']);
            unset($this->session->data['totals']);
        }
    }
}
