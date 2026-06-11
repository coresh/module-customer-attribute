<?php
declare(strict_types=1);

namespace Coresh\CustomerAttribute\Model\ResourceModel;

use Magento\Framework\App\ResourceConnection;

/**
 * Resource model for customer UUID persistence.
 *
 * Purpose:
 * - Centralizes all direct access to `customer_entity.uuid`.
 * - Uses Magento's DB adapter and bound values instead of raw interpolated SQL.
 * - Keeps business services independent from table names and SQL details.
  *
 *
 * @author: Dmitrii Dmitriev
 * @link: https://www.upwork.com/freelancers/dmitriid15
 */
class CustomerUuid
{
    private const CUSTOMER_TABLE = 'customer_entity';
    private const ID_COLUMN = 'entity_id';
    private const UUID_COLUMN = 'uuid';
    private const UNIQUE_REFERENCE_ID = 'CORESH_CUSTOMER_ENTITY_UUID_UNQ';

    /**
     * @param ResourceConnection $resource Magento resource connection service.
     */
    public function __construct(
        private readonly ResourceConnection $resource
    ) {
    }

    /**
     * Return the current UUID for a customer ID.
     *
     * @param int $customerId Customer entity ID.
     * @return string|null UUID string, or null when no UUID is stored.
     */
    public function getByCustomerId(int $customerId): ?string
    {
        $connection = $this->resource->getConnection();
        $select = $connection->select()
            ->from($this->getCustomerTable(), [self::UUID_COLUMN])
            ->where(self::ID_COLUMN . ' = :customer_id')
            ->limit(1);

        $value = $connection->fetchOne($select, ['customer_id' => $customerId]);

        return is_string($value) && $value !== '' ? $value : null;
    }

    /**
     * Fetch customer IDs that do not have a UUID yet.
     *
     * @param int $limit Maximum number of IDs to fetch.
     * @return int[] Customer entity IDs.
     */
    public function getCustomerIdsWithoutUuid(int $limit): array
    {
        $connection = $this->resource->getConnection();
        $select = $connection->select()
            ->from($this->getCustomerTable(), [self::ID_COLUMN])
            ->where(self::UUID_COLUMN . ' IS NULL OR ' . self::UUID_COLUMN . ' = ?', '')
            ->order(self::ID_COLUMN . ' ASC')
            ->limit($limit);

        return array_map('intval', $connection->fetchCol($select));
    }

    /**
     * Check whether a UUID is already assigned to any customer.
     *
     * @param string $uuid UUID value to check.
     * @return bool True when the UUID already exists.
     */
    public function isUuidAssigned(string $uuid): bool
    {
        $connection = $this->resource->getConnection();
        $select = $connection->select()
            ->from($this->getCustomerTable(), ['count' => new \Zend_Db_Expr('COUNT(*)')])
            ->where(self::UUID_COLUMN . ' = :uuid');

        return (int)$connection->fetchOne($select, ['uuid' => $uuid]) > 0;
    }

    /**
     * Persist UUID only when the customer row still has no UUID.
     *
     * @param int $customerId Customer entity ID.
     * @param string $uuid UUID to persist.
     * @return int Number of affected rows.
     */
    public function updateUuidIfEmpty(int $customerId, string $uuid): int
    {
        $connection = $this->resource->getConnection();

        return $connection->update(
            $this->getCustomerTable(),
            [self::UUID_COLUMN => $uuid],
            [
                self::ID_COLUMN . ' = ?' => $customerId,
                '(' . self::UUID_COLUMN . ' IS NULL OR ' . self::UUID_COLUMN . ' = ?)' => '',
            ]
        );
    }

    /**
     * Detect whether an exception is likely caused by this module's UUID unique constraint.
     *
     * @param \Throwable $exception Exception from the persistence layer.
     * @return bool True when the exception mentions this module's UUID unique index.
     */
    public function isUuidConstraintViolation(\Throwable $exception): bool
    {
        do {
            $message = $exception->getMessage();
            if (str_contains($message, self::UNIQUE_REFERENCE_ID) || str_contains($message, self::UUID_COLUMN)) {
                return true;
            }
            $exception = $exception->getPrevious();
        } while ($exception instanceof \Throwable);

        return false;
    }

    /**
     * Return the resolved customer table name, including any configured DB prefix.
     *
     * @return string Fully resolved table name.
     */
    private function getCustomerTable(): string
    {
        return $this->resource->getTableName(self::CUSTOMER_TABLE);
    }
}
