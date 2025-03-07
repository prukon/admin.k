<?php

/*
 * The MIT License
 *
 * Copyright (c) 2024 "YooMoney", NBСO LLC
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in
 * all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
 * THE SOFTWARE.
 */

namespace Tests\YooKassa\Request\Invoices;

use Exception;
use Tests\YooKassa\AbstractTestCase;
use Datetime;
use YooKassa\Helpers\Random;
use YooKassa\Model\Invoice\LineItem;
use YooKassa\Model\Metadata;
use YooKassa\Model\MonetaryAmount;
use YooKassa\Request\Invoices\CreateInvoiceRequest;
use YooKassa\Request\Invoices\CreateInvoiceRequestBuilder;

/**
 * CreateInvoiceRequestTest
 *
 * @category    ClassTest
 * @author      cms@yoomoney.ru
 * @link        https://yookassa.ru/developers/api
 */
class CreateInvoiceRequestBuilderTest extends AbstractTestCase
{
    protected CreateInvoiceRequest $object;

    /**
     * @return CreateInvoiceRequest
     */
    protected function getTestInstance(): CreateInvoiceRequest
    {
        return new CreateInvoiceRequest();
    }

    /**
     * Test property "payment_data"
     * @dataProvider validPaymentDataDataProvider
     * @param mixed $value
     *
     * @return void
     * @throws Exception
     */
    public function testPaymentData(mixed $value): void
    {
        $builder = CreateInvoiceRequest::builder();

        $builder->setOptions($this->getRequiredData('payment_data'));

        $builder->setPaymentData($value);
        $instance = $builder->build();
        self::assertNotNull($instance->getPaymentData());
        self::assertNotNull($instance->payment_data);
        self::assertEquals($value, is_array($value) ? $instance->getPaymentData()->toArray() : $instance->getPaymentData());
        self::assertEquals($value, is_array($value) ? $instance->payment_data->toArray() : $instance->payment_data);
    }

    /**
     * Test invalid property "payment_data"
     * @dataProvider invalidPaymentDataDataProvider
     * @param mixed $value
     * @param string $exceptionClass
     *
     * @return void
     */
    public function testInvalidPaymentData(mixed $value, string $exceptionClass): void
    {
        $builder = CreateInvoiceRequest::builder();
        $builder->setOptions($this->getRequiredData('payment_data'));

        $this->expectException($exceptionClass);
        $builder->setPaymentData($value);
    }

    /**
     * @return array[]
     * @throws Exception
     */
    public function validPaymentDataDataProvider(): array
    {
        $instance = $this->getTestInstance();
        return $this->getValidDataProviderByType($instance->getValidator()->getRulesByPropName('_payment_data'));
    }

    /**
     * @return array[]
     * @throws Exception
     */
    public function invalidPaymentDataDataProvider(): array
    {
        $instance = $this->getTestInstance();
        return $this->getInvalidDataProviderByType($instance->getValidator()->getRulesByPropName('_payment_data'));
    }

    /**
     * Test property "cart"
     * @dataProvider validCartDataProvider
     * @param mixed $value
     *
     * @return void
     * @throws Exception
     */
    public function testCart(mixed $value): void
    {
        $builder = CreateInvoiceRequest::builder();
        $builder->setOptions($this->getRequiredData('cart'));
        $builder->setCart($value);

        $instance = $builder->build();

        self::assertNotNull($instance->getCart());
        self::assertNotNull($instance->cart);
        foreach ($value as $key => $element) {
            if (is_array($element) && !empty($element)) {
                self::assertEquals($element, $instance->getCart()[$key]->toArray());
                self::assertEquals($element, $instance->cart[$key]->toArray());
                self::assertIsArray($instance->getCart()[$key]->toArray());
                self::assertIsArray($instance->cart[$key]->toArray());
            }
            if (is_object($element) && !empty($element)) {
                self::assertEquals($element, $instance->getCart()->get($key));
                self::assertIsObject($instance->getCart()->get($key));
                self::assertIsObject($instance->cart->get($key));
                self::assertIsObject($instance->getCart());
                self::assertIsObject($instance->cart);
            }
        }
        self::assertCount(count($value), $instance->getCart());
        self::assertCount(count($value), $instance->cart);

        $builder->setOptions($this->getRequiredData('cart'));
        $builder->setCart($value);
        $builder->addCartItem(new LineItem([
            'description' => 'Test product',
            'quantity' => 1,
            'price' => ['value' => Random::int(1, 100), 'currency' => 'RUB'],
        ]));

        $instance = $builder->build();
        self::assertCount(count($value) + 1, $instance->getCart());
        self::assertCount(count($value) + 1, $instance->cart);
    }

