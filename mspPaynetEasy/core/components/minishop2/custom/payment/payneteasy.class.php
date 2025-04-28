<?php

$newBasePaymentHandler = dirname(__FILE__, 3) . '/handlers/mspaymenthandler.class.php';
$oldBasePaymentHandler = dirname(__FILE__, 3) . '/model/minishop2/mspaymenthandler.class.php';

if (!class_exists('msPaymentInterface')) {
    if (file_exists($newBasePaymentHandler)) {
        require_once $newBasePaymentHandler;
    } else {
        require_once $oldBasePaymentHandler;
    }
}

require_once realpath($_SERVER["DOCUMENT_ROOT"]) . '/mspPaynetEasy/vendor/autoload.php';

use \Payneteasy\Classes\Api\PaynetApi,
    \Payneteasy\Classes\Common\PaynetEasyLogger,
    \Payneteasy\Classes\Exception\PaynetEasyException;

class PaynetEasy extends msPaymentHandler implements msPaymentInterface
{

    private msOrder $order;
    public PaynetEasyLogger $logger;

    /**
     * PaynetEasy constructor.
     *
     * This method initiates the PaynetEasy object by merging the default configuration with the user defined configurations.
     *
     * @param xPDOObject $object The xPDOObject from the modx system.
     * @param array $config User defined configuration array.
     */
    public function __construct(xPDOObject $object, $config = [])
    {
        parent::__construct($object, $config);

        $resultConfig  = [];
        $settings = [
            'live_url',
            'sandbox_url',
            'login',
            'control_key',
            'endpoint_id',
            'payment_method',
            'sandbox',
            'logging',
            'three_d_secure',
            'default_country',
            'success_id',
            'failure_id'
        ];

        foreach ($settings as $setting) {
            $resultConfig[$setting] = $this->modx->getOption('ms2_payment_payneteasy_' . $setting);
        }

        $this->config = array_merge($resultConfig, $config);

        $this->setPayneteasyLogger();
    }


    /**
     * Send method.
     *
     * This method is used to create a new order and send it to the payment gateway.
     * It then logs the response and returns the payment URL to the user.
     *
     * @param msOrder $order An instance of the order that needs to be sent to the gateway.
     * @return array|string Either the URL of the payment gateway or an error message.
     */
    public function send(msOrder $order)
    {
        $this->order = $order;

        $this->logger->setOption('additionalCommonText', 'payment-' . rand(1111, 9999));

        try {
            $statusNew = $this->modx->getOption('ms2_status_new', null, 1) ?: 1;
            if ($this->order->get('status') > $statusNew) {
                throw new \Exception($this->modx->lexicon('ms2_err_status_wrong'));
            }

            $siteUrl = $this->modx->getOption('site_url');
            $assetsUrl = $this->modx->getOption('assets_url') . 'components/minishop2/';
            $returnUrl = $siteUrl . substr($assetsUrl, 1) . 'payment/payneteasy.php?mode=return';

            if (empty($payUrl = $this->getPayUrl())) {
                throw new \Exception($this->modx->lexicon('ms2_payment_payneteasy_url_fetch_error'));
            }

            $paymentStatus = $this->getPaymentStatus($this->order->get('id'));

            if (trim($paymentStatus['status']) == 'processing') {
                if ($this->config['payment_method'] == 'form') {
                    $this->setOrderProperty('payment_link', $payUrl['redirect-url']);
                    return $this->success('', [
                        'redirect' => $payUrl['redirect-url'],
                        'msorder' => $this->order->get('id')
                    ]);
                }
                elseif ($this->config['payment_method'] == 'direct') {
                    if ($this->config['three_d_secure']) {
                        return $this->success('', [
                            'redirect' => '/mspPaynetEasy/direct.php?orderId='.$this->order->get('id'),
                            'msorder' => $this->order->get('id')
                        ]);
                    }
                }
            }
        } catch (\Exception | PaynetEasyException $e) {
            // Handle exception and log error
            $context = [
                'file_exception' => $e->getFile(),
                'line_exception' => $e->getLine(),
            ];
            if (method_exists($e, 'getContext')) $context = array_merge($e->getContext(), $context);

            // Handle exception and log error
            $this->logger->error(sprintf(
                __FUNCTION__ . ' > PaynetEasy exception : %s; Order id: %s;',
                $e->getMessage(),
                $this->order->get('id') ?: ''
            ), $context);

            // Set parameters and context
            $params['msorder'] = (int) $this->order->get('id');

            // Get failure url page
            if ($id = $this->modx->getOption('ms2_payment_payneteasy_failure_id', null, 0)) {
                $failure = $this->modx->makeUrl(
                    $id,
                    $this->order->get('context'),
                    $params,
                    'full'
                );
            }

            return $this->error($e->getMessage(), [
                'redirect' => $failure
            ]);
        }
    }

