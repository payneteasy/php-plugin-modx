<?php

require_once(realpath($_SERVER["DOCUMENT_ROOT"]) .'/core/components/minishop2/custom/payment/payneteasy.class.php');
require_once realpath($_SERVER["DOCUMENT_ROOT"]).'/config.core.php';
require_once realpath($_SERVER["DOCUMENT_ROOT"]).'/core/model/modx/modx.class.php';
require_once realpath($_SERVER["DOCUMENT_ROOT"]).'/core/xpdo/xpdo.class.php';
require_once realpath($_SERVER["DOCUMENT_ROOT"]).'/core/xpdo/om/xpdoobject.class.php';

use \Payneteasy\Classes\Api\PaynetApi,
    \Payneteasy\Classes\Common\PaynetEasyLogger,
    \Payneteasy\Classes\Exception\PaynetEasyException;

$dsn = 'mysql:host=localhost;dbname=;port=3306;charset=utf-8';
$xpdo = new xPDO($dsn, $username= '', $password= '', $options= array(), $driverOptions= null);
$xpdoobject = new xPDOObject($xpdo);
$modx = new modX;
$config = $modx->getConfig();
$PaynetEasy = new PaynetEasy($xpdoobject,$config);


$orderId = $_GET['orderId'];
$paymentStatus = $PaynetEasy->getPaymentStatus($orderId);

if (isset($paymentStatus['html']))
echo $paymentStatus['html'];
else
    echo $paymentStatus['error-message'];
