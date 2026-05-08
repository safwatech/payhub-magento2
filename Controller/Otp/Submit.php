<?php

declare(strict_types=1);

namespace Payhub\Payments\Controller\Otp;

use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\CsrfAwareActionInterface;
use Magento\Framework\App\Request\InvalidRequestException;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Sales\Api\OrderRepositoryInterface;
use Payhub\Payhub as PayhubClient;
use Payhub\Payments\Helper\Data as PayhubHelper;

/**
 * POST /payhub/otp/submit  body: { id, code }
 */
class Submit implements HttpPostActionInterface, CsrfAwareActionInterface
{
    public function __construct(
        private readonly RequestInterface $request,
        private readonly JsonFactory $jsonFactory,
        private readonly OrderRepositoryInterface $orderRepository,
        private readonly PayhubHelper $payhubHelper,
    ) {}

    public function execute(): Json
    {
        $r = $this->jsonFactory->create();
        $body = json_decode((string) $this->request->getContent(), true) ?: [];
        $orderId = (int) ($body['id'] ?? 0);
        $code    = (string) ($body['code'] ?? '');
        if ($orderId <= 0 || !preg_match('/^\d{4,8}$/', $code)) {
            return $r->setData(['error' => 'invalid_input']);
        }
        $order = $this->orderRepository->get($orderId);
        $info  = $order->getPayment();
        $paymentId = (string) ($info?->getAdditionalInformation('payhub_payment_id') ?? '');
        if ($paymentId === '') {
            return $r->setData(['error' => 'no_payment']);
        }
        $client = new PayhubClient(
            $this->payhubHelper->getApiKey((int) $order->getStoreId()),
            $this->payhubHelper->getBaseUrl((int) $order->getStoreId()),
        );
        try {
            $payment = $client->payments->confirmOtp($paymentId, $code);
        } catch (\Throwable $e) {
            return $r->setData(['error' => $e->getMessage()]);
        }
        if ($payment->status === 'succeeded') {
            return $r->setData(['redirect' => $order->getStore()->getBaseUrl() . 'sales/order/view/order_id/' . $orderId]);
        }
        return $r->setData(['status' => $payment->status]);
    }

    public function createCsrfValidationException(RequestInterface $request): ?InvalidRequestException
    {
        return null;
    }

    public function validateForCsrf(RequestInterface $request): ?bool
    {
        return true;
    }
}