    private function getCountries(): array
    {
        $countries = [
            ['AF', 'Afghanistan'],
            ['AX', 'Aland Islands'],
            ['AL', 'Albania'],
            ['DZ', 'Algeria'],
            ['AS', 'American Samoa'],
            ['AD', 'Andorra'],
            ['AO', 'Angola'],
            ['AI', 'Anguilla'],
            ['AQ', 'Antarctica'],
            ['AG', 'Antigua And Barbuda'],
            ['AR', 'Argentina'],
            ['AM', 'Armenia'],
            ['AW', 'Aruba'],
            ['AU', 'Australia'],
            ['AT', 'Austria'],
            ['AZ', 'Azerbaijan'],
            ['BS', 'Bahamas'],
            ['BH', 'Bahrain'],
            ['BD', 'Bangladesh'],
            ['BB', 'Barbados'],
            ['BY', 'Belarus'],
            ['BE', 'Belgium'],
            ['BZ', 'Belize'],
            ['BJ', 'Benin'],
            ['BM', 'Bermuda'],
            ['BT', 'Bhutan'],
            ['BO', 'Bolivia'],
            ['BA', 'Bosnia And Herzegovina'],
            ['BW', 'Botswana'],
            ['BV', 'Bouvet Island'],
            ['BR', 'Brazil'],
            ['IO', 'British Indian Ocean Territory'],
            ['BN', 'Brunei Darussalam'],
            ['BG', 'Bulgaria'],
            ['BF', 'Burkina Faso'],
            ['BI', 'Burundi'],
            ['KH', 'Cambodia'],
            ['CM', 'Cameroon'],
            ['CA', 'Canada'],
            ['CV', 'Cape Verde'],
            ['KY', 'Cayman Islands'],
            ['CF', 'Central African Republic'],
            ['TD', 'Chad'],
            ['CL', 'Chile'],
            ['CN', 'China'],
            ['CX', 'Christmas Island'],
            ['CC', 'Cocos (Keeling) Islands'],
            ['CO', 'Colombia'],
            ['KM', 'Comoros'],
            ['CG', 'Congo'],
            ['CD', 'Congo, Democratic Republic'],
            ['CK', 'Cook Islands'],
            ['CR', 'Costa Rica'],
            ['CI', 'Cote DIvoire'],
            ['HR', 'Croatia'],
            ['CU', 'Cuba'],
            ['CY', 'Cyprus'],
            ['CZ', 'Czech Republic'],
            ['DK', 'Denmark'],
            ['DJ', 'Djibouti'],
            ['DM', 'Dominica'],
            ['DO', 'Dominican Republic'],
            ['EC', 'Ecuador'],
            ['EG', 'Egypt'],
            ['SV', 'El Salvador'],
            ['GQ', 'Equatorial Guinea'],
            ['ER', 'Eritrea'],
            ['EE', 'Estonia'],
            ['ET', 'Ethiopia'],
            ['FK', 'Falkland Islands (Malvinas)'],
            ['FO', 'Faroe Islands'],
            ['FJ', 'Fiji'],
            ['FI', 'Finland'],
            ['FR', 'France'],
            ['GF', 'French Guiana'],
            ['PF', 'French Polynesia'],
            ['TF', 'French Southern Territories'],
            ['GA', 'Gabon'],
            ['GM', 'Gambia'],
            ['GE', 'Georgia'],
            ['DE', 'Germany'],
            ['GH', 'Ghana'],
            ['GI', 'Gibraltar'],
            ['GR', 'Greece'],
            ['GL', 'Greenland'],
            ['GD', 'Grenada'],
            ['GP', 'Guadeloupe'],
            ['GU', 'Guam'],
            ['GT', 'Guatemala'],
            ['GG', 'Guernsey'],
            ['GN', 'Guinea'],
            ['GW', 'Guinea-Bissau'],
            ['GY', 'Guyana'],
            ['HT', 'Haiti'],
            ['HM', 'Heard Island & Mcdonald Islands'],
            ['VA', 'Holy See (Vatican City State)'],
            ['HN', 'Honduras'],
            ['HK', 'Hong Kong'],
            ['HU', 'Hungary'],
            ['IS', 'Iceland'],
            ['IN', 'India'],
            ['ID', 'Indonesia'],
            ['IR', 'Iran, Islamic Republic Of'],
            ['IQ', 'Iraq'],
            ['IE', 'Ireland'],
            ['IM', 'Isle Of Man'],
            ['IL', 'Israel'],
            ['IT', 'Italy'],
            ['JM', 'Jamaica'],
            ['JP', 'Japan'],
            ['JE', 'Jersey'],
            ['JO', 'Jordan'],
            ['KZ', 'Kazakhstan'],
            ['KE', 'Kenya'],
            ['KI', 'Kiribati'],
            ['KR', 'Korea'],
            ['KW', 'Kuwait'],
            ['KG', 'Kyrgyzstan'],
            ['LA', 'Lao Peoples Democratic Republic'],
            ['LV', 'Latvia'],
            ['LB', 'Lebanon'],
            ['LS', 'Lesotho'],
            ['LR', 'Liberia'],
            ['LY', 'Libyan Arab Jamahiriya'],
            ['LI', 'Liechtenstein'],
            ['LT', 'Lithuania'],
            ['LU', 'Luxembourg'],
            ['MO', 'Macao'],
            ['MK', 'Macedonia'],
            ['MG', 'Madagascar'],
            ['MW', 'Malawi'],
            ['MY', 'Malaysia'],
            ['MV', 'Maldives'],
            ['ML', 'Mali'],
            ['MT', 'Malta'],
            ['MH', 'Marshall Islands'],
            ['MQ', 'Martinique'],
            ['MR', 'Mauritania'],
            ['MU', 'Mauritius'],
            ['YT', 'Mayotte'],
            ['MX', 'Mexico'],
            ['FM', 'Micronesia, Federated States Of'],
            ['MD', 'Moldova'],
            ['MC', 'Monaco'],
            ['MN', 'Mongolia'],
            ['ME', 'Montenegro'],
            ['MS', 'Montserrat'],
            ['MA', 'Morocco'],
            ['MZ', 'Mozambique'],
            ['MM', 'Myanmar'],
            ['NA', 'Namibia'],
            ['NR', 'Nauru'],
            ['NP', 'Nepal'],
            ['NL', 'Netherlands'],
            ['AN', 'Netherlands Antilles'],
            ['NC', 'New Caledonia'],
            ['NZ', 'New Zealand'],
            ['NI', 'Nicaragua'],
            ['NE', 'Niger'],
            ['NG', 'Nigeria'],
            ['NU', 'Niue'],
            ['NF', 'Norfolk Island'],
            ['MP', 'Northern Mariana Islands'],
            ['NO', 'Norway'],
            ['OM', 'Oman'],
            ['PK', 'Pakistan'],
            ['PW', 'Palau'],
            ['PS', 'Palestinian Territory, Occupied'],
            ['PA', 'Panama'],
            ['PG', 'Papua New Guinea'],
            ['PY', 'Paraguay'],
            ['PE', 'Peru'],
            ['PH', 'Philippines'],
            ['PN', 'Pitcairn'],
            ['PL', 'Poland'],
            ['PT', 'Portugal'],
            ['PR', 'Puerto Rico'],
            ['QA', 'Qatar'],
            ['RE', 'Reunion'],
            ['RO', 'Romania'],
            ['RU', 'Russian Federation'],
            ['RU', 'Russia'],
            ['RW', 'Rwanda'],
            ['BL', 'Saint Barthelemy'],
            ['SH', 'Saint Helena'],
            ['KN', 'Saint Kitts And Nevis'],
            ['LC', 'Saint Lucia'],
            ['MF', 'Saint Martin'],
            ['PM', 'Saint Pierre And Miquelon'],
            ['VC', 'Saint Vincent And Grenadines'],
            ['WS', 'Samoa'],
            ['SM', 'San Marino'],
            ['ST', 'Sao Tome And Principe'],
            ['SA', 'Saudi Arabia'],
            ['SN', 'Senegal'],
            ['RS', 'Serbia'],
            ['SC', 'Seychelles'],
            ['SL', 'Sierra Leone'],
            ['SG', 'Singapore'],
            ['SK', 'Slovakia'],
            ['SI', 'Slovenia'],
            ['SB', 'Solomon Islands'],
            ['SO', 'Somalia'],
            ['ZA', 'South Africa'],
            ['GS', 'South Georgia And Sandwich Isl.'],
            ['ES', 'Spain'],
            ['LK', 'Sri Lanka'],
            ['SD', 'Sudan'],
            ['SR', 'Suriname'],
            ['SJ', 'Svalbard And Jan Mayen'],
            ['SZ', 'Swaziland'],
            ['SE', 'Sweden'],
            ['CH', 'Switzerland'],
            ['SY', 'Syrian Arab Republic'],
            ['TW', 'Taiwan'],
            ['TJ', 'Tajikistan'],
            ['TZ', 'Tanzania'],
            ['TH', 'Thailand'],
            ['TL', 'Timor-Leste'],
            ['TG', 'Togo'],
            ['TK', 'Tokelau'],
            ['TO', 'Tonga'],
            ['TT', 'Trinidad And Tobago'],
            ['TN', 'Tunisia'],
            ['TR', 'Turkey'],
            ['TM', 'Turkmenistan'],
            ['TC', 'Turks And Caicos Islands'],
            ['TV', 'Tuvalu'],
            ['UG', 'Uganda'],
            ['UA', 'Ukraine'],
            ['AE', 'United Arab Emirates'],
            ['GB', 'United Kingdom'],
            ['US', 'United States'],
            ['UM', 'United States Outlying Islands'],
            ['UY', 'Uruguay'],
            ['UZ', 'Uzbekistan'],
            ['VU', 'Vanuatu'],
            ['VE', 'Venezuela'],
            ['VN', 'Viet Nam'],
            ['VG', 'Virgin Islands, British'],
            ['VI', 'Virgin Islands, U.S.'],
            ['WF', 'Wallis And Futuna'],
            ['EH', 'Western Sahara'],
            ['YE', 'Yemen'],
            ['ZM', 'Zambia'],
            ['ZW', 'Zimbabwe']
        ];
        return $countries;
    }


