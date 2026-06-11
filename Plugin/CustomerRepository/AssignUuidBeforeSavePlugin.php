<?php
declare(strict_types=1);

namespace Coresh\CustomerAttribute\Plugin\CustomerRepository;

use Coresh\CustomerAttribute\Model\ResourceModel\CustomerUuid;
use Coresh\CustomerAttribute\Model\Uuid\AssignerInterface;
use Magento\Customer\Api\Data\CustomerInterface;
use Magento\Customer\Model\ResourceModel\CustomerRepository;
use Psr\Log\LoggerInterface;

/**
 * Assigns and protects customer UUID during repository save.
 *
 * Purpose:
 * - Covers Admin, storefront, REST, GraphQL, and programmatic customer repository saves.
 * - Prevents user-submitted UUID values from replacing persisted UUIDs.
 * - Retries a UUID collision only when the database reports this module's unique index.
 *
 * Why an around plugin is used here:
 * - Around plugins are avoided by default, but retrying the original save after a rare UUID
 *   unique-key collision requires controlled re-execution of the observed method.
  *
 *
 * @author: Dmitrii Dmitriev
 * @link: https://www.upwork.com/freelancers/dmitriid15
 */
class AssignUuidBeforeSavePlugin
{
    private const MAX_SAVE_RETRIES = 2;

    /**
     * @param AssignerInterface $assigner Business service that applies immutable UUID rules.
     * @param CustomerUuid $customerUuidResource Resource helper for identifying UUID constraint collisions.
     * @param LoggerInterface $logger PSR logger for collision diagnostics.
     */
    public function __construct(
        private readonly AssignerInterface $assigner,
        private readonly CustomerUuid $customerUuidResource,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * Assign UUID before customer save and retry if a generated UUID collides.
     *
     * @param CustomerRepository $subject Customer repository being intercepted.
     * @param callable $proceed Original save callable.
     * @param CustomerInterface $customer Customer data object.
     * @param string|null $passwordHash Optional password hash passed by core code.
     * @return CustomerInterface Saved customer object.
     * @throws \Throwable Re-throws non-UUID persistence failures or exhausted retries.
     */
    public function aroundSave(
        CustomerRepository $subject,
        callable $proceed,
        CustomerInterface $customer,
        ?string $passwordHash = null
    ): CustomerInterface {
        $lastException = null;

        for ($attempt = 1; $attempt <= self::MAX_SAVE_RETRIES; $attempt++) {
            $this->assigner->assignToCustomer($customer);

            try {
                /** @var CustomerInterface $savedCustomer */
                $savedCustomer = $proceed($customer, $passwordHash);
                $this->assigner->assignToCustomer($savedCustomer);

                return $savedCustomer;
            } catch (\Throwable $exception) {
                $lastException = $exception;

                if (!$this->customerUuidResource->isUuidConstraintViolation($exception) || $attempt === self::MAX_SAVE_RETRIES) {
                    throw $exception;
                }

                $this->logger->warning(
                    'Generated customer UUID collided with an existing value. Retrying customer save.',
                    ['attempt' => $attempt]
                );
            }
        }

        throw $lastException;
    }
}
