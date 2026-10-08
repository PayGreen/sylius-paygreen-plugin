<?php

declare(strict_types=1);

namespace PayGreen\SyliusPayumPlugin\Tests\Bridge\PayGreen;

use Nyholm\Psr7\Response;
use PayGreen\SyliusPayumPlugin\Bridge\PayGreen\ResponseExtractor;
use PHPUnit\Framework\TestCase;

final class ResponseExtractorTest extends TestCase
{
    public function testItExtractsPaymentOrderDetailsFromSdkResponse(): void
    {
        $response = new Response(201, [], json_encode([
            'data' => [
                'id' => 'po_123',
                'hosted_payment_url' => 'https://paygreen.example/payment/po_123',
                'status' => 'pending',
            ],
        ], JSON_THROW_ON_ERROR));

        $details = (new ResponseExtractor())->extractPaymentOrderDetails($response);

        self::assertSame('po_123', $details['paygreen_payment_order_id']);
        self::assertSame('https://paygreen.example/payment/po_123', $details['paygreen_hosted_payment_url']);
        self::assertSame('pending', $details['paygreen_status']);
    }

    public function testItFindsHostedUrlInNestedPayload(): void
    {
        $extractor = new ResponseExtractor();

        self::assertSame(
            'https://paygreen.example/payment/po_123',
            $extractor->extractHostedPaymentUrl([
                'payment_order' => [
                    'hostedPaymentUrl' => 'https://paygreen.example/payment/po_123',
                ],
            ]),
        );
    }

    public function testItCanReadSeekableSdkResponsesMoreThanOnce(): void
    {
        $response = new Response(201, [], json_encode([
            'data' => [
                'id' => 'po_123',
                'hosted_payment_url' => 'https://paygreen.example/payment/po_123',
            ],
        ], JSON_THROW_ON_ERROR));

        $extractor = new ResponseExtractor();

        self::assertSame('https://paygreen.example/payment/po_123', $extractor->extractHostedPaymentUrl($response));
        self::assertSame('po_123', $extractor->extractPaymentOrderDetails($response)['paygreen_payment_order_id']);
    }
}