    /**
     * Получает URL-адрес платежа для перенаправления.
     *
     * @return string URL-адрес платежа.
     */
    private function getPayUrl(): array
    {
        $profile = $this->order->getOne('UserProfile');
        $contacts = $this->order->Address;
        $customerEmail = $profile->get('email')?:$contacts->email;
        $profilePhone = $profile->get('phone')?:$profile->get('mobilephone');
        $customerPhone = $profilePhone?:$contacts->phone;
        $customerFullName = $profile->get('fullname')?:$contacts->receiver;
        $customerFullName = explode(' ', $customerFullName);
        $customerFirstname = $customerFullName[0];
        $customerLastname = $customerFullName[1];
        $customerAddress1 = $contacts->street . ' ' . $contacts->building . ', ' . $contacts->room;
        $customerCity = $profile->get('city')?:$contacts->city;
        $customerZip = $profile->get('zip')?:$contacts->index;
        $customerCountry = $contacts->country??$profile->get('country');
        $cost = str_replace(',', '.', $this->order->get('cost'));

        $countries = $this->getCountries();

        foreach ($countries as $country) {
            if (isset($customerCountry) && mb_strtolower($customerCountry) == mb_strtolower($country[1])) {
                $country_iso = $country[0];
            }
        }

        // Get return url
        $siteUrl = $this->modx->getOption('site_url');
        $assetsUrl = $this->modx->getOption('assets_url') . 'components/minishop2/';
        $returnUrl = $siteUrl . substr($assetsUrl, 1) . 'payment/payneteasy.php?mode=return';

        $payneteasy_card_number       = $_POST['credit_card_number']??'';
        $payneteasy_card_expiry_month = $_POST['expire_month']??'';
        $payneteasy_card_expiry_year  = $_POST['expire_year']??'';
        $payneteasy_card_name         = $_POST['card_printed_name']??'';
        $payneteasy_card_cvv          = $_POST['cvv2']??'';

        $card_data = [
            'credit_card_number' => $payneteasy_card_number??'',
            'card_printed_name' => $payneteasy_card_name??'',
            'expire_month' => $payneteasy_card_expiry_month??'',
            'expire_year' => $payneteasy_card_expiry_year??'',
            'cvv2' => $payneteasy_card_cvv??'',
        ];

        $data = [
            'client_orderid' => (string)$this->order->get('id'),
            'order_desc' => 'Order # ' . $this->order->get('id'),
            'amount' => $cost,
            'currency' => $this->modx->lexicon('ms2_frontend_currency')?:'eur',
            'address1' => $customerAddress1?:$profile->get('address'),
            'city' => $customerCity,
            'zip_code' => $customerZip,
            'country' => $country_iso??$this->config['default_country'],
            'phone'      => $customerPhone,
            'email'      => $customerEmail,
            'ipaddress' => $_SERVER['REMOTE_ADDR'],
            'cvv2' => $card_data['cvv2'],
            'credit_card_number' => $card_data['credit_card_number'],
            'card_printed_name' => $card_data['card_printed_name'],
            'expire_month' => $card_data['expire_month'],
            'expire_year' => $card_data['expire_year'],
            'first_name' => $customerFirstname,
            'last_name'  => $customerLastname,
            'redirect_success_url' => $returnUrl,
            'redirect_fail_url' => $returnUrl,
            'redirect_url' => $returnUrl,
            'server_callback_url' => $returnUrl,
        ];
        $data['control'] = $this->signPaymentRequest($data, $this->config['endpoint_id'], $this->config['control_key']);

        // Logging input
        $this->logger->debug(
            __FUNCTION__ . ' > getOrderLink - INPUT: ', [
            'arguments' => [
                'orderId' => $this->order->get('id'),
                'customerEmail' => $customerEmail,
                'time' => time(),
                'cost' => $cost,
                'returnUrl' => $returnUrl
            ]
        ]);

        $action_url = $this->config['live_url'];
        if ($this->config['sandbox'])
            $action_url = $this->config['sandbox_url'];


        if ($this->config['payment_method'] == 'form') {
            $response = $this->getPaynetApi()->saleForm(
                $data,
                $this->config['payment_method'],
                $this->config['sandbox'],
                $action_url,
                $this->config['endpoint_id']
            );
        } elseif ($this->config['payment_method'] == 'direct') {
            $response = $this->getPaynetApi()->saleDirect(
                $data,
                $this->config['payment_method'],
                $this->config['sandbox'],
                $action_url,
                $this->config['endpoint_id']
            );
        }

        // Logging output
        $this->logger->debug(
            __FUNCTION__ . ' > getOrderLink - OUTPUT: ', [
            'response' => $response
        ]);

        $sql = "INSERT INTO payneteasy_payments (paynet_order_id, merchant_order_id) VALUES (:paynet_order_id, :merchant_order_id)";
        $this->modx->prepare($sql)->execute(['paynet_order_id' => $response['paynet-order-id'], 'merchant_order_id' => $response['merchant-order-id']]);

        return $response;
    }


