<?php

declare(strict_types=1);

namespace Scandiweb\Test\Setup\Patch\Data;

use Magento\Catalog\Api\CategoryLinkManagementInterface;
use Magento\Catalog\Api\Data\ProductInterfaceFactory;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\Product\Attribute\Source\Status;
use Magento\Catalog\Model\Product\Type;
use Magento\Catalog\Model\Product\Visibility;
use Magento\Eav\Setup\EavSetup;
use Magento\Framework\App\State;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\InputException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\Patch\DataPatchInterface;
use Magento\Framework\Setup\Patch\PatchRevertableInterface;
use Magento\Framework\Validation\ValidationException;
use Magento\InventoryApi\Api\Data\SourceItemInterface;
use Magento\InventoryApi\Api\Data\SourceItemInterfaceFactory;
use Magento\InventoryApi\Api\SourceItemsSaveInterface;

class CreateSimpleProduct implements DataPatchInterface, PatchRevertableInterface
{
    /**
    * @var ModuleDataSetupInterface
    */
    protected $setup;

    /**
     * @var ProductInterfaceFactory
     */
    protected $productInterfaceFactory;

    /**
     * @var ProductRepositoryInterface
     */
    protected $productRepository;

    /**
     * @var State
     */
    protected $appState;

    /**
     * @var SourceItemInterfaceFactory
     */
    protected $sourceItemFactory;

    /**
     * @var SourceItemsSaveInterface
     */
    protected $sourceItemsSaveInterface;

    /**
     * @var EavSetup
     */
    protected $eavSetup;

    /**
     * @var array
     */
    protected $sourceItems = [];

    /**
     * Migration patch constructor.
     *
     * @param ModuleDataSetupInterface $setup
     * @param ProductInterfaceFactory $productInterfaceFactory
     * @param ProductRepositoryInterface $productRepository
     * @param SourceItemInterfaceFactory $sourceItemFactory
     * @param SourceItemsSaveInterface $sourceItemsSaveInterface
     * @param State $appState
     * @param EavSetup $eavSetup
     * @param CategoryLinkManagementInterface $categoryLink
     */
    public function __construct(
        ModuleDataSetupInterface $setup,
        ProductInterfaceFactory $productInterfaceFactory,
        ProductRepositoryInterface $productRepository,
        State $appState,
        EavSetup $eavSetup,
        SourceItemInterfaceFactory $sourceItemFactory,
        SourceItemsSaveInterface $sourceItemsSaveInterface,
        CategoryLinkManagementInterface $categoryLink
    ) {
        $this->appState = $appState;
        $this->productInterfaceFactory = $productInterfaceFactory;
        $this->productRepository = $productRepository;
        $this->setup = $setup;
        $this->eavSetup = $eavSetup;
        $this->sourceItemFactory = $sourceItemFactory;
        $this->sourceItemsSaveInterface = $sourceItemsSaveInterface;
        $this->categoryLink = $categoryLink;
    }

    /**
      * Add new product
      */
    public function apply()
    {
        $this->appState->emulateAreaCode('adminhtml', [$this, 'execute']);
    }

    /**
     * @throws CouldNotSaveException
     * @throws InputException
     * @throws LocalizedException
     * @throws NoSuchEntityException
     * @throws ValidationException
     */
    public function execute()
    {
        $product = $this->productInterfaceFactory->create();

        if ($product->getIdBySku('towel')) {
            return;
        }

        $this->setup->getConnection()->startSetup();

        $attributeSetId = $this->eavSetup->getAttributeSetId(Product::ENTITY, 'Default');
        $product->setTypeId(Type::TYPE_SIMPLE)
            ->setAttributeSetId($attributeSetId)
            ->setName('Towel')
            ->setUrlKey('towel')
            ->setSku('towel')
            ->setPrice(5.90)
            ->setVisibility(Visibility::VISIBILITY_BOTH)
            ->setStatus(Status::STATUS_ENABLED)
            ->setStockData(['use_config_manage_stock' => 0, 'is_qty_decimal' => 0, 'is_in_stock' => 1]);
        $product = $this->productRepository->save($product);

        $sourceItemFactory = $this->sourceItemFactory->create();
        $sourceItemFactory->setSourceCode('default');
        $sourceItemFactory->setQuantity(10);
        $sourceItemFactory->setSku($product->getSku());
        $sourceItemFactory->setStatus(SourceItemInterface::STATUS_IN_STOCK);
        $this->sourceItems[] = $sourceItemFactory;

        $this->sourceItemsSaveInterface->execute($this->sourceItems);

        $this->categoryLink->assignProductToCategories($product->getSku(), [2]);
        $this->setup->getConnection()->endSetup();
    }

    /**
     * {@inheritDoc}
     */
    public static function getDependencies()
    {
        return [];
    }

    public function revert() {}

    /**
     * {@inheritDoc}
     */
    public function getAliases()
    {
        return [];
    }
}
