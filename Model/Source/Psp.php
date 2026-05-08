<?php

declare(strict_types=1);

namespace Payhub\Payments\Model\Source;

use Magento\Framework\Data\OptionSourceInterface;

final class Psp implements OptionSourceInterface
{
    public function toOptionArray(): array
    {
        return [
            ['value' => 'sadad',    'label' => __('Sadad (Almadar)')],
            ['value' => 'moamalat', 'label' => __('Moamalat')],
            ['value' => 'mobicash', 'label' => __('Mobicash')],
            ['value' => 'tlync',    'label' => __('T-Lync')],
            ['value' => 'adfali',   'label' => __('Adfali')],
        ];
    }
}
