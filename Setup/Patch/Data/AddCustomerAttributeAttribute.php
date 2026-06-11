<?php declare(strict_types=1);

namespace Coresh\CustomerAttribute\Setup\Patch\Data;

use Magento\Customer\Model\Customer;
use Magento\Customer\Setup\CustomerSetupFactory;
use Magento\Eav\Api\AttributeRepositoryInterface;
use Magento\Framework\Setup\Patch\DataPatchInterface;
use Magento\Framework\Setup\ModuleDataSetupInterface;

/**
 * Adds customer UUID attribute metadata.
 *
 * Purpose:
 * - Registers `uuid` as a customer attribute so Magento customer metadata,
 *   customer grid indexing, and service-layer customer objects can work with it.
 * - Uses `type => static` because the physical value lives in `customer_entity.uuid`.
 *
 * Important implementation details:
 * - The database column and unique constraint are declared in `etc/db_schema.xml`.
 * - The EAV `unique` flag is kept as metadata, but production uniqueness is enforced
 *   by the database unique constraint, not by UI validation.
 * - The attribute is intentionally not assigned to editable Admin forms.
 *
 * Magento Open Source / Adobe Commerce notes:
 * - Customer EAV metadata is available in both editions.
 * - B2B/Company modules in Adobe Commerce do not change this storage strategy.
 *
 *
 * @author: Dmitrii Dmitriev
 * @link: https://www.upwork.com/freelancers/dmitriid15
 */
class AddCustomerAttributeAttribute implements DataPatchInterface
{
    public const ATTRIBUTE_CODE = 'uuid';

    /**
     * @param ModuleDataSetupInterface $moduleDataSetup Setup connection wrapper used by Magento patches.
     * @param CustomerSetupFactory $customerSetupFactory Factory for customer EAV setup operations.
     * @param AttributeRepositoryInterface $attributeRepository Repository used to persist attribute metadata.
     */
    public function __construct(
        private readonly ModuleDataSetupInterface $moduleDataSetup,
        private readonly CustomerSetupFactory $customerSetupFactory,
        private readonly AttributeRepositoryInterface $attributeRepository
    ) {}

    /**
     * Apply the customer UUID attribute metadata.
     *
     * @return void
     */
    public function apply(): void
    {
        $connection = $this->moduleDataSetup->getConnection();
        $connection->startSetup();

        $customerSetup = $this->customerSetupFactory->create(['setup' => $this->moduleDataSetup]);
        $attributeId = $customerSetup->getAttributeId(Customer::ENTITY, self::ATTRIBUTE_CODE);

        if (!$attributeId) {
            $customerSetup->addAttribute(
                Customer::ENTITY,
                self::ATTRIBUTE_CODE,
                [
                    'type' => 'static',
                    'label' => 'UUID',
                    'input' => 'text',
                    'required' => false,
                    'visible' => true,
                    'system' => false,
                    'user_defined' => false,
                    'unique' => true,
                    'position' => 999,
                    'is_used_in_grid' => true,
                    'is_visible_in_grid' => true,
                    'is_filterable_in_grid' => true,
                    'is_searchable_in_grid' => true,
                ]
            );
        }

        $attribute = $customerSetup->getEavConfig()->getAttribute(Customer::ENTITY, self::ATTRIBUTE_CODE);
        $attribute->setData('used_in_forms', []);
        $attribute->setData('is_used_in_grid', true);
        $attribute->setData('is_visible_in_grid', true);
        $attribute->setData('is_filterable_in_grid', true);
        $attribute->setData('is_searchable_in_grid', true);
        $attribute->setData('is_visible', true);
        $attribute->setData('is_required', false);
        $attribute->setData('is_unique', true);

        $this->attributeRepository->save($attribute);

        $connection->endSetup();
    }

    /**
     * This patch has no data-patch dependency because db_schema.xml is applied before data patches.
     *
     * @return array<class-string>
     */
    public static function getDependencies(): array
    {
        return [];
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