    /**
     * Get order property.
     *
     * This method is used to retrieve a specific property of an order.
     *
     * @param msOrder    $order The order for which the property needs to be fetched.
     * @param string     $name Name of the property to be fetched.
     * @param mixed|null $default The default value to return if the property doesn't exist.
     * @return mixed|null The property value if found, else the default value.
     */
    private function getOrderProperty(msOrder $order, $name, $default = null)
    {
        $props = $order->get('properties');

        return isset($props['payments']['payneteasy'][$name]) ? $props['payments']['payneteasy'][$name] : $default;
    }


    /**
     * Set order property.
     *
     * This method is used to set a property for an order.
     * It handles both single and multiple property setting.
     *
     * @param string|array $name The name of the property or an array of properties to be set.
     * @param mixed|null   $value The value to be set for the property.
     */
    private function setOrderProperty($name, $value = null)
    {
        $newProperties = [];
        if (is_array($name)) {
            $newProperties = $name;
        } else {
            $newProperties[$name] = $value;
        }

        $orderProperties = $this->order->get('properties');

        if (isset($orderProperties['payments']['payneteasy'])) {
            $orderProperties['payments']['payneteasy'] = array_merge(
                $orderProperties['payments']['payneteasy'],
                $newProperties
            );
        }
        else {
            if (!is_array($orderProperties)) {
                $orderProperties = [];
            }
            $orderProperties['payments']['payneteasy'] = $newProperties;
        }

        $this->order->set('properties', $orderProperties);
        $this->order->save();
    }


