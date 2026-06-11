<?php
declare(strict_types=1);

namespace Coresh\CustomerAttribute\Model\Resolver;

use Coresh\CustomerAttribute\Model\Uuid\AssignerInterface;
use Magento\Authorization\Model\UserContextInterface;
use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Exception\GraphQlAuthorizationException;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;

/**
 * GraphQL resolver for `Customer.uuid`.
 *
 * Purpose:
 * - Exposes only the authenticated customer's own UUID.
 * - Does not create a guest-accessible custom query.
 * - Uses the GraphQL context customer identity instead of accepting customer ID input.
 *
 * Security behavior:
 * - Guests receive the standard customer authorization error.
 * - The resolver never reveals whether another customer exists.
  *
 *
 * @author: Dmitrii Dmitriev
 * @link: https://www.upwork.com/freelancers/dmitriid15
 */
class CustomerAttribute implements ResolverInterface
{
    /**
     * @param AssignerInterface $assigner Service used to guarantee UUID exists for the authenticated customer.
     */
    public function __construct(
        private readonly AssignerInterface $assigner
    ) {
    }

    /**
     * Resolve the authenticated customer's UUID.
     *
     * @param Field $field GraphQL field metadata.
     * @param mixed $context GraphQL execution context containing user identity.
     * @param ResolveInfo $info GraphQL resolve info.
     * @param array<string, mixed>|null $value Parent customer resolver value.
     * @param array<string, mixed>|null $args Field arguments; unused because no customer ID input is accepted.
     * @return string Customer UUID.
     * @throws GraphQlAuthorizationException When the request is not authenticated as a customer.
     */
    public function resolve(
        Field $field,
        $context,
        ResolveInfo $info,
        ?array $value = null,
        ?array $args = null
    ): string {
        $customerId = (int)$context->getUserId();
        $userType = (int)$context->getUserType();

        if ($customerId <= 0 || $userType !== UserContextInterface::USER_TYPE_CUSTOMER) {
            throw new GraphQlAuthorizationException(__('The current customer isn\'t authorized.'));
        }

        return $this->assigner->assignPersistedIfEmpty($customerId);
    }
}
