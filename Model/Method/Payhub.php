<?php

declare(strict_types=1);

namespace Payhub\Payments\Model\Method;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Data\Collection\AbstractDb;
use Magento\Framework\Model\Context;
use Magento\Framework\Model\ResourceModel\AbstractResource;
use Magento\Framework\Registry;
use Magento\Payment\Helper\Data as PaymentHelper;
use Magento\Payment\Model\InfoInterface;
use Magento\Payment\Model\Method\AbstractMethod;
use Magento\Payment\Model\Method\Logger;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Payment;
use Payhub\Payhub as PayhubClient;
use Payhub\PayhubError;
use Payhub\Payments\Helper\Data as PayhubHelper;

/**
 * PayHub payment method. Order-flow only — refunds via process_refund;
 * customer-side OTP/QR/Lightbox interactions happen on a custom flow
 * page reached via redirectUrl after order placement (not in the
 * Knockout checkout).
 */
class Payhub extends AbstractMethod
{
    public const CODE = 'payhub';

    /** @var string */
    protected $_code = self::CODE;

    /** @var bool */
    protected $_isInitializeNeeded = true;

    /** @var bool */
    protected $_isOffline = false;

    /** @var bool */
    protected $_isGateway = true;

    /** @var bool */
    protected $_canCapture = false;       // capture happens via webhook → invoice on payment.succeeded

    /** @var bool */
    protected $_canRefund = true;

    /** @var bool */
    protected $_canRefundInvoicePartial = true;

    /** @var bool */
    protected $_canVoid = false;

    /** @var bool */
    protected $_canUseInternal = false;

    public function __construct(
        Context $context,
        Registry $registry,
        \Magento\Framework\Api\ExtensionAttributesFactory $extensionFactory,
        \Magento\Framework\Api\AttributeValueFactory $customAttributeFactory,
        PaymentHelper $paymentData,
        ScopeConfigInterface $scopeConfig,
        Logger $logger,
        private readonly PayhubHelper $payhubHelper,
        AbstractResource $resource = null,
        AbstractDb $resourceCollection = null,
        array $data = [],
    ) {
        parent::__construct(
            $context,
            $registry,
            $extensionFactory,
            $customAttributeFactory,
            $paymentData,
            $scopeConfig,
            $logger,
            $resource,
            $resourceCollection,
            $data,
        );
    }

    public function isAvailable(\Magento\Quote\Api\Data\CartInterface $quote = null): bool
    {
        if (!$this->payhubHelper->isActive($quote ? (int) $quote->getStoreId() : null)) {
            return false;
        }
        return parent::isAvailable($quote);
    }

    /**
     * Build the PayHub payment lazily; payload-stash flow continues on
     * the post-checkout redirect page.
     */
    public function initialize($paymentAction, $stateObject)
    {
        /** @var Payment $info */
        $info = $this->getInfoInstance();
        /** @var Order $order */
        $order = $info->getOrder();
        $stateObject->setState(Order::STATE_PENDING_PAYMENT);
        $stateObject->setStatus('pending_payment');
        $stateObject->setIsNotified(false);

        $client = $this->client((int) $order->getStoreId());
        $request = [
            'psp'                => $this->payhubHelper->getDefaultPsp((int) $order->getStoreId()),
            'amount_minor'       => self::amountToMinor($order),
            'currency'           => $order->getOrderCurrencyCode() ?: 'LYD',
            'merchant_order_ref' => $order->getIncrementId() ?: (string) $order->getEntityId(),
            'customer'           => self::customerData($order, $info),
            'return_urls'        => [
                'success_url' => $order->getStore()->getBaseUrl() . 'payhub/return/success?id=' . $order->getEntityId(),
                'cancel_url'  => $order->getStore()->getBaseUrl() . 'payhub/return/cancel?id=' . $order->getEntityId(),
            ],
            'metadata' => [
                'magento_order_id'        => (string) $order->getEntityId(),
                'magento_increment_id'    => (string) $order->getIncrementId(),
                'site_url'                => $order->getStore()->getBaseUrl(),
            ],
        ];
        $idempotency = 'm2-' . $order->getEntityId() . '-' . $order->getIncrementId();

        try {
            $payment = $client->payments->create($request, $idempotency);
        } catch (PayhubError $e) {
            $this->payhubLog('payments.create failed: ' . $e->getMessage());
            throw new \Magento\Framework\Exception\LocalizedException(__('Payment could not be initiated: %1', $e->getMessage()));
        }

        $info->setAdditionalInformation('payhub_payment_id', $payment->id);
        $info->setAdditionalInformation('payhub_psp', $payment->psp);
        $info->setAdditionalInformation('payhub_status', $payment->status);
        $info->setAdditionalInformation('payhub_next_action', $payment->nextAction ? self::serializeNextAction($payment) : null);
        $info->setAdditionalInformation('payhub_redirect_url', self::flowUrl($order));

        return $this;
    }