    /**
     * Get payment link.
     *
     * This method is used to retrieve the payment link for an order.
     * Returns a direct link for continue payment process of existing order
     *
     * @param msOrder $order The order for which the payment link needs to be fetched.
     * @return string The URL of the payment gateway.
     */
    public function getPaymentLink(msOrder $order)
    {
        return $this->getOrderProperty($order, 'payment_link');
    }


    /**
     * Receive method.
     *
     * This method is used to receive the status of a payment from the payment gateway.
     * Depending on the response, it changes the status of the order accordingly.
     *
     * @param msOrder $order The order for which the payment status needs to be fetched.
     * @param array $params Any additional parameters needed.
     *
     * @return bool True if the payment status is 'PAID', False otherwise.
     */
    public function receive(msOrder $order)
    {
        $this->order = $order;

        try {
            $paymentStatus = $this->getPaymentStatus($this->order->get('id'));
            $this->changePaymentStatus(trim($paymentStatus['status']));

            if (trim($paymentStatus['status']) == 'approved') return true;

        } catch (\Exception | PaynetEasyException $e) {
            // Handle exception and log error
            $context = [
                'file_exception' => $e->getFile(),
                'line_exception' => $e->getLine(),
            ];
            if (method_exists($e, 'getContext')) $context = array_merge($e->getContext(), $context);

            // Handle exception and log error
            $this->logger->error(sprintf(
                __FUNCTION__ . ' > PaynetEasy exception : %s; Order id: %s;',
                $e->getMessage(),
                $this->order->get('id') ?: ''
            ), $context);

            $status = $context['order_status'] ?? $this->modx->getOption('ms2_status_canceled', null, 4) ?: 4;

            // Set status "cancelled"
            $this->ms2->changeOrderStatus($this->order->get('id'), $status);
        }

        return false;
    }

