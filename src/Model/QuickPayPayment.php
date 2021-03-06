<?php

declare(strict_types=1);

namespace Setono\Payum\QuickPay\Model;

use Psr\Http\Message\ResponseInterface;
use function Safe\json_decode;

class QuickPayPayment extends QuickPayModel
{
    public const STATE_INITIAL = 'initial';

    public const STATE_PENDING = 'pending';

    public const STATE_NEW = 'new';

    public const STATE_REJECTED = 'rejected';

    public const STATE_PROCESSED = 'processed';

    protected int $id;

    protected string $currency;

    protected string $order_id;

    protected string $state;

    protected array $operations;

    public static function createFromResponse(ResponseInterface $response): self
    {
        $data = json_decode((string) $response->getBody(), false);

        return new self($data);
    }

    public static function createFromObject(object $data): self
    {
        return new self($data);
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getOrderId(): string
    {
        return $this->order_id;
    }

    public function getCurrency(): string
    {
        return $this->currency;
    }

    /**
     * @return string initial, pending, new, rejected, processed
     */
    public function getState(): string
    {
        return $this->state;
    }

    public function getAuthorizedAmount(): int
    {
        $operations = array_reverse($this->getOperations());

        foreach ($operations as $operation) {
            if (QuickPayPaymentOperation::TYPE_AUTHORIZE === $operation->getType() && $operation->isApproved()) {
                return $operation->getAmount();
            }
        }

        return 0;
    }

    /**
     * @return QuickPayPaymentOperation[]
     */
    public function getOperations(): array
    {
        if (\count($this->operations) > 0) {
            return QuickPayPaymentOperation::createFromArray($this->operations);
        }

        return [];
    }

    public function getLatestOperation(): ?QuickPayPaymentOperation
    {
        if (\count($this->operations) > 0) {
            return QuickPayPaymentOperation::createFromObject(end($this->operations));
        }

        return null;
    }
}
