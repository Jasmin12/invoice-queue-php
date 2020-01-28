<?php
declare(strict_types=1);

namespace Serato\InvoiceQueue\Test;

use Serato\InvoiceQueue\Test\AbstractTestCase;
use Serato\InvoiceQueue\SqsQueue;
use Serato\InvoiceQueue\Invoice;
use Serato\InvoiceQueue\InvoiceValidator;
use Aws\Sdk;
use Aws\Credentials\CredentialProvider;

#use Aws\Result;
#use Aws\Exception\AwsException;
#use Aws\Sqs\Exception\SqsException;
#use Exception;

/**
 * Tests the interact with live AWS services.
 *
 * All tests in the test case should be tagged with `@group aws-integration`.
 *
 * These tests are excluded as part of standard PHPUnit execution. To run them:
 *
 *      $ phpunit --group aws-integration
 */
class SqsQueueIntegrationTest extends AbstractTestCase
{
    /**
     * Tests the SqsQueue::sendToBatch method with multiple invoices.
     *
     * Test that a log entry is created
     *
     * Note: this is really a very useful test. I've just used as a means of making
     * IRL message batch sends and analysing the results :-)
     *
     * @group aws-integration
     */
    public function testSendInvoiceToBatch()
    {
        $validator = new InvoiceValidator;

        $sqsQueue = $this->getSqsQueueInstance();

        foreach ($this->getValidInvoiceData() as $data) {
            $invoice = Invoice::load($data, $validator);
            $sqsQueue->sendInvoiceToBatch($invoice, $validator);
        }

        # Destroy the object. This should trigger the batch send.
        unset($sqsQueue);

        $log = trim($this->getLogFileContents());

        $this->assertTrue($log !== '');
        $this->assertTrue(strpos($log, 'SQS sendMessageBatch') !== false);
        # 1 log entry per invoice in the batch
        $this->assertEquals(2, count(explode("\n", $log)));
    }

    private function getSqsQueueInstance(): SqsQueue
    {
        $sdk = new Sdk([
            'region' => 'us-east-1',
            'version' => '2014-11-01',
            'credentials' => CredentialProvider::memoize(
                CredentialProvider::chain(
                    CredentialProvider::ini(),
                    CredentialProvider::env()
                )
            )
        ]);
        return new SqsQueue(
            $sdk->createSqs(['version' => '2012-11-05']),
            'test',
            $this->getLogger()
        );
    }

    private function getValidInvoiceData()
    {
        return [
            [
                'source' => 'SwsEc',
                'invoice_id' => 'INV-1234ABCD',
                'invoice_date' => '2020-01-21T08:54:09Z',
                'order_id' => '1234567',
                'transaction_reference' => 'REF-ABCD1234',
                'payment_provider' => 'BT',
                'moneyworks_debtor_code' => 'WEBC001',
                'subscription_id' => 'SUB-XYZ-ABC',
                'currency' => 'USD',
                'gross_amount' => 0,
                'billing_address' => [
                    'company_name' => 'Company Inc',
                    'person_name' => 'Jo Bloggs',
                    'address_1' => '123 Street Road',
                    'address_2' => 'Suburbia',
                    'address_3' => 'The Stixx',
                    'city' => 'Townsville',
                    'region' => 'Statey',
                    'post_code' => '90210',
                    'country_iso' => 'NZ'
                ],
                'items' => [
                    [
                        'sku' => 'SKU1',
                        'quantity' => 2,
                        'amount_gross' => 2200,
                        'amount_tax' => 200,
                        'amount_net' => 2000,
                        'unit_price' => 1000,
                        'tax_code' => 'V'
                    ]
                ]
            ],
            [
                'source' => 'SwsEc',
                'invoice_id' => 'INV-ABCD-1234',
                'invoice_date' => '2020-01-21T08:54:09Z',
                'order_id' => '1234568',
                'transaction_reference' => 'REF-1234ABCD',
                'payment_provider' => 'BT',
                'moneyworks_debtor_code' => 'WEBC001',
                'subscription_id' => 'SUB-ABC-XYZ',
                'currency' => 'USD',
                'gross_amount' => 0,
                'billing_address' => [
                    'company_name' => 'Company Inc',
                    'person_name' => 'Jo Bloggs',
                    'address_1' => '123 Street Road',
                    'address_2' => 'Suburbia',
                    'address_3' => 'The Stixx',
                    'city' => 'Townsville',
                    'region' => 'Statey',
                    'post_code' => '90210',
                    'country_iso' => 'NZ'
                ],
                'items' => [
                    [
                        'sku' => 'SKU1',
                        'quantity' => 1,
                        'amount_gross' => 1100,
                        'amount_tax' => 100,
                        'amount_net' => 1000,
                        'unit_price' => 1000,
                        'tax_code' => 'V'
                    ]
                ]
            ]
        ];
    }
}
