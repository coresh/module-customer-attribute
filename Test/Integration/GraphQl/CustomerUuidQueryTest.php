<?php
declare(strict_types=1);

namespace Coresh\CustomerAttribute\Test\Integration\GraphQl;

use Magento\Customer\Api\AccountManagementInterface;
use Magento\Customer\Api\Data\CustomerInterfaceFactory;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Integration\Model\Oauth\TokenFactory;
use Magento\TestFramework\Helper\Bootstrap;
use Magento\TestFramework\TestCase\GraphQlAbstract;

/**
 * GraphQL integration tests for `customer { uuid }`.
 *
 * @magentoAppArea graphql
 * @magentoAppIsolation enabled
 * @magentoDbIsolation enabled
  *
 *
 * @author: Dmitrii Dmitriev
 * @link: https://www.upwork.com/freelancers/dmitriid15
 */
class CustomerUuidQueryTest extends GraphQlAbstract
{
    /**
     * Verify authenticated customer query returns UUID.
     *
     * @return void
     */
    public function testAuthenticatedCustomerCanReadUuid(): void
    {
        $objectManager = Bootstrap::getObjectManager();
        $customerFactory = $objectManager->get(CustomerInterfaceFactory::class);
        $accountManagement = $objectManager->get(AccountManagementInterface::class);
        $tokenFactory = $objectManager->get(TokenFactory::class);

        $email = 'graphql-uuid-' . uniqid('', true) . '@example.com';
        $password = 'Password123!';

        $customer = $customerFactory->create();
        $customer->setWebsiteId(1);
        $customer->setEmail($email);
        $customer->setFirstname('GraphQL');
        $customer->setLastname('UUID');

        $savedCustomer = $accountManagement->createAccount($customer, $password);
        $token = $tokenFactory->create()->createCustomerToken((int)$savedCustomer->getId())->getToken();

        $response = $this->graphQlQuery(
            '{ customer { email uuid } }',
            [],
            '',
            ['Authorization' => 'Bearer ' . $token]
        );

        self::assertSame($email, $response['customer']['email']);
        self::assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/',
            $response['customer']['uuid']
        );
    }

    /**
     * Verify guest access is rejected by Magento customer authorization.
     *
     * @return void
     */
    public function testGuestCannotReadUuid(): void
    {
        $this->expectException(\Exception::class);
        $this->graphQlQuery('{ customer { uuid } }');
    }
}