    /**
     * Test invalid property "cart"
     * @dataProvider invalidCartDataProvider
     * @param mixed $value
     * @param string $exceptionClass
     *
     * @return void
     */
    public function testInvalidCart(mixed $value, string $exceptionClass): void
    {
        $builder = CreateInvoiceRequest::builder();
        $builder->setOptions($this->getRequiredData('cart'));

        $this->expectException($exceptionClass);
        $builder->setCart($value);
    }

    /**
     * @return array[]
     * @throws Exception
     */
    public function validCartDataProvider(): array
    {
        $instance = $this->getTestInstance();
        return $this->getValidDataProviderByType($instance->getValidator()->getRulesByPropName('_cart'));
    }

    /**
     * @return array[]
     * @throws Exception
     */
    public function invalidCartDataProvider(): array
    {
        $instance = $this->getTestInstance();
        return $this->getInvalidDataProviderByType($instance->getValidator()->getRulesByPropName('_cart'));
    }

    /**
     * Test property "delivery_method_data"
     * @dataProvider validDeliveryMethodDataDataProvider
     * @param mixed $value
     *
     * @return void
     * @throws Exception
     */
    public function testDeliveryMethodData(mixed $value): void
    {
        $builder = CreateInvoiceRequest::builder();
        $builder->setOptions($this->getRequiredData('delivery_method_data'));

        $instance = $builder->build();

        self::assertEmpty($instance->getDeliveryMethodData());
        self::assertEmpty($instance->delivery_method_data);
        $instance->setDeliveryMethodData($value);
        self::assertEquals($value, is_array($value) ? $instance->getDeliveryMethodData()->toArray() : $instance->getDeliveryMethodData());
        self::assertEquals($value, is_array($value) ? $instance->delivery_method_data->toArray() : $instance->delivery_method_data);
        if (!empty($value)) {
            self::assertNotNull($instance->getDeliveryMethodData());
            self::assertNotNull($instance->delivery_method_data);
        }
    }

    /**
     * Test invalid property "delivery_method_data"
     * @dataProvider invalidDeliveryMethodDataDataProvider
     * @param mixed $value
     * @param string $exceptionClass
     *
     * @return void
     */
    public function testInvalidDeliveryMethodData(mixed $value, string $exceptionClass): void
    {
        $builder = CreateInvoiceRequest::builder();
        $builder->setOptions($this->getRequiredData('delivery_method_data'));

        $this->expectException($exceptionClass);
        $builder->setDeliveryMethodData($value);
    }

    /**
     * @return array[]
     * @throws Exception
     */
    public function validDeliveryMethodDataDataProvider(): array
    {
        $instance = $this->getTestInstance();
        return $this->getValidDataProviderByType($instance->getValidator()->getRulesByPropName('_delivery_method_data'));
    }

    /**
     * @return array[]
     * @throws Exception
     */
    public function invalidDeliveryMethodDataDataProvider(): array
    {
        $instance = $this->getTestInstance();
        return $this->getInvalidDataProviderByType($instance->getValidator()->getRulesByPropName('_delivery_method_data'));
    }

