<?php

declare(strict_types=1);

namespace Setono\Payum\QuickPay;

use Http\Message\MessageFactory;
use Payum\Core\Bridge\Spl\ArrayObject;
use Payum\Core\Exception\Http\HttpException;
use Payum\Core\Exception\InvalidArgumentException;
use Payum\Core\Exception\LogicException;
use Payum\Core\HttpClientInterface;
use Payum\Core\Model\Payment;
use Psr\Http\Message\ResponseInterface;
use Setono\Payum\QuickPay\Model\QuickPayPayment;
use Setono\Payum\QuickPay\Model\QuickPayPaymentLink;

class Api
{
    public const VERSION = 'v10';

    /** @var HttpClientInterface */
    protected $client;

    /** @var MessageFactory */
    protected $messageFactory;

    /** @var array */
    protected $options = [];

    public function __construct(array $options, HttpClientInterface $client, MessageFactory $messageFactory)
    {
        $this->options = $options;
        $this->client = $client;
        $this->messageFactory = $messageFactory;
    }

    public function getPayment(ArrayObject $params, bool $create = true): QuickPayPayment
    {
        $params = ArrayObject::ensureArrayObject($params);

        if (isset($params['quickpayPayment']) && $params['quickpayPayment'] instanceof QuickPayPayment) {
            return $params['quickpayPayment'];
        }

        if (is_int($params['quickpayPaymentId'])) {
            $response = $this->doRequest('GET', 'payments/'.$params['quickpayPaymentId']);
        } else {
            /** @var Payment $paymentModel */
            $paymentModel = $params['payment'];
            if ($create) {
                $response = $this->doRequest('POST', 'payments', [
                    'order_id' => $this->getOption('order_prefix').$paymentModel->getNumber(),
                    'currency' => $paymentModel->getCurrencyCode(),
                ]);
            } else {
                throw new LogicException('Payment does not exist');
            }
        }

        return QuickPayPayment::createFromResponse($response);
    }

    /**
     * @return QuickPayPayment[]
     */
    public function getPayments(ArrayObject $params): array
    {
        $params = ArrayObject::ensureArrayObject($params);

        $response = $this->doRequest('GET', 'payments?'.http_build_query($params->getArrayCopy()));

        $payments = json_decode((string) $response->getBody(), false);
        if (null === $payments) {
            throw new HttpException('Invalid response');
        }

        $return = [];
        foreach ($payments as $payment) {
            $return[] = QuickPayPayment::createFromObject($payment);
        }

        return $return;
    }

    public function createPaymentLink(QuickPayPayment $payment, ArrayObject $params): QuickPayPaymentLink
    {
        $params = ArrayObject::ensureArrayObject($params);
        $params->validateNotEmpty([
            'continue_url', 'cancel_url', 'callback_url', 'amount',
        ]);

        $response = $this->doRequest('PUT', 'payments/'.$payment->getId().'/link', $params->getArrayCopy() + [
            'payment_methods' => $this->options['payment_methods'],
            'auto_capture' => $this->options['auto_capture'],
        ]);

        return QuickPayPaymentLink::createFromResponse($response);
    }

    public function authorizePayment(QuickPayPayment $payment, ArrayObject $params): QuickPayPayment
    {
        $params = ArrayObject::ensureArrayObject($params);
        $params->validateNotEmpty([
            'card', 'amount',
        ]);

        $response = $this->doRequest('POST', 'payments/'.$payment->getId().'/authorize', $params->getArrayCopy());

        return QuickPayPayment::createFromResponse($response);
    }

    public function capturePayment(QuickPayPayment $payment, ArrayObject $params): QuickPayPayment
    {
        $params = ArrayObject::ensureArrayObject($params);
        $params->validateNotEmpty([
            'amount',
        ]);

        $response = $this->doRequest('POST', 'payments/'.$payment->getId().'/capture', $params->getArrayCopy());

        return QuickPayPayment::createFromResponse($response);
    }

    public function refundPayment(QuickPayPayment $payment, ArrayObject $params): QuickPayPayment
    {
        $params = ArrayObject::ensureArrayObject($params);
        $params->validateNotEmpty([
            'amount',
        ]);

        $response = $this->doRequest('POST', 'payments/'.$payment->getId().'/refund', $params->getArrayCopy());

        return QuickPayPayment::createFromResponse($response);
    }

    public function cancelPayment(QuickPayPayment $payment, ArrayObject $params): QuickPayPayment
    {
        $params = ArrayObject::ensureArrayObject($params);
        $params->validateNotEmpty([
            'amount',
        ]);

        $response = $this->doRequest('POST', 'payments/'.$payment->getId().'/cancel', $params->getArrayCopy());

        return QuickPayPayment::createFromResponse($response);
    }

    protected function doRequest($method, string $path, array $params = []): ResponseInterface
    {
        $headers = [
            'Authorization' => 'Basic '.base64_encode(':'.$this->getOption('apikey')),
            'Accept-Version' => self::VERSION,
            'Content-Type' => 'application/json',
        ];

        $encodedParams = json_encode($params);
        if (false === $encodedParams) {
            throw new InvalidArgumentException('Could not encode $params');
        }

        $request = $this->messageFactory->createRequest(
            $method,
            $this->getApiEndpoint().'/'.ltrim($path, '/'),
            $headers,
            $encodedParams
        );

        $response = $this->client->send($request);
        $statusCode = $response->getStatusCode();

        if ($statusCode < 200 || $statusCode > 299) {
            throw new HttpException((string) $response->getBody(), $response->getStatusCode());
        }

        self::assertValidResponse($response, (string) $this->getOption('privatekey'));

        return $response;
    }

    protected function getApiEndpoint(): string
    {
        return 'https://api.quickpay.net';
    }

    /**
     * Generates a checksum based on response body.
     *
     * @param string $data
     * @param string $privateKey
     */
    public static function checksum($data, $privateKey): string
    {
        return hash_hmac('sha256', $data, $privateKey);
    }

    public static function assertValidResponse(ResponseInterface $response, string $privateKey): void
    {
        if ($response->hasHeader('QuickPay-Checksum-Sha256')) {
            $checksum = self::checksum((string) $response->getBody(), $privateKey);
            $quickpayChecksum = $response->getHeaderLine('QuickPay-Checksum-Sha256');
            if ($checksum !== $quickpayChecksum) {
                throw new LogicException('Invalid checksum');
            }
        }
    }

    public function getOption(string $option, $default = '')
    {
        return $this->options[$option] ?? $default;
    }
}
