<?php

declare(strict_types=1);

namespace Payhub\Payments\Helper;

use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;
use Magento\Framework\Encryption\EncryptorInterface;
use Magento\Store\Model\ScopeInterface;

class Data extends AbstractHelper
{
    public const XML_PATH = 'payment/payhub/';

    public function __construct(
        Context $context,
        private readonly EncryptorInterface $encryptor,
    ) {
        parent::__construct($context);
    }

    public function getApiKey(?int $storeId = null): string
    {
        $enc = (string) $this->config('api_key', $storeId);
        return $enc !== '' ? $this->encryptor->decrypt($enc) : '';
    }

    public function getWebhookSecret(?int $storeId = null): string
    {
        $hex = (string) $this->config('webhook_secret_hex', $storeId);
        if ($hex === '') {
            return '';
        }
        $hex = $this->encryptor->decrypt($hex);
        $bin = @hex2bin($hex);
        return $bin === false ? '' : $bin;
    }

    public function getBaseUrl(?int $storeId = null): string
    {
        $env = (string) $this->config('environment', $storeId);
        if ($env === 'custom') {
            $custom = trim((string) $this->config('base_url', $storeId));
            return $custom !== '' ? rtrim($custom, '/') : 'https://app.payhub.ly';
        }
        return $env === 'sandbox' ? 'https://demo.payhub.ly' : 'https://app.payhub.ly';
    }

    public function getDefaultPsp(?int $storeId = null): string
    {
        return (string) ($this->config('default_psp', $storeId) ?: 'moamalat');
    }

    public function isDebug(?int $storeId = null): bool
    {
        return (bool) $this->config('debug', $storeId);
    }

    public function isActive(?int $storeId = null): bool
    {
        return (bool) $this->config('active', $storeId);
    }

    private function config(string $field, ?int $storeId): mixed
    {
        return $this->scopeConfig->getValue(
            self::XML_PATH . $field,
            ScopeInterface::SCOPE_STORE,
            $storeId,
        );
    }
}
