<?php

declare(strict_types=1);

namespace Payhub\Payments\Model\Ui;

use Magento\Checkout\Model\ConfigProviderInterface;
use Payhub\Payments\Helper\Data as PayhubHelper;
use Payhub\Payments\Model\Method\Payhub;

final class ConfigProvider implements ConfigProviderInterface
{
    public function __construct(
        private readonly PayhubHelper $helper,
    ) {}

    public function getConfig(): array
    {
        return [
            'payment' => [
                Payhub::CODE => [
                    'enabled'      => $this->helper->isActive(),
                    'default_psp'  => $this->helper->getDefaultPsp(),
                    'logo_url'     => '',
                    'flow_url_template' => '/payhub/flow/index/id/',
                ],
            ],
        ];
    }
}