    /**
     * Получение статуса заказа из PAYNETEASY API.
     *
     */
    public function getPaymentStatus($id_order): array
    {
        $modx = new modX;
        $sql = "SELECT paynet_order_id FROM payneteasy_payments WHERE merchant_order_id = :merchant_order_id";
        $statement = $modx->prepare($sql);
        if ($statement->execute(['merchant_order_id' => $id_order]))
            $paynet_order_id = $statement->fetchAll(PDO::FETCH_ASSOC);

        // Logging input
        $this->logger->debug(
            __FUNCTION__ . ' > getOrderInfo - INPUT: ', [
            'arguments' => [
                'orderId' => $id_order,
                'paynet_order_id' => $paynet_order_id[0]['paynet_order_id']
            ]
        ]);

        $data = [
            'login' => $this->config['login']??$modx->getConfig()['ms2_payment_payneteasy_login'],
            'client_orderid' => (string)$id_order,
            'orderid' => $paynet_order_id[0]['paynet_order_id'],
        ];
        $data['control'] = $this->signStatusRequest($data, $this->config['login']??$modx->getConfig()['ms2_payment_payneteasy_login'], $this->config['control_key']??$modx->getConfig()['ms2_payment_payneteasy_control_key']);

        $action_url = $this->config['live_url']??$modx->getConfig()['ms2_payment_payneteasy_live_url'];
        if ($this->config['sandbox']??$modx->getConfig()['ms2_payment_payneteasy_sandbox'])
            $action_url = $this->config['sandbox_url']??$modx->getConfig()['ms2_payment_payneteasy_sandbox_url'];

        $response = $this->getPaynetApi()->status(
            $data,
            $this->config['payment_method']??$modx->getConfig()['ms2_payment_payneteasy_payment_method'],
            $this->config['sandbox']??$modx->getConfig()['ms2_payment_payneteasy_sandbox'],
            $action_url,
            $this->config['endpoint_id']??$modx->getConfig()['ms2_payment_payneteasy_endpoint_id']
        );

        // Logging output
        $this->logger->debug(
            __FUNCTION__ . ' > getOrderInfo - OUTPUT: ', [
            'response' => $response
        ]);

        return $response;
    }