    /**
     * Test property "expires_at"
     * @dataProvider validExpiresAtDataProvider
     * @param mixed $value
     *
     * @return void
     * @throws Exception
     */
    public function testExpiresAt(mixed $value): void
    {
        $builder = CreateInvoiceRequest::builder();
        $builder->setOptions($this->getRequiredData('expires_at'));
        $builder->setExpiresAt($value);

        $instance = $builder->build();

        self::assertNotNull($instance->getExpiresAt());
        self::assertNotNull($instance->expires_at);
        if ($value instanceof Datetime) {
            self::assertEquals($value, $instance->getExpiresAt());
            self::assertEquals($value, $instance->expires_at);
        } else {
            self::assertEquals(new Datetime($value), $instance->getExpiresAt());
            self::assertEquals(new Datetime($value), $instance->expires_at);
        }
    }

    /**
     * Test invalid property "expires_at"
     * @dataProvider invalidExpiresAtDataProvider
     * @param mixed $value
     * @param string $exceptionClass
     *
     * @return void
     */
    public function testInvalidExpiresAt(mixed $value, string $exceptionClass): void
    {
        $builder = CreateInvoiceRequest::builder();
        $builder->setOptions($this->getRequiredData('expires_at'));

        $this->expectException($exceptionClass);
        $builder->setExpiresAt($value);
    }

    /**
     * @return array[]
     * @throws Exception
     */
    public function validExpiresAtDataProvider(): array
    {
        $instance = $this->getTestInstance();
        return $this->getValidDataProviderByType($instance->getValidator()->getRulesByPropName('_expires_at'));
    }

    /**
     * @return array[]
     * @throws Exception
     */
    public function invalidExpiresAtDataProvider(): array
    {
        $instance = $this->getTestInstance();
        return $this->getInvalidDataProviderByType($instance->getValidator()->getRulesByPropName('_expires_at'));
    }

    /**
     * Test property "locale"
     * @dataProvider validLocaleDataProvider
     * @param mixed $value
     *
     * @return void
     * @throws Exception
     */
    public function testLocale(mixed $value): void
    {
        $builder = CreateInvoiceRequest::builder();
        $builder->setOptions($this->getRequiredData('locale'));
        $builder->setLocale($value);

        $instance = $builder->build();

        self::assertEquals($value, $instance->getLocale());
        self::assertEquals($value, $instance->locale);
        if (!empty($value)) {
            self::assertNotNull($instance->getLocale());
            self::assertNotNull($instance->locale);
        }
    }

    /**
     * Test invalid property "locale"
     * @dataProvider invalidLocaleDataProvider
     * @param mixed $value
     * @param string $exceptionClass
     *
     * @return void
     */
    public function testInvalidLocale(mixed $value, string $exceptionClass): void
    {
        $builder = CreateInvoiceRequest::builder();
        $builder->setOptions($this->getRequiredData('locale'));

        $this->expectException($exceptionClass);
        $builder->setLocale($value);
    }

    /**
     * @return array[]
     * @throws Exception
     */
    public function validLocaleDataProvider(): array
    {
        $instance = $this->getTestInstance();
        return $this->getValidDataProviderByType($instance->getValidator()->getRulesByPropName('_locale'));
    }

    /**
     * @return array[]
     * @throws Exception
     */
    public function invalidLocaleDataProvider(): array
    {
        $instance = $this->getTestInstance();
        return $this->getInvalidDataProviderByType($instance->getValidator()->getRulesByPropName('_locale'));
    }

    /**
     * Test property "description"
     * @dataProvider validDescriptionDataProvider
     * @param mixed $value
     *
     * @return void
     * @throws Exception
     */
    public function testDescription(mixed $value): void
    {
        $builder = CreateInvoiceRequest::builder();
        $builder->setOptions($this->getRequiredData('description'));
        $builder->setDescription($value);

        $instance = $builder->build();

        self::assertEquals($value, $instance->getDescription());
        self::assertEquals($value, $instance->description);
        if (!empty($value)) {
            self::assertNotNull($instance->getDescription());
            self::assertNotNull($instance->description);
            self::assertLessThanOrEqual(128, is_string($instance->getDescription()) ? mb_strlen($instance->getDescription()) : $instance->getDescription());
            self::assertLessThanOrEqual(128, is_string($instance->description) ? mb_strlen($instance->description) : $instance->description);
        }
    }

