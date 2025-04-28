<?php

// Constant declaration
const MODX_API_MODE = true;

// Require main project file
require dirname(__FILE__, 5) . '/index.php';

/**
 * @var modX $modx
 *
 * The ModX service, responsible for error handling and logging.
 */
$modx->getService('error', 'error.modError');

/**
 * @var miniShop2 $miniShop2
 *
 * The miniShop2 service, responsible for payment classes loading.
 */
$miniShop2 = $modx->getService('minishop2');
$miniShop2->loadCustomClasses('payment');

/**
 * @var msPaymentInterface|PaynetEasy $handler
 *
 * Initialize the payment handler.
 */
$handler = new PaynetEasy($modx->newObject(msPayment::class));

// Получаем orderId и логируем данные запроса
$orderId = '';
$mode = $_REQUEST['mode'];
// Logging request data
if (in_array($mode, ['return', 'webhook'])) {
    $handler->logger->setOption('additionalCommonText', $mode . '-' . rand(1111, 9999));
    $requestData = ($mode === 'return') ? $_REQUEST : json_decode(file_get_contents('php://input'), true);
    $handler->logger->debug(
        $mode . ': ',
        ['request_data' => $requestData]
    );

    $orderId = $_REQUEST['client_orderid']??$requestData['client_orderid'];
}

try {
    // Check if orderId is set and not empty
    if (empty($orderId)) {
        throw new \Exception('Order ID is empty.');
    }

    /**
     * @var msOrder $order
     *
     * Retrieve the order by id from the modX object.
     */
    $order = $modx->getObject(msOrder::class, ['id' => (int)$orderId]);

    // If order exists
    if (empty($order)) {
        // Log the error if order could not be retrieved
        throw new \Exception('Order not found.');
    }

    $receive = $handler->receive($order);

    // Если запрос имеет тип webhook, то не требуется редирект,
    // поэтому останавливаем выполнение
    if ($mode === 'webhook') die($receive ? 'ok' : 'error');

    // Set parameters and context
    $params['msorder'] = (int)$orderId;
    $orderContext = $order->get('context');

    // Set default success and failure URLs
    $success = $failure = $modx->getOption('site_url');

    // If success page id is set, make URL for it
    if ($id = $modx->getOption('ms2_payment_payneteasy_success_id', null, 0)) {
        $success = $modx->makeUrl($id, $orderContext, $params, 'full');
    }
    // If failure page id is set, make URL for it
    if ($id = $modx->getOption('ms2_payment_payneteasy_failure_id', null, 0)) {
        $failure = $modx->makeUrl($id, $orderContext, $params, 'full');
    }

    // Redirect to the determined URL
    $modx->sendRedirect($receive ? $success : $failure);

} catch (\Exception $e) {

    // Handle exception and log error
    $handler->logger->error(sprintf(
        'Request return page > PaynetEasy Exception: %s; Order id: %s',
        $e->getMessage(),
        $orderId ?: ''
    ));

    if ($mode === 'webhook') die('error');
    die($e->getMessage() . ' <a href="/">Go Home</a>');
}