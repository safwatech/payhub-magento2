<?php

declare(strict_types=1);

namespace Payhub\Payments\Controller\Return;

use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Controller\ResultFactory;

class Cancel implements HttpGetActionInterface
{
    public function __construct(
        private readonly ResultFactory $resultFactory,
    ) {}

    public function execute()
    {
        $r = $this->resultFactory->create(ResultFactory::TYPE_REDIRECT);
        return $r->setPath('checkout/cart');
    }
}
