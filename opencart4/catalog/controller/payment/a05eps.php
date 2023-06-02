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
require_once ("digiwallet.client.php");

class a05eps extends \DigiwalletClient
{
    public $paymentType = 'EPS';
    public $paymentName = 'eps';
    public $prefix_code = "a05";
}
