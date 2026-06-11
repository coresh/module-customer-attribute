<?php
declare(strict_types=1);

namespace Coresh\CustomerAttribute\Model\Uuid;

use Coresh\CustomerAttribute\Model\ResourceModel\CustomerUuid;
use Coresh\CustomerAttribute\Setup\Patch\Data\AddCustomerAttributeAttribute;
use Magento\Customer\Api\Data\CustomerInterface;
use Magento\Framework\Exception\LocalizedException;
use Psr\Log\LoggerInterface;

/**
 * Customer UUID assignment service.
 *
 * Purpose:
 * - Enforces UUID creation and immutability before customer persistence.
 * - Preserves existing database UUIDs for existing customers.
 * - Ignores user-supplied UUIDs for new customers and existing customers without UUIDs.
 *
 * Important implementation details:
 * - DB uniqueness is the final source of truth.
 * - The pre-check in `generateUnique()` reduces collision retries but does not replace the unique index.
 * - The retry limit keeps collision handling deterministic and prevents infinite loops.
  *
 *
 * @author: Dmitrii Dmitriev
 * @link: https://www.upwork.com/freelancers/dmitriid15
 */
class Assigner implements AssignerInterface
{
    private const MAX_RETRIES = 5;

    /**
     * @param GeneratorInterface $generator UUID generation and validation service.
     * @param CustomerUuid $customerUuidResource Resource model for persisted UUID values.
     * @param LoggerInterface $logger PSR logger for exceptional collision diagnostics.
     */
    public function __construct(
        private readonly GeneratorInterface $generator,
        private readonly CustomerUuid $customerUuidResource,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * Ensure a customer object carries an immutable UUID before save.
     *
     * @param CustomerInterface $customer Customer being saved.
     * @return CustomerInterface Same customer object with UUID custom attribute set.
     */
    public function assignToCustomer(CustomerInterface $customer): CustomerInterface
    {
        $customerId = (int)$customer->getId();

        if ($customerId > 0) {
            $persistedUuid = $this->customerUuidResource->getByCustomerId($customerId);
            if ($persistedUuid !== null && $this->generator->isValid($persistedUuid)) {
                $customer->setCustomAttribute(AddCustomerAttributeAttribute::ATTRIBUTE_CODE, $persistedUuid);
                return $customer;
            }
        }

        $customer->setCustomAttribute(AddCustomerAttributeAttribute::ATTRIBUTE_CODE, $this->generateUnique());

        return $customer;
    }

    /**
     * Assign a UUID directly to an existing customer row when the row is empty.
     *
     * @param int $customerId Customer entity ID.
     * @return string Existing or newly created UUID.
     * @throws LocalizedException When a UUID cannot be assigned after retrying collisions.
     */
    public function assignPersistedIfEmpty(int $customerId): string
    {
        $existingUuid = $this->customerUuidResource->getByCustomerId($customerId);
        if ($existingUuid !== null && $this->generator->isValid($existingUuid)) {
            return $existingUuid;
        }

        for ($attempt = 1; $attempt <= self::MAX_RETRIES; $attempt++) {
            $uuid = $this->generateUnique();

            try {
                $affectedRows = $this->customerUuidResource->updateUuidIfEmpty($customerId, $uuid);
                if ($affectedRows === 1) {
                    return $uuid;
                }

                $existingUuid = $this->customerUuidResource->getByCustomerId($customerId);
                if ($existingUuid !== null && $this->generator->isValid($existingUuid)) {
                    return $existingUuid;
                }
            } catch (\Throwable $exception) {
                if (!$this->customerUuidResource->isUuidConstraintViolation($exception) || $attempt === self::MAX_RETRIES) {
                    $this->logger->critical('Customer UUID assignment failed.', ['exception' => $exception]);
                    throw $exception;
                }
            }
        }

        throw new LocalizedException(__('Unable to assign a unique customer UUID.'));
    }

    /**
     * Generate a UUID candidate that is not currently assigned.
     *
     * @return string Unique UUID candidate.
     * @throws LocalizedException When no available candidate is found after retrying.
     */
    public function generateUnique(): string
    {
        for ($attempt = 1; $attempt <= self::MAX_RETRIES; $attempt++) {
            $uuid = $this->generator->generate();
            if (!$this->customerUuidResource->isUuidAssigned($uuid)) {
                return $uuid;
            }
        }

        throw new LocalizedException(__('Unable to generate a unique customer UUID.'));
    }
}
