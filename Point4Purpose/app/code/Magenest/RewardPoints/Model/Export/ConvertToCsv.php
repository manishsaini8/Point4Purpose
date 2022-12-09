<?php
/**
 * Created by PhpStorm.
 * User: khanh
 * Date: 23-07-2020
 * Time: 14:57
 */

namespace Magenest\RewardPoints\Model\Export;

use Magento\Framework\Exception\LocalizedException;

/**
 * Class ConvertToCsv
 * @package Magenest\RewardPoints\Model\Export
 */
class ConvertToCsv extends \Magento\Ui\Model\Export\ConvertToCsv
{

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
     * Returns CSV file
     *
     * @return array
     * @throws LocalizedException
     */
    public function getCsvFile()
    {
        $component = $this->filter->getComponent();

        $name = sha1(microtime());
        $file = 'export/'. $component->getName() . $name . '.csv';

        $this->filter->prepareComponent($component);
        $this->filter->applySelectionOnTargetProvider();
        $dataProvider = $component->getContext()->getDataProvider();
        $fields = $this->metadataProvider->getFields($component);
        $options = $this->metadataProvider->getOptions();

        $this->directory->create('export');
        $stream = $this->directory->openFile($file, 'w+');
        $stream->lock();
        $stream->writeCsv($this->metadataProvider->getHeaders($component));
        $i = 1;
        $searchCriteria = $dataProvider->getSearchCriteria()
            ->setCurrentPage($i)
            ->setPageSize($this->pageSize);
        $totalCount = (int) $dataProvider->getSearchResult()->getTotalCount();
        while ($totalCount > 0) {
            $items = $dataProvider->getSearchResult()->getItems();
           
           
		   
            foreach ($items as $item) {
				$object_manager = \Magento\Framework\App\ObjectManager::getInstance();
				$pointBlock = $object_manager->create('\Magenest\RewardPoints\Block\Customer\Points');	
				 $current_points = $item->getData('points_total') - $pointBlock->getPendingPoints($item['customer_id']);
			     $current_points = $current_points - $pointBlock->getDonation($item['customer_id']);
				 $item->setData('points_current', $current_points);
				 $item->setData('points_donation', $pointBlock->getDonation($item['customer_id']));
				 $item->setData('points_pending', $pointBlock->getPendingPoints($item['customer_id']));
				 if ($component->getName() == 'rewardpoints_rule_listing') {
                    $status = $item->getData('status');
                    if ($status == self::STATUS) {
                        $item->setData('status', self::$inactive);
                    } else {
                        $item->setData('status', self::$active);
                    }
                    $ruleType = $item->getData('rule_type');
                    if ($ruleType == self::TYPE_PRODUCT) {
                        $item->setData('rule_type', self::$typeProduct);
                    } else {
                        $item->setData('rule_type', self::$typeBehaviour);
                    }

                } elseif ($component->getName() == 'rewardpoints_transaction_listing') {
                    $item->setData('insertion_date', date('Y-m-d', strtotime($item->getData('insertion_date'))));

                }

                if(!empty($item['points_change']) && $item['product_id'] == 2){
                    $exp =  $item['points_change']/100;
                }else{
                    $exp  = '-';
                }

                $item->setData('expired_date',$exp);
                $this->metadataProvider->convertDate($item, $component->getName());
                $stream->writeCsv($this->metadataProvider->getRowData($item, $fields, $options));
            }
            $searchCriteria->setCurrentPage(++$i);
            $totalCount = $totalCount - $this->pageSize;
        }
        $stream->unlock();
        $stream->close();

        return [
            'type' => 'filename',
            'value' => $file,
            'rm' => true  // can delete file after use
        ];
    }
}
