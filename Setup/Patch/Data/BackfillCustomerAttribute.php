<?php
declare(strict_types=1);

namespace Coresh\CustomerAttribute\Setup\Patch\Data;

use Coresh\CustomerAttribute\Model\ResourceModel\CustomerUuid;
use Coresh\CustomerAttribute\Model\Uuid\AssignerInterface;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\Patch\DataPatchInterface;
use Psr\Log\LoggerInterface;

/**
 * Backfills UUID values for existing customers.
 *
 * Purpose:
 * - Ensures all pre-existing customer rows receive a UUID during module installation/upgrade.
 * - Runs in batches to avoid loading the whole customer table into memory.
 * - Keeps the patch idempotent: existing non-empty UUID values are not overwritten.
 *
 * Important implementation details:
 * - This patch relies on `customer_entity.uuid` already existing from declarative schema.
 * - UUID values are written directly through a resource model with bound values because
 *   repository saves would trigger unnecessary customer business logic for every row.
 * - Duplicate UUID collisions are retried inside the assigner and protected by the DB unique key.
  *
 *
 * @author: Dmitrii Dmitriev
 * @link: https://www.upwork.com/freelancers/dmitriid15
 */
class BackfillCustomerAttribute implements DataPatchInterface
{
    private const BATCH_SIZE = 500;

    /**
     * @param ModuleDataSetupInterface $moduleDataSetup Setup connection wrapper used by Magento patches.
     * @param CustomerUuid $customerUuidResource Resource model for customer UUID persistence.
     * @param AssignerInterface $assigner Service that generates and persists UUID values safely.
     * @param LoggerInterface $logger PSR logger for exceptional backfill diagnostics.
     */
    public function __construct(
        private readonly ModuleDataSetupInterface $moduleDataSetup,
        private readonly CustomerUuid $customerUuidResource,
        private readonly AssignerInterface $assigner,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * Assign UUIDs to customers missing a UUID.
     *
     * @return void
     */
    public function apply(): void
    {
        $connection = $this->moduleDataSetup->getConnection();
        $connection->startSetup();

        try {
            do {
                $customerIds = $this->customerUuidResource->getCustomerIdsWithoutUuid(self::BATCH_SIZE);

                foreach ($customerIds as $customerId) {
                    try {
                        $this->assigner->assignPersistedIfEmpty((int)$customerId);
                    } catch (\Throwable $exception) {
                        $this->logger->error(
                            'Unable to backfill customer UUID.',
                            [
                                'customer_id' => (int)$customerId,
                                'exception' => $exception,
                            ]
                        );
                        throw $exception;
                    }
                }
            } while ($customerIds !== []);
        } finally {
            $connection->endSetup();
        }
    }

    /**
     * Ensure attribute metadata is registered before data backfill starts.
     *
     * @return array<class-string>
     */
    public static function getDependencies(): array
    {
        return [
            AddCustomerAttributeAttribute::class,
        ];
    }

    /**
     * No aliases are required for the first release of this module.
     *
     * @return array<string>
     */
    public function getAliases(): array
    {
        return [];
    }
}
