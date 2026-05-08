<?php

declare(strict_types=1);

namespace Payhub\Payments\Controller\Return;

use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\ResultFactory;

class Success implements HttpGetActionInterface
{
    public function __construct(
        private readonly RequestInterface $request,
        private readonly ResultFactory $resultFactory,
    ) {}

    public function execute()
    {
        $orderId = (int) $this->request->getParam('id');
        $r = $this->resultFactory->create(ResultFactory::TYPE_REDIRECT);
        return $r->setPath('checkout/onepage/success', ['order_id' => $orderId]);
    }
}
