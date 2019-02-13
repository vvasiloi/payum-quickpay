<?php

declare(strict_types=1);

namespace Setono\Payum\QuickPay\Model;

use Psr\Http\Message\ResponseInterface;

class QuickPayPaymentLink extends QuickPayModel
{
    /**
     * @var string
     */
    protected $url;

    /**
     * @param ResponseInterface $response
     *
     * @return QuickPayPaymentLink
     */
    public static function createFromResponse(ResponseInterface $response): self
    {
        $data = json_decode((string) $response->getBody());

        return new self($data);
    }

    /**
     * @return string
     */
    public function getUrl(): string
    {
        return $this->url;
    }
}