    public function refund(InfoInterface $payment, $amount): self
    {
        /** @var Payment $payment */
        $paymentId = (string) $payment->getAdditionalInformation('payhub_payment_id');
        if ($paymentId === '') {
            throw new \Magento\Framework\Exception\LocalizedException(__('No PayHub payment recorded on this order.'));
        }
        $order = $payment->getOrder();
        $amountMinor = self::amountToMinorFromCurrency((float) $amount, $order->getOrderCurrencyCode() ?: 'LYD');

        try {
            $this->client((int) $order->getStoreId())->payments->refund(
                $paymentId,
                $amountMinor,
                $payment->getCreditmemo() ? (string) $payment->getCreditmemo()->getCustomerNote() : null,
                'm2-refund-' . $order->getEntityId() . '-' . time(),
            );
        } catch (PayhubError $e) {
            throw new \Magento\Framework\Exception\LocalizedException(__('Refund failed: %1', $e->getMessage()));
        }
        return $this;
    }

    private function client(int $storeId): PayhubClient
    {
        $apiKey = $this->payhubHelper->getApiKey($storeId);
        if ($apiKey === '' || !str_starts_with($apiKey, 'phk_')) {
            throw new \Magento\Framework\Exception\LocalizedException(__('PayHub API key is not configured.'));
        }
        return new PayhubClient(
            apiKey: $apiKey,
            baseUrl: $this->payhubHelper->getBaseUrl($storeId),
            userAgentSuffix: 'payhub-magento2/0.2.0',
        );
    }

    private function payhubLog(string $message): void
    {
        if ($this->payhubHelper->isDebug()) {
            $this->logger->debug($message);
        }
    }

    public static function amountToMinor(Order $order): int
    {
        return self::amountToMinorFromCurrency((float) $order->getGrandTotal(), $order->getOrderCurrencyCode() ?: 'LYD');
    }

    private static function amountToMinorFromCurrency(float $amount, string $currency): int
    {
        $code = strtoupper($currency);
        $mul = match (true) {
            in_array($code, ['LYD', 'BHD', 'KWD', 'OMR', 'TND', 'IQD', 'JOD'], true) => 1000,
            in_array($code, ['JPY', 'KRW', 'VND'], true)                              => 1,
            default                                                                    => 100,
        };
        return (int) round($amount * $mul);
    }

    private static function customerData(Order $order, Payment $info): array
    {
        $msisdn = (string) $info->getAdditionalInformation('payhub_msisdn');
        $msisdn = preg_replace('/\D/', '', $msisdn) ?? '';
        if ($msisdn !== '' && !str_starts_with($msisdn, '218')) {
            $msisdn = '218' . ltrim($msisdn, '0');
        }
        $birthYear = (int) $info->getAdditionalInformation('payhub_birth_year');
        $billing = $order->getBillingAddress();
        $customer = [];
        if ($msisdn !== '') {
            $customer['msisdn'] = $msisdn;
        } elseif ($billing && $billing->getTelephone()) {
            $customer['msisdn'] = preg_replace('/\D/', '', (string) $billing->getTelephone()) ?? '';
        }
        if ($birthYear > 1900) {
            $customer['birth_year'] = $birthYear;
        }
        if ($order->getCustomerEmail()) {
            $customer['email'] = $order->getCustomerEmail();
        }
        $name = trim(($order->getCustomerFirstname() ?? '') . ' ' . ($order->getCustomerLastname() ?? ''));
        if ($name !== '') {
            $customer['name'] = $name;
        }
        return $customer;
    }

    private static function serializeNextAction(\Payhub\Payment $payment): array
    {
        $next = $payment->nextAction;
        if ($next instanceof \Payhub\OtpRequired) {
            return ['kind' => 'otp', 'masked_destination' => $next->maskedDestination, 'expires_at' => $next->expiresAt];
        }
        if ($next instanceof \Payhub\Redirect) {
            return ['kind' => 'redirect', 'url' => $next->url, 'method' => $next->method, 'fields' => $next->fields, 'expires_at' => $next->expiresAt];
        }
        if ($next instanceof \Payhub\QR) {
            return ['kind' => 'qr', 'qr_payload' => $next->qrPayload, 'reference' => $next->reference, 'expires_at' => $next->expiresAt];
        }
        if ($next instanceof \Payhub\Lightbox) {
            return ['kind' => 'lightbox', 'params' => $next->params, 'script_url' => $next->scriptUrl];
        }
        return [];
    }

    private static function flowUrl(Order $order): string
    {
        return $order->getStore()->getBaseUrl() . 'payhub/flow/index/id/' . $order->getEntityId();
    }
}
