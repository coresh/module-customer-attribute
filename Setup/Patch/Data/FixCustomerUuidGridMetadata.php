<?php
declare(strict_types=1);

namespace Coresh\CustomerAttribute\Setup\Patch\Data;

use Magento\Customer\Model\Customer;
use Magento\Customer\Setup\CustomerSetupFactory;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\Patch\DataPatchInterface;

/**
 * Repairs customer UUID attribute metadata for Admin customer grid indexing.
 *
 * Purpose:
 * - Fixes existing installations where the original `uuid` customer attribute metadata
 *   was saved with `is_visible = 0` in `customer_eav_attribute`.
 * - Keeps the UUID available to Magento customer grid indexing while keeping it out
 *   of editable Admin customer forms.
 *
 * Important implementation details:
 * - The UUID value is stored in the static `customer_entity.uuid` column.
 * - `is_visible = 1` is required so Magento customer metadata and the customer grid
 *   indexer can include the attribute in `customer_grid_flat`.
 * - `used_in_forms` remains empty so the attribute is not rendered as an editable
 *   field in Admin customer forms.
 * - Runtime immutability is still enforced by the CustomerRepository plugin.
 *
 * Magento Open Source / Adobe Commerce notes:
 * - This patch is safe for both Magento Open Source and Adobe Commerce 2.4.7+.
 * - The patch is idempotent and can be applied repeatedly without changing UUID values.
 *
 * @author: Dmitrii Dmitriev
 * @link: https://www.upwork.com/freelancers/dmitriid15
 */
class FixCustomerUuidGridMetadata implements DataPatchInterface
{
    /**
     * @param ModuleDataSetupInterface $moduleDataSetup Setup connection wrapper used by Magento patches.
     * @param CustomerSetupFactory $customerSetupFactory Factory for customer EAV setup operations.
     */
    public function __construct(
        private readonly ModuleDataSetupInterface $moduleDataSetup,
        private readonly CustomerSetupFactory $customerSetupFactory
    ) {
    }

    /**
     * Apply corrected UUID attribute metadata for customer grid indexing.
     *
     * @return void
     */
    public function apply(): void
    {
        $connection = $this->moduleDataSetup->getConnection();
        $connection->startSetup();

        try {
            $customerSetup = $this->customerSetupFactory->create(['setup' => $this->moduleDataSetup]);
            $attributeId = (int) $customerSetup->getAttributeId(Customer::ENTITY, 'uuid');

            if ($attributeId <= 0) {
                return;
            }

            $connection->update(
                $this->moduleDataSetup->getTable('customer_eav_attribute'),
                [
                    'is_visible' => 1,
                    'is_used_in_grid' => 1,
                    'is_visible_in_grid' => 1,
                    'is_filterable_in_grid' => 1,
                    'is_searchable_in_grid' => 1,
                ],
                ['attribute_id = ?' => $attributeId]
            );

            $connection->update(
                $this->moduleDataSetup->getTable('eav_attribute'),
                [
                    'backend_type' => 'static',
                    'is_required' => 0,
                    'is_unique' => 1,
                ],
                ['attribute_id = ?' => $attributeId]
            );
        } finally {
            $connection->endSetup();
        }
    }

    /**
     * Run after the original attribute metadata patch.
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
     * No aliases are required for this repair patch.
     *
     * @return array<string>
     */
    public function getAliases(): array
    {
        return [];
    }
}
