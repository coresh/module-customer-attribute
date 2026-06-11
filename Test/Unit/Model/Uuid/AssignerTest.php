<?php
declare(strict_types=1);

namespace Coresh\CustomerAttribute\Test\Unit\Model\Uuid;

use Coresh\CustomerAttribute\Model\ResourceModel\CustomerUuid;
use Coresh\CustomerAttribute\Model\Uuid\Assigner;
use Coresh\CustomerAttribute\Model\Uuid\GeneratorInterface;
use Coresh\CustomerAttribute\Setup\Patch\Data\AddCustomerAttributeAttribute;
use Magento\Customer\Api\Data\CustomerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * Unit tests for customer UUID assignment rules.
  *
 *
 * @author: Dmitrii Dmitriev
 * @link: https://www.upwork.com/freelancers/dmitriid15
 */
class AssignerTest extends TestCase
{
    /**
     * Verify a new customer gets a generated UUID and user-submitted values are ignored.
     *
     * @return void
     */
    public function testAssignToNewCustomerGeneratesUuid(): void
    {
        $uuid = '550e8400-e29b-41d4-a716-446655440000';
        $customer = $this->createMock(CustomerInterface::class);
        $customer->method('getId')->willReturn(null);
        $customer->expects(self::once())
            ->method('setCustomAttribute')
            ->with(AddCustomerAttributeAttribute::ATTRIBUTE_CODE, $uuid);

        $generator = $this->createGenerator($uuid);
        $resource = $this->createMock(CustomerUuid::class);
        $resource->method('isUuidAssigned')->with($uuid)->willReturn(false);

        $assigner = new Assigner($generator, $resource, $this->createMock(LoggerInterface::class));
        $assigner->assignToCustomer($customer);
    }

    /**
     * Verify an existing persisted UUID is preserved.
     *
     * @return void
     */
    public function testAssignToExistingCustomerPreservesPersistedUuid(): void
    {
        $uuid = '550e8400-e29b-41d4-a716-446655440000';
        $customer = $this->createMock(CustomerInterface::class);
        $customer->method('getId')->willReturn(10);
        $customer->expects(self::once())
            ->method('setCustomAttribute')
            ->with(AddCustomerAttributeAttribute::ATTRIBUTE_CODE, $uuid);

        $generator = $this->createGenerator($uuid);
        $resource = $this->createMock(CustomerUuid::class);
        $resource->method('getByCustomerId')->with(10)->willReturn($uuid);

        $assigner = new Assigner($generator, $resource, $this->createMock(LoggerInterface::class));
        $assigner->assignToCustomer($customer);
    }

    /**
     * Create a UUID generator mock.
     *
     * @param string $uuid UUID value returned by generate().
     * @return GeneratorInterface&MockObject
     */
    private function createGenerator(string $uuid): GeneratorInterface
    {
        $generator = $this->createMock(GeneratorInterface::class);
        $generator->method('generate')->willReturn($uuid);
        $generator->method('isValid')->willReturnCallback(
            static fn (string $value): bool => $value === $uuid
        );

        return $generator;
    }
}
