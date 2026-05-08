<?php

declare(strict_types=1);

namespace Payhub\Payments\Model\Source;

use Magento\Framework\Data\OptionSourceInterface;

final class Environment implements OptionSourceInterface
{
    public function toOptionArray(): array
    {
        return [
            ['value' => 'production', 'label' => __('Production')],
            ['value' => 'sandbox',    'label' => __('Sandbox / Demo')],
            ['value' => 'custom',     'label' => __('Custom (set Base URL)')],
        ];
    }
}
