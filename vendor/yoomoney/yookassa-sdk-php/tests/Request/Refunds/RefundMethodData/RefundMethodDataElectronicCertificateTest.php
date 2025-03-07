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

namespace Tests\YooKassa\Request\Refunds\RefundMethodData;

use Exception;
use Tests\YooKassa\AbstractTestCase;
use Datetime;
use YooKassa\Model\Metadata;
use YooKassa\Request\Refunds\RefundMethodData\RefundMethodDataElectronicCertificate;

/**
 * RefundMethodDataElectronicCertificateTest
 *
 * @category    ClassTest
 * @author      cms@yoomoney.ru
 * @link        https://yookassa.ru/developers/api
*/
class RefundMethodDataElectronicCertificateTest extends AbstractTestCase
{
    protected RefundMethodDataElectronicCertificate $object;

    /**
    * @return RefundMethodDataElectronicCertificate
    */
    protected function getTestInstance(): RefundMethodDataElectronicCertificate
    {
        return new RefundMethodDataElectronicCertificate();
    }

    /**
    * @return void
    */
    public function testRefundMethodDataElectronicCertificateClassExists(): void
    {
        $this->object = $this->getMockBuilder(RefundMethodDataElectronicCertificate::class)->getMockForAbstractClass();
        $this->assertTrue(class_exists(RefundMethodDataElectronicCertificate::class));
        $this->assertInstanceOf(RefundMethodDataElectronicCertificate::class, $this->object);
    }

    /**
    * Test invalid property "type"
    * @dataProvider invalidTypeDataProvider
    * @param mixed $value
    * @param string $exceptionClass
    *
    * @return void
    */
    public function testInvalidType(mixed $value, string $exceptionClass): void
    {
        $instance = $this->getTestInstance();

        $this->expectException($exceptionClass);
        $instance->setType($value);
    }

    /**
    * @return array[]
    * @throws Exception
    */
    public function validTypeDataProvider(): array
    {
        $instance = $this->getTestInstance();
        return $this->getValidDataProviderByType($instance->getValidator()->getRulesByPropName('_type'));
    }

    /**
    * @return array[]
    * @throws Exception
    */
    public function invalidTypeDataProvider(): array
    {
        $instance = $this->getTestInstance();
        return $this->getInvalidDataProviderByType($instance->getValidator()->getRulesByPropName('_type'));
    }

    /**
    * Test property "electronic_certificate"
    * @dataProvider validElectronicCertificateDataProvider
    * @param mixed $value
    *
    * @return void
    * @throws Exception
    */
    public function testElectronicCertificate(mixed $value): void
    {
        $instance = $this->getTestInstance();
        self::assertEmpty($instance->getElectronicCertificate());
        self::assertEmpty($instance->electronic_certificate);
        $instance->setElectronicCertificate($value);
        self::assertEquals($value, is_array($value) ? $instance->getElectronicCertificate()->toArray() : $instance->getElectronicCertificate());
        self::assertEquals($value, is_array($value) ? $instance->electronic_certificate->toArray() : $instance->electronic_certificate);
        if (!empty($value)) {
            self::assertNotNull($instance->getElectronicCertificate());
            self::assertNotNull($instance->electronic_certificate);
        }
    }

    /**
    * Test invalid property "electronic_certificate"
    * @dataProvider invalidElectronicCertificateDataProvider
    * @param mixed $value
    * @param string $exceptionClass
    *
    * @return void
    */
    public function testInvalidElectronicCertificate(mixed $value, string $exceptionClass): void
    {
        $instance = $this->getTestInstance();

        $this->expectException($exceptionClass);
        $instance->setElectronicCertificate($value);
    }

    /**
    * @return array[]
    * @throws Exception
    */
    public function validElectronicCertificateDataProvider(): array
    {
        $instance = $this->getTestInstance();
        return $this->getValidDataProviderByType($instance->getValidator()->getRulesByPropName('_electronic_certificate'));
    }

    /**
    * @return array[]
    * @throws Exception
    */
    public function invalidElectronicCertificateDataProvider(): array
    {
        $instance = $this->getTestInstance();
        return $this->getInvalidDataProviderByType($instance->getValidator()->getRulesByPropName('_electronic_certificate'));
    }

    /**
    * Test property "articles"
    * @dataProvider validArticlesDataProvider
    * @param mixed $value
    *
    * @return void
    * @throws Exception
    */
    public function testArticles(mixed $value): void
    {
        $instance = $this->getTestInstance();
        self::assertEmpty($instance->getArticles());
        self::assertEmpty($instance->articles);
        self::assertIsObject($instance->getArticles());
        self::assertIsObject($instance->articles);
        self::assertCount(0, $instance->getArticles());
        self::assertCount(0, $instance->articles);
        $instance->setArticles($value);
        if (!empty($value)) {
            self::assertNotNull($instance->getArticles());
            self::assertNotNull($instance->articles);
            foreach ($value as $key => $element) {
                if (is_array($element) && !empty($element)) {
                    self::assertEquals($element, $instance->getArticles()[$key]->toArray());
                    self::assertEquals($element, $instance->articles[$key]->toArray());
                    self::assertIsArray($instance->getArticles()[$key]->toArray());
                    self::assertIsArray($instance->articles[$key]->toArray());
                }
                if (is_object($element) && !empty($element)) {
                    self::assertEquals($element, $instance->getArticles()->get($key));
                    self::assertIsObject($instance->getArticles()->get($key));
                    self::assertIsObject($instance->articles->get($key));
                    self::assertIsObject($instance->getArticles());
                    self::assertIsObject($instance->articles);
                }
            }
            self::assertCount(count($value), $instance->getArticles());
            self::assertCount(count($value), $instance->articles);
        }
    }

    /**
    * Test invalid property "articles"
    * @dataProvider invalidArticlesDataProvider
    * @param mixed $value
    * @param string $exceptionClass
    *
    * @return void
    */
    public function testInvalidArticles(mixed $value, string $exceptionClass): void
    {
        $instance = $this->getTestInstance();

        $this->expectException($exceptionClass);
        $instance->setArticles($value);
    }

    /**
    * @return array[]
    * @throws Exception
    */
    public function validArticlesDataProvider(): array
    {
        $instance = $this->getTestInstance();
        return $this->getValidDataProviderByType($instance->getValidator()->getRulesByPropName('_articles'));
    }

    /**
    * @return array[]
    * @throws Exception
    */
    public function invalidArticlesDataProvider(): array
    {
        $instance = $this->getTestInstance();
        return $this->getInvalidDataProviderByType($instance->getValidator()->getRulesByPropName('_articles'));
    }
}
