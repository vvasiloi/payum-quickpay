<?php

declare(strict_types=1);

namespace Setono\Payum\QuickPay\Model;

use stdClass;

class QuickPayPaymentOperation extends QuickPayModel
{
    public const TYPE_AUTHORIZE = 'authorize';

    public const TYPE_CAPTURE = 'capture';

    public const TYPE_REFUND = 'refund';

    public const TYPE_CANCEL = 'cancel';

    public const STATUS_CODE_APPROVED = 20000;

    protected int $id;

    protected string $type;

    protected ?int $amount = null;

    protected ?string $qp_status_code = null;

    public static function createFromObject(stdClass $operations): self
    {
        return new self($operations);
    }

    public static function createFromArray(array $operations): array
    {
        $ret = [];
        foreach ($operations as $operation) {
            $ret[] = self::createFromObject($operation);
        }

        return $ret;
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function getAmount(): int
    {
        return (int) $this->amount;
    }

    public function getStatusCode(): int
    {
        return (int) $this->qp_status_code;
    }

    public function isApproved(): bool
    {
        return self::STATUS_CODE_APPROVED === $this->getStatusCode();
    }
}
