<?php

declare(strict_types=1);

namespace Setono\Payum\QuickPay\Action;

use Payum\Core\Exception\LogicException;
use Setono\Payum\QuickPay\Action\Api\ApiAwareTrait;
use Setono\Payum\QuickPay\Model\QuickPayPayment;
use Setono\Payum\QuickPay\Model\QuickPayPaymentOperation;
use Payum\Core\Action\ActionInterface;
use Payum\Core\ApiAwareInterface;
use Payum\Core\GatewayAwareInterface;
use Payum\Core\GatewayAwareTrait;
use Payum\Core\Request\GetStatusInterface;
use Payum\Core\Bridge\Spl\ArrayObject;
use Payum\Core\Exception\RequestNotSupportedException;

class StatusAction implements ActionInterface, ApiAwareInterface, GatewayAwareInterface
{
    use GatewayAwareTrait;
    use ApiAwareTrait;

    /**
     * {@inheritdoc}
     *
     * @param GetStatusInterface $request
     */
    public function execute($request)
    {
        RequestNotSupportedException::assertSupports($this, $request);

        $model = ArrayObject::ensureArrayObject($request->getModel());

        if (!$model['quickpayPaymentId']) {
            $request->markNew();

            return;
        }

        $quickpayPayment = $this->api->getPayment($model);

        switch ($quickpayPayment->getState()) {
            case QuickPayPayment::STATE_INITIAL:
                $request->markNew();

                break;
            case QuickPayPayment::STATE_NEW:
                $latestOperation = $this->getLatestOperation($quickpayPayment);
                if (QuickPayPaymentOperation::TYPE_AUTHORIZE === $latestOperation->getType() && $latestOperation->isApproved()) {
                    $request->markAuthorized();
                } else {
                    $request->markFailed();
                }

                break;
            case QuickPayPayment::STATE_PENDING:
                $request->markPending();

                break;
            case QuickPayPayment::STATE_REJECTED:
                $request->markFailed();

                break;
            case QuickPayPayment::STATE_PROCESSED:
                $latestOperation = $this->getLatestOperation($quickpayPayment);
                if (QuickPayPaymentOperation::TYPE_CAPTURE === $latestOperation->getType() && $latestOperation->isApproved()) {
                    $request->markCaptured();
                } else {
                    $request->markCanceled();
                }

                break;
            default:
                $request->markUnknown();
        }
    }

    /**
     * {@inheritdoc}
     */
    public function supports($request)
    {
        return
            $request instanceof GetStatusInterface &&
            $request->getModel() instanceof \ArrayAccess;
    }

    private function getLatestOperation(QuickPayPayment $quickPayPayment): QuickPayPaymentOperation
    {
        $latestOperation = $quickPayPayment->getLatestOperation();

        if (null === $latestOperation) {
            throw new LogicException('No latest operation available');
        }

        return $latestOperation;
    }
}
