<?php

declare(strict_types=1);

namespace Payhub\Payments\Controller\Webhook;

use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\CsrfAwareActionInterface;
use Magento\Framework\App\Request\InvalidRequestException;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\ResourceModel\Order\CollectionFactory as OrderCollectionFactory;
use Payhub\InvalidSignature;
use Payhub\MalformedHeader;
use Payhub\Payments\Helper\Data as PayhubHelper;
use Payhub\TimestampOutOfTolerance;
use Payhub\WebhookEvent;
use Payhub\WebhookSignatureError;
use Psr\Log\LoggerInterface;

/**
 * POST /payhub/webhook  — receives `payment.*` events from PayHub.
 */
class Index implements HttpPostActionInterface, CsrfAwareActionInterface
{
    public function __construct(
        private readonly RequestInterface $request,
        private readonly JsonFactory $jsonFactory,
        private readonly PayhubHelper $payhubHelper,
        private readonly OrderCollectionFactory $orderCollectionFactory,
        private readonly OrderRepositoryInterface $orderRepository,
        private readonly LoggerInterface $logger,
    ) {}

    public function execute(): Json
    {
        $body = (string) $this->request->getContent();
        $sig  = (string) ($this->request->getHeader('Hub-Signature') ?: '');
        $secret = $this->payhubHelper->getWebhookSecret();
        if ($secret === '') {
            return $this->json(['error' => 'webhook_secret_not_configured'], 503);
        }

        try {
            $event = WebhookEvent::verify($secret, $body, $sig);
        } catch (TimestampOutOfTolerance $e) {
            return $this->json(['error' => 'stale_timestamp'], 401);
        } catch (MalformedHeader|InvalidSignature|WebhookSignatureError $e) {
            return $this->json(['error' => 'invalid_signature'], 401);
        }

        $order = $this->findOrder($event->paymentId);
        if (!$order) {
            return $this->json(['received' => true, 'ignored' => 'unknown payment'], 200);
        }

        $info = $order->getPayment();
        $applied = (string) ($info?->getAdditionalInformation('payhub_applied_events') ?? '');
        if (str_contains($applied, "[{$event->id}]")) {
            return $this->json(['received' => true, 'idempotent' => true], 200);
        }

        switch ($event->type) {
            case 'payment.succeeded':
                if (!$order->canInvoice()) {
                    break;
                }
                $invoice = $order->prepareInvoice();
                $invoice->setRequestedCaptureCase(\Magento\Sales\Model\Order\Invoice::CAPTURE_OFFLINE);
                $invoice->register();
                $invoice->getOrder()->setState(Order::STATE_PROCESSING)->setStatus('processing');
                $invoice->save();
                $invoice->getOrder()->save();
                $order->addCommentToStatusHistory('PayHub: payment ' . $event->paymentId . ' succeeded.');
                break;
            case 'payment.failed':
                $order->setState(Order::STATE_CANCELED)->setStatus('canceled');
                $order->addCommentToStatusHistory('PayHub: payment ' . $event->paymentId . ' failed.');
                break;
            case 'payment.expired':
                $order->setState(Order::STATE_CANCELED)->setStatus('canceled');
                $order->addCommentToStatusHistory('PayHub: payment ' . $event->paymentId . ' expired.');
                break;
            case 'payment.refunded':
                $order->addCommentToStatusHistory('PayHub: payment ' . $event->paymentId . ' refunded.');
                break;
            default:
                $order->addCommentToStatusHistory('PayHub: ' . $event->type . ' for ' . $event->paymentId);
        }

        $applied .= "[{$event->id}]";
        if (strlen($applied) > 4000) {
            $applied = substr($applied, -4000);
        }
        $info?->setAdditionalInformation('payhub_applied_events', $applied);
        $this->orderRepository->save($order);
        return $this->json(['received' => true], 200);
    }

    private function findOrder(string $paymentId): ?OrderInterface
    {
        $coll = $this->orderCollectionFactory->create();
        $coll->getSelect()->joinLeft(
            ['op' => $coll->getResource()->getTable('sales_order_payment')],
            'op.parent_id = main_table.entity_id',
            [],
        );
        $coll->getSelect()->where('op.additional_information LIKE ?', '%"payhub_payment_id":"' . $paymentId . '"%')->limit(1);
        $order = $coll->getFirstItem();
        return $order && $order->getId() ? $order : null;
    }

    public function createCsrfValidationException(RequestInterface $request): ?InvalidRequestException
    {
        return null;
    }

    public function validateForCsrf(RequestInterface $request): ?bool
    {
        return true;
    }

    private function json(array $body, int $status): Json
    {
        $r = $this->jsonFactory->create();
        $r->setHttpResponseCode($status);
        $r->setData($body);
        return $r;
    }
}
