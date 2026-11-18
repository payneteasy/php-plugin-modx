<?php

// Shows the 3-D Secure page of a direct payment. Runs inside MODX and only
// for the order the signed link was issued for.
const MODX_API_MODE = true;

require dirname(__DIR__) . '/index.php';

/** @var modX $modx */
$modx->getService('error', 'error.modError');
$miniShop2 = $modx->getService('minishop2');
$miniShop2->loadCustomClasses('payment');

$orderId = (string) ($_GET['orderId'] ?? '');
$token = (string) ($_GET['token'] ?? '');

$handler = new PaynetEasy($modx->newObject(msPayment::class));

if (!ctype_digit($orderId) || !hash_equals($handler->directPageToken($orderId), $token)) {
    http_response_code(404);
    exit;
}

$paymentStatus = $handler->getPaymentStatus($orderId);

// The 3-D Secure form comes from the gateway as HTML.
if (isset($paymentStatus['html'])) {
    echo $paymentStatus['html'];
} else {
    echo htmlspecialchars($paymentStatus['error-message'] ?? 'Payment error', ENT_QUOTES, 'UTF-8');
}
