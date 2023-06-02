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
require_once ("digiwallet.frontend.php");

class a01ideal extends \DigiwalletFrontEnd
{

    public $paymentType = 'IDE';
    public $paymentName = \DigiWalletCore::METHOD_IDEAL;
    public $prefix_code = "a01";
    
    public function setAdditionParameter($digiWallet, $order = null)
    {
        if (! empty($this->request->post['bank_id'])) {
            $digiWallet->setBankId($this->request->post['bank_id']);
        }
        return true;
    }
    
    public function setListConfirm($data)
    {
        $targetCore = new \DigiWalletCore($this->paymentType);
        $data['custom'] = $this->session->data['order_id'];
        $data['banks'] = $targetCore->getBankList();
        return $data;
    }
}
