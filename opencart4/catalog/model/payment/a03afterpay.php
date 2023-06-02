<?php
namespace Opencart\Catalog\Model\Extension\Digiwallet\Payment;

/**
 *
 *  DigiWallet.nl
 * DigiWallet plugin for Opencart 2.0+
 *
 *  (C) Copyright TargetMedia B.V 2014
 *
 *  @file     DigiWallet Catalog Model
 *  @author TargetMedia B.V / https://digiwallet.nl
 *
 */
require_once(DIR_OPENCART . "extension/digiwallet/system/library/digiwallet.core.php");
require_once ("digibase_model.php");

class A03Afterpay extends \BaseDigiWalletModel
{

    public $currencies = array('EUR');

    protected $prefix_code = "a03";

    public function getMethod($address, $total = -1)
    {
        return $this->getMethodModel($address, $total, 'afterpay', 'AFP');
    }
    public function getMethods($address)
    {
        return $this->getMethodModel($address, -1, 'afterpay', 'AFP');
    }
}
