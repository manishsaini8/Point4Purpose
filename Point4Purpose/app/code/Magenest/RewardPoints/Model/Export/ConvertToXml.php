<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Magenest\RewardPoints\Model\Export;

use Magento\Framework\Exception\LocalizedException;
/**
 * Class ConvertToXml
 */
class ConvertToXml extends \Magento\Ui\Model\Export\ConvertToXml
{
    /**
     * @var DirectoryList
     */
    const TYPE_PRODUCT = 1;

    const STATUS = 0;

    /**
     * @var string
     */
    private static $inactive = 'Inactive';

    /**
     * @var string
     */
    private static $active = 'Active';

    /**
     * @var string
     */
    private static $typeProduct = 'Product Rule';

    /**
     * @var string
     */
    private static $typeBehaviour = 'Behaviour Rule';

    /**
     * Returns Filters with options
     *
     * @return array
     */
    
    public function getXmlFile()
    {
        $component = $this->filter->getComponent();

        $name = md5(microtime());
        $file = 'export/'. $component->getName() . $name . '.xml';

        $this->filter->prepareComponent($component);
        $this->filter->applySelectionOnTargetProvider();

        $component->getContext()->getDataProvider()->setLimit(0, 0);

        /** @var SearchResultInterface $searchResult */
        $searchResult = $component->getContext()->getDataProvider()->getSearchResult();

        /** @var DocumentInterface[] $searchResultItems */
        $searchResultItems = $searchResult->getItems();
			
        $this->prepareItems($component->getName(), $searchResultItems);
        /** @var SearchResultIterator $searchResultIterator */
        $searchResultIterator = $this->iteratorFactory->create(['items' => $searchResultItems]);
      foreach ($searchResultIterator as $item) {
				$object_manager = \Magento\Framework\App\ObjectManager::getInstance();
				$pointBlock = $object_manager->create('\Magenest\RewardPoints\Block\Customer\Points');
				 $current_points = $item->getData('points_total') - $pointBlock->getPendingPoints($item['customer_id']);
			     $current_points = $current_points - $pointBlock->getDonation($item['customer_id']);
				 $item->setData('points_current', $current_points);
				 $item->setData('points_donation', $pointBlock->getDonation($item['customer_id']));
				 $item->setData('points_pending', $pointBlock->getPendingPoints($item['customer_id']));
				
			}
			
			//die('tets');
        /** @var Excel $excel */
        $excel = $this->excelFactory->create(
            [
                'iterator' => $searchResultIterator,
                'rowCallback'=> [$this, 'getRowData'],
            ]
        );

        $this->directory->create('export');
        $stream = $this->directory->openFile($file, 'w+');
        $stream->lock();

        $excel->setDataHeader($this->metadataProvider->getHeaders($component));
        $excel->write($stream, $component->getName() . '.xml');

        $stream->unlock();
        $stream->close();

        return [
            'type' => 'filename',
            'value' => $file,
            'rm' => true  // can delete file after use
        ];
    }

    /**
     * @param string $componentName
     * @param array $items
     * @return void
     */
   
}