    /**
     * Test invalid property "description"
     * @dataProvider invalidDescriptionDataProvider
     * @param mixed $value
     * @param string $exceptionClass
     *
     * @return void
     */
    public function testInvalidDescription(mixed $value, string $exceptionClass): void
    {
        $builder = CreateInvoiceRequest::builder();
        $builder->setOptions($this->getRequiredData('description'));

        $this->expectException($exceptionClass);
        $builder->setDescription($value);
    }

    /**
     * @return array[]
     * @throws Exception
     */
    public function validDescriptionDataProvider(): array
    {
        $instance = $this->getTestInstance();
        return $this->getValidDataProviderByType($instance->getValidator()->getRulesByPropName('_description'));
    }

    /**
     * @return array[]
     * @throws Exception
     */
    public function invalidDescriptionDataProvider(): array
    {
        $instance = $this->getTestInstance();
        return $this->getInvalidDataProviderByType($instance->getValidator()->getRulesByPropName('_description'));
    }

    /**
     * Test property "metadata"
     * @dataProvider validMetadataDataProvider
     * @param mixed $value
     *
     * @return void
     * @throws Exception
     */
    public function testMetadata(mixed $value): void
    {
        $builder = CreateInvoiceRequest::builder();
        $builder->setOptions($this->getRequiredData('metadata'));
        $builder->setMetadata($value);

        $instance = $builder->build();

        if (!empty($value)) {
            self::assertNotNull($instance->getMetadata());
            self::assertNotNull($instance->metadata);
            foreach ($value as $key => $element) {
                if (!empty($element)) {
                    self::assertEquals($element, $instance->getMetadata()[$key]);
                    self::assertEquals($element, $instance->metadata[$key]);
                    self::assertIsObject($instance->getMetadata());
                    self::assertIsObject($instance->metadata);
                }
            }
            self::assertCount(count($value), $instance->getMetadata());
            self::assertCount(count($value), $instance->metadata);
            if ($instance->getMetadata() instanceof Metadata) {
                self::assertEquals($value, $instance->getMetadata()->toArray());
                self::assertEquals($value, $instance->metadata->toArray());
                self::assertCount(count($value), $instance->getMetadata());
                self::assertCount(count($value), $instance->metadata);
            }
        }
    }

    /**
     * Test invalid property "metadata"
     * @dataProvider invalidMetadataDataProvider
     * @param mixed $value
     * @param string $exceptionClass
     *
     * @return void
     */
    public function testInvalidMetadata(mixed $value, string $exceptionClass): void
    {
        $builder = CreateInvoiceRequest::builder();
        $builder->setOptions($this->getRequiredData('metadata'));

        $this->expectException($exceptionClass);
        $builder->setMetadata($value);
    }

    /**
     * @return array[]
     * @throws Exception
     */
    public function validMetadataDataProvider(): array
    {
        $instance = $this->getTestInstance();
        return $this->getValidDataProviderByType($instance->getValidator()->getRulesByPropName('_metadata'));
    }

    /**
     * @return array[]
     * @throws Exception
     */
    public function invalidMetadataDataProvider(): array
    {
        $instance = $this->getTestInstance();
        return $this->getInvalidDataProviderByType($instance->getValidator()->getRulesByPropName('_metadata'));
    }

    /**
     * @param string|null $testingProperty
     *
     * @return array
     */
    protected function getRequiredData(?string $testingProperty = null): array
    {
        $result = [];
        if ('payment_data' !== $testingProperty) {
            $result['payment_data'] = [
                'amount' => new MonetaryAmount(Random::int(1, 100), 'RUB'),
            ];
        }
        if ('cart' !== $testingProperty) {
            $result['cart'] = [
                [
                    'description' => Random::str(1, 100),
                    'price' => new MonetaryAmount(Random::int(1, 100), 'RUB'),
                    'quantity' => Random::int(1, 10),
                ]
            ];
        }
        if ('expires_at' !== $testingProperty) {
            $result['expires_at'] = new DateTime('+1 month');
        }

        return $result;
    }
}
