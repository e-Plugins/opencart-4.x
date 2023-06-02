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

class a03afterpay extends \DigiwalletFrontEnd
{
    public $paymentType = 'AFP';
    public $paymentName = \DigiWalletCore::METHOD_AFTERPAY;
    public $prefix_code = "a03";
}
