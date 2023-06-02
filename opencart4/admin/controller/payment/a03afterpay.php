<?php
namespace Opencart\Admin\Controller\Extension\Digiwallet\Payment;
/**
 *
 * DigiWallet.nl
 * DigiWallet plugin for Opencart 2.0+
 *
 *  (C) Copyright TargetMedia B.V 2014
 *
 * @file        DigiWallet Admin Controller
 * @author      TargetMedia B.V / https://digiwallet.nl
 *
 */
require_once(DIR_OPENCART . "/extension/digiwallet/system/library/digiwallet.core.php");
require_once (DIR_OPENCART . "/extension/digiwallet/system/library/digiwallet.admin.php");

class A03Afterpay extends \DigiWalletAdmin
{
    protected $error = array();
    protected $type = \DigiWalletCore::METHOD_AFTERPAY;
    protected $methodName = \DigiWalletCore::METHOD_AFTERPAY;
    protected $prefix_code = "a03";
}