    /**
     * Устанавливаем статус заказа
     *
     * @param string $paymentStatus Статус оплаты.
     *
     * @return void
     */
    private function changePaymentStatus(string $paymentStatus): void
    {
        $currentOrderStatus = $this->order->get('status');
        $paidStatus = (int) $this->modx->getOption('ms2_status_paid', null, 2) ?: 2;
        $availableStatuses = [
            'approved' => [
                'order_status' => $paidStatus
            ],
            'processing' => [
                'order_status' => (int) $this->modx->getObject('msOrderStatus', ['rank' => 7777])->id
            ],
            'refunded' => [
                'order_status' => (int) $this->modx->getObject('msOrderStatus', ['rank' => 8888])->id
            ]
        ];

        $orderStatusData = $availableStatuses[$paymentStatus] ?? [];

        if (!empty($orderStatusData)) {
            if ($currentOrderStatus !== $orderStatusData['order_status']) {
                $this->ms2->changeOrderStatus(
                    $this->order->get('id'),
                    $orderStatusData['order_status']
                );
                if (isset($orderStatusData['fnc'])) $orderStatusData['fnc']();
            }
        } else {
            throw new \Exception($this->modx->lexicon('ms2_payment_payneteasy_unsuccessful_payment_message'));
        }
    }


    /**
     * Отправляем запрос на возврат в PAYNETEASY, возвращвем результат запроса и логируем входящие и выходящие данные.
     * Если запрос прошёл успешно, то и в CMS отображаем информацию о возврате.
     *
     * @return array
     */
    private function makeChargebackInPayneteasy(): array
    {
        // Logging input
        $this->logger->debug(
            __FUNCTION__ . ' > setRefunds - INPUT: ', [
            'arguments' => [
                'order_id' => $this->order->get('id')
            ]
        ]);

        $sql = "SELECT paynet_order_id FROM payneteasy_payments WHERE merchant_order_id = :merchant_order_id";
        $statement = $this->modx->prepare($sql);
        if ($statement->execute(['merchant_order_id' => $this->order->get('id')]))
            $paynet_order_id = $statement->fetchAll(PDO::FETCH_ASSOC);

        $data = [
            'login' => $this->config['login'],
            'client_orderid' => $this->order->get('id'),
            'orderid' => $paynet_order_id[0]['paynet_order_id'],
            'comment' => 'Order cancel '
        ];

        $data['control'] = $this->signPaymentRequest($data, $this->config['endpoint_id'], $this->config['control_key']);

        $action_url = $this->config['live_url'];
        if ($this->config['sandbox'])
            $action_url = $this->config['sandbox_url'];

        $response = $this->getPaynetApi()->return(
            $data,
            $this->config['payment_method'],
            $this->config['sandbox'],
            $action_url,
            $this->config['endpoint_id']
        );

        // Logging output
        $this->logger->debug(
            __FUNCTION__ . ' > setRefunds - OUTPUT: ', [
            'response' => $response
        ]);

        return $response;
    }


