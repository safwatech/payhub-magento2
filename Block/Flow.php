<?php

declare(strict_types=1);

namespace Payhub\Payments\Block;

use Magento\Framework\View\Element\Template;

class Flow extends Template
{
    public function getNextActionKind(): string
    {
        $next = (array) $this->getData('next_action');
        return (string) ($next['kind'] ?? '');
    }

    public function getNextAction(): array
    {
        return (array) ($this->getData('next_action') ?? []);
    }

    public function getOrder(): \Magento\Sales\Api\Data\OrderInterface
    {
        return $this->getData('order');
    }
}
