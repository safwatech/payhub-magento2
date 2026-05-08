<?php

declare(strict_types=1);

namespace Payhub\Payments\Controller\Flow;

use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Sales\Api\OrderRepositoryInterface;
use Payhub\Payhub as PayhubClient;
use Payhub\Payments\Helper\Data as PayhubHelper;

/**
 * GET /payhub/flow/status?id={order_id}  — JSON poll endpoint for the
 * flow page. Hits PayHub `payments.retrieve` and returns
 * { status, redirect? }.
 */
class Status implements HttpGetActionInterface
{
    public function __construct(
        private readonly RequestInterface $request,
        private readonly JsonFactory $jsonFactory,
        private readonly OrderRepositoryInterface $orderRepository,
        private readonly PayhubHelper $payhubHelper,
    ) {}

    public function execute(): Json
    {
        $orderId = (int) $this->request->getParam('id');
        $resp = $this->jsonFactory->create();
        if ($orderId <= 0) {
            return $resp->setData(['error' => 'bad_id']);
        }
        $order = $this->orderRepository->get($orderId);
        $info  = $order->getPayment();
        $paymentId = (string) ($info?->getAdditionalInformation('payhub_payment_id') ?? '');
        if ($paymentId === '') {
            return $resp->setData(['error' => 'no_payment']);
        }
        $client = new PayhubClient(
            $this->payhubHelper->getApiKey((int) $order->getStoreId()),
            $this->payhubHelper->getBaseUrl((int) $order->getStoreId()),
        );
        try {
            $payment = $client->payments->retrieve($paymentId);
        } catch (\Throwable $e) {
            return $resp->setData(['error' => $e->getMessage()]);
        }
        $payload = ['status' => $payment->status];
        if ($payment->status === 'succeeded') {
            $payload['redirect'] = $order->getStore()->getBaseUrl() . 'sales/order/view/order_id/' . $order->getEntityId();
        }
        return $resp->setData($payload);
    }
}
