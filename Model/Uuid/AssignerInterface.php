<?php
declare(strict_types=1);

namespace Coresh\CustomerAttribute\Model\Uuid;

use Magento\Customer\Api\Data\CustomerInterface;

/**
 * Assigns immutable UUID values to customers.
  *
 *
 * @author: Dmitrii Dmitriev
 * @link: https://www.upwork.com/freelancers/dmitriid15
 */
interface AssignerInterface
{
    /**
     * Ensure a customer data object contains the correct UUID before repository save.
     *
     * @param CustomerInterface $customer Customer data object being saved.
     * @return CustomerInterface Same customer object with protected UUID state.
     */
    public function assignToCustomer(CustomerInterface $customer): CustomerInterface;

    /**
     * Persist a UUID for an existing customer row when it is empty.
     *
     * @param int $customerId Customer entity ID.
     * @return string Existing or newly assigned UUID.
     */
    public function assignPersistedIfEmpty(int $customerId): string;

    /**
     * Generate a UUID that is not currently assigned.
     *
     * @return string Unique UUID candidate.
     */
    public function generateUnique(): string;
}