    /**
     * Create and return an instance of the PaynetApi
     *
     * @return PaynetApi An instance of the PaynetApi class.
     */
    private function getPaynetApi(): PaynetApi
    {
        return new PaynetApi(
            trim($this->config['login']),
            trim($this->config['control_key']),
            trim($this->config['endpoint_id']),
            trim($this->config['payment_method']),
            (bool) $this->config['sandbox']

        );
    }


    /**
     * Инициализация и настройка объекта класса PaynetEasyLogger.
     *
     * Эта функция инициализирует и настраивает логгер, используемый плагином PaynetEasy для ведения журнала.
     *
     * @return void
     */
    private function setPayneteasyLogger(): void
    {
        $modx = $this->modx;
        $modx->setLogTarget([
            'target' => 'FILE',
            'options' => [
                'filename' => 'payneteasy-' . date('d-m-Y') . '.log'
            ]
        ]);

        $logging = $this->config['logging'];

        $this->logger = PaynetEasyLogger::getInstance()
            ->setOption('showCurrentDate', false)
            ->setOption('showLogLevel', false)
            ->setCustomRecording(function($message) use ($modx) {
                $type = modX::LOG_LEVEL_ERROR;
                $modx->setLogLevel($type);
                $modx->log(
                    $type,
                    '[miniShop2:PaynetEasy] ' . $message
                );
            }, PaynetEasyLogger::LOG_LEVEL_ERROR)
            ->setCustomRecording(function($message) use ($modx, $logging) {
                $type = modX::LOG_LEVEL_INFO;
                $modx->setLogLevel($type);
                if ($logging) $modx->log(
                    $type,
                    '[miniShop2:PaynetEasy] ' . $message
                );
            }, PaynetEasyLogger::LOG_LEVEL_DEBUG);
    }

    private function signStatusRequest($requestFields, $login, $merchantControl)
    {
        $base = '';
        $base .= $login;
        $base .= $requestFields['client_orderid'];
        $base .= $requestFields['orderid'];

        return $this->signString($base, $merchantControl);
    }


    private function signPaymentRequest($data, $endpointId, $merchantControl)
    {
        $base = '';
        $base .= $endpointId;
        $base .= $data['client_orderid'];
        $base .= $data['amount'] * 100;
        $base .= $data['email'];

        return $this->signString($base, $merchantControl);
    }


    private function signString($s, $merchantControl)
    {
        return sha1($s . $merchantControl);
    }
}
