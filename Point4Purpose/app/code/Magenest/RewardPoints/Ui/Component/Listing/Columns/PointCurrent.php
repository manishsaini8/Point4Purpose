<?php

namespace Magenest\RewardPoints\Ui\Component\Listing\Columns;

use Magento\Framework\View\Element\UiComponent\ContextInterface;
use Magento\Framework\View\Element\UiComponentFactory;
use Magento\Ui\Component\Listing\Columns\Column;
use Magento\Framework\UrlInterface;

/**
 * Class RuleActions
 * @package Magenest\RewardPoints\Ui\Component\Listing\Columns
 */
class PointCurrent extends Column
{
    /**
     * @var UrlInterface
     */
    protected $urlBuilder;

    /**
     * RuleActions constructor.
     *
     * @param UrlInterface $urlBuilder
     * @param ContextInterface $context
     * @param UiComponentFactory $uiComponentFactory
     * @param array $components
     * @param array $data
     */
    public function __construct(
        UrlInterface $urlBuilder,
        ContextInterface $context,
        UiComponentFactory $uiComponentFactory,
        array $components,
        array $data
    ) {
        $this->urlBuilder = $urlBuilder;
        parent::__construct($context, $uiComponentFactory, $components, $data);
    }

    /**
     * @param array $dataSource
     *
     * @return array
     */
    public function prepareDataSource(array $dataSource)
    {
        $object_manager = \Magento\Framework\App\ObjectManager::getInstance();
        $pointBlock = $object_manager->create('\Magenest\RewardPoints\Block\Customer\Points');
        if (isset($dataSource['data']['items'])) {
            foreach ($dataSource['data']['items'] as &$item) {
               if(!empty($item['points_current'])){
                   $item['points_donation'] = $pointBlock->getDonation($item['customer_id']);
                   $item['points_current'] = $item['points_total'] - $pointBlock->getDonation($item['customer_id']);
                   $item['points_current'] = $item['points_current'] - $pointBlock->getPendingPoints($item['customer_id']);
			   }
            }
        }

        return $dataSource;
    }
}
