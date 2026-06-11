<?php
declare(strict_types=1);

namespace Coresh\CustomerAttribute\Test\Integration;

use Coresh\CustomerAttribute\Model\ResourceModel\CustomerUuid;
use Coresh\CustomerAttribute\Model\Uuid\GeneratorInterface;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Customer\Api\Data\CustomerInterfaceFactory;
use Magento\Framework\ObjectManagerInterface;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;

/**
 * Integration coverage for customer UUID persistence.
 *
 * @magentoAppIsolation enabled
 * @magentoDbIsolation enabled
  *
 *
 * @author: Dmitrii Dmitriev
 * @link: https://www.upwork.com/freelancers/dmitriid15
 */
class CustomerUuidIntegrationTest extends TestCase
{
    private ObjectManagerInterface $objectManager;

    /**
     * Prepare object manager for Magento integration tests.
     *
     * @return void
     */
    protected function setUp(): void
    {
        $this->objectManager = Bootstrap::getObjectManager();
    }

    /**
     * Verify a new customer saved through the repository receives a UUID.
     *
     * @return void
     */
    public function testNewCustomerReceivesUuid(): void
    {
        $customerFactory = $this->objectManager->get(CustomerInterfaceFactory::class);
        $customerRepository = $this->objectManager->get(CustomerRepositoryInterface::class);
        $generator = $this->objectManager->get(GeneratorInterface::class);
        $customerUuidResource = $this->objectManager->get(CustomerUuid::class);

        $customer = $customerFactory->create();
        $customer->setWebsiteId(1);
        $customer->setEmail('uuid-test-' . uniqid('', true) . '@example.com');
        $customer->setFirstname('UUID');
        $customer->setLastname('Test');

        $savedCustomer = $customerRepository->save($customer, 'Password123!');
        $uuid = $customerUuidResource->getByCustomerId((int)$savedCustomer->getId());

        self::assertIsString($uuid);
        self::assertTrue($generator->isValid($uuid));
    }

    /**
     * Verify a persisted UUID is immutable when the customer is updated.
     *
     * @return void
     */
    public function testExistingUuidIsNotOverwritten(): void
    {
        $customerFactory = $this->objectManager->get(CustomerInterfaceFactory::class);
        $customerRepository = $this->objectManager->get(CustomerRepositoryInterface::class);
        $customerUuidResource = $this->objectManager->get(CustomerUuid::class);

        $customer = $customerFactory->create();
        $customer->setWebsiteId(1);
        $customer->setEmail('uuid-immutable-' . uniqid('', true) . '@example.com');
        $customer->setFirstname('UUID');
        $customer->setLastname('Immutable');

        $savedCustomer = $customerRepository->save($customer, 'Password123!');
        $customerId = (int)$savedCustomer->getId();
        $originalUuid = $customerUuidResource->getByCustomerId($customerId);

        $savedCustomer->setCustomAttribute('uuid', '11111111-1111-4111-8111-111111111111');
        $customerRepository->save($savedCustomer);

        self::assertSame($originalUuid, $customerUuidResource->getByCustomerId($customerId));
    }
}
