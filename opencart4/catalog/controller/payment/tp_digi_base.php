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
require_once(DIR_OPENCART . "extension/digiwallet/system/library/digiwallet.core.php");

class TPDigiBase extends \Opencart\System\Engine\Controller
{
    public function show_callback($message, $link, $link_label)
    {
        $data['column_left'] = $this->load->controller('common/column_left');
        $data['column_right'] = $this->load->controller('common/column_right');
        $data['content_top'] = $this->load->controller('common/content_top');
        $data['content_bottom'] = $this->load->controller('common/content_bottom');
        $data['footer'] = $this->load->controller('common/footer');
        $data['header'] = $this->load->controller('common/header');
        $data['digi_message'] = $message;
        $data['digi_link'] = $link;
        $data['digi_button'] = $link_label;

        $this->response->setOutput($this->load->view('extension/digiwallet/payment/digi_callback', $data));
    }
}