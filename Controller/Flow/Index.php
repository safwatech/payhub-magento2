<?php

declare(strict_types=1);

namespace Payhub\Payments\Controller\Flow;

use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\View\Result\Page;
use Magento\Sales\Api\OrderRepositoryInterface;
use Payhub\Payments\Model\Method\Payhub as PayhubMethod;

/**
 * GET /payhub/flow/index/id/{order_id}  — renders the OTP / QR /
 * Lightbox / redirect page after order placement.
 */
class Index implements HttpGetActionInterface
{
    public function __construct(
        private readonly RequestInterface $request,
        private readonly ResultFactory $resultFactory,
        private readonly OrderRepositoryInterface $orderRepository,
    ) {}

    public function execute()
    {
        $orderId = (int) $this->request->getParam('id');
        if ($orderId <= 0) {
            $r = $this->resultFactory->create(ResultFactory::TYPE_REDIRECT);
            return $r->setUrl('/');
        }
        $order = $this->orderRepository->get($orderId);
        $info  = $order->getPayment();
        if (!$info || $info->getMethod() !== PayhubMethod::CODE) {
            $r = $this->resultFactory->create(ResultFactory::TYPE_REDIRECT);
            return $r->setUrl('/');
        }

        /** @var Page $page */
        $page = $this->resultFactory->create(ResultFactory::TYPE_PAGE);
        $page->getConfig()->getTitle()->set(__('Complete your payment'));

        $block = $page->getLayout()->createBlock(\Payhub\Payments\Block\Flow::class)
            ->setTemplate('Payhub_Payments::flow.phtml')
            ->setData('order', $order)
            ->setData('next_action', $info->getAdditionalInformation('payhub_next_action'))
            ->setData('payment_id', (string) $info->getAdditionalInformation('payhub_payment_id'));

        $page->getLayout()->getBlock('content')?->append($block);
        return $page;
    }
}
