<?php

namespace PointsforPurpose\Rewards\Controller\Pokitpal;

use Magenest\RewardPoints\Model\ResourceModel\Transaction;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\Controller\Result\ForwardFactory;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\View\Result\Page;
use Magento\Framework\View\Result\PageFactory;

/**
 * Class View
 * @package Mageplaza\Blog\Controller\Author
 */
class Webhook extends Action
{

    /**
     * @var \Magenest\RewardPoints\Model\TransactionFactory
     */
    protected $transactionFactory;

    /**
     * @var Transaction
     */
    protected $_transactionResource;

    /**
     * @var PageFactory
     */
    public $resultPageFactory;

    protected $_customer;

    protected $user;

    /**
     * @var \Magenest\RewardPoints\Helper\Data
     */
    protected $helper;

    public function __construct(
        Context $context,
        PageFactory $resultPageFactory,
        \Magento\User\Model\User $user,
        Transaction                                     $transactionResource,
        \Magenest\RewardPoints\Model\TransactionFactory $transactionFactory,
        \Magento\Customer\Model\Customer                $customers,
        \Magenest\RewardPoints\Helper\Data              $help
    ) {
        $this->resultPageFactory = $resultPageFactory;
        $this->user = $user;
        $this->transactionFactory = $transactionFactory;
        $this->_customer = $customers;
        $this->helper = $help;

        $this->_transactionResource = $transactionResource;
        parent::__construct($context);
    }

    /**
     * @return ResponseInterface|ResultInterface|Page
     */
    public function execute()
    {
        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
        $data = $this->getRequest()->getParams();
		
	
       if(!empty($data['username']) && !empty($data['password'])) {
           $uname = $data['username'];
           $pwd = $data['password'];
           $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
           $st = $objectManager->create('Magento\User\Model\User')->authenticate($uname, $pwd);
           //$webdata = '{"source":"Raiz","offerId":917984,"address":"My Deal","externalAdvertiserId":"1550011","userId":"1550201","offerUseType":"multi","offerUseTypeId":1,"reward":{"transactionId":"4ff6a4b2-ce16-ed11-ab4b-06aa91bea21fw","type":"Cashback","value":5,"created":"2022-08-08T04:00:56.7082325Z","originalTransaction":{"originalAmount":299.8,"computedValue":20,"conversionValue":0,"ruleParameters":"CASHBACK=70","appliedRuleKey":"SYSTEM-LAPSED","operatorTransactionDate":"2022-08-08T12:27:13"}},"walletTransactionState":0,"cardId":0,"status":"unknown","merchantName":"My Deal","isOnline":true,"logoUrl":"https://d1u2dq0qgohzjh.cloudfront.net/prod/UploadFiles/Logos/advertiser_logo.1639036522_1639036522.Jpeg"}';
           $webdata = $data['transaction'];

           $whook = json_decode($webdata,true);
           $pokitpalUid = $whook['userId'];

           $tStatus = $whook['walletTransactionState'];
           $transactionId = $whook['reward']['transactionId'];


           if(!empty($whook['merchantName'])){
               $merchantName = $whook['merchantName'];
               $mname = 'Received point(s) from '.$merchantName;
           }else{
               $mname = 'Received point(s) from card linked transaction';
           }
           $point = $whook['reward']['value'];

           //$st = 1;
               if(!empty($st) && !empty($webdata)){
               $customerObj = $objectManager->create('Magento\Customer\Model\ResourceModel\Customer\Collection');
               $collection = $customerObj->addAttributeToSelect('entity_id')
                   ->addAttributeToFilter('pokitpal_user_id',$pokitpalUid)
                   ->load();

               $c_data=$collection->getData();
			   $customerId=''; // line code add by manish
               if($c_data) {
                   $customerId = $c_data[0]['entity_id'];
               }

               if($customerId !='') {
                   if(in_array($tStatus, [1,2])){

                       $pointsT = $this->helper->getAccount();
                       $pointsT = $pointsT->getData('points_total');

                       $pointChange = $point * 100;
                       $model = $this->transactionFactory->create();
                       $model->setData([
                           'customer_id' => $customerId,
                           'rule_id' => -1,
                           'points_change' => $pointChange,
                           'points_pending' => $pointChange,
                           'product_id' => 1,
                           'comment' => 'Pending Point(s) from '.$whook['merchantName'],
                           'transaction_id' => $transactionId,
                           'points_after' => $pointsT
						   
                       ]);
							
                       $this->_transactionResource->save($model);
					   
					   	   // Code for add Donation rows
						   if($whook['clubDonatedAmount']!=0){
							   $pointsT = $this->helper->getAccount();
								$pointsT = $pointsT->getData('points_total');
							   $clubDonatedAmount = $whook['clubDonatedAmount'];
							   $charitypoint = $clubDonatedAmount * 100;
							   $model->setData([
								   'customer_id' => $customerId,
								   'rule_id' => -1,
								   'points_change' => $charitypoint,                   
								   'product_id' => 2,
								   'comment' => 'Donated point(s) to '.$whook['clubName'],
								   'transaction_id' => $transactionId,
								   'points_donated' => $charitypoint,
								   'points_after' => $pointsT
								  
							   ]);						   
									
							   $this->_transactionResource->save($model);
						
							}
						  // Code end add Donation rows
					   
					
                   }else {
                       if ($tStatus == 0) {

                           $pointChange = $point * 100;
                           $model = $this->transactionFactory->create();
                           $model->setData([
                               'customer_id' => $customerId,
                               'rule_id' => -1,
                               'points_change' => $pointChange,
                               'product_id' => 1,
                               'comment' => $mname
                           ]);

                           $loadByTransaction = $this->transactionFactory->create()->load($transactionId, 'transaction_id');
                           $tData = $loadByTransaction->getData();

                           if(!empty($tData)){
                               $loadByTransaction->setPointsPending(0);
                               $loadByTransaction->Save();
                           }

                           // if($pointChange !== $tr->getData('points_change') && $pointChange > $tr->getData('points_after')){
                           $this->_transactionResource->save($model);

                           // transaction resp
                           $pointAfter = $this->helper->addPointsAccount($customerId, $pointChange, $model->getId());
                           $model->setData('points_after', $pointAfter);
                           // $comment = 'Received point(s) from card linked transaction';
                           $model->setData('comment', $mname);
                           $this->_transactionResource->save($model);
						   
						   // Code for add Donation rows
						   if($whook['clubDonatedAmount']!=0){
							   $pointsT = $this->helper->getAccount();
								$pointsT = $pointsT->getData('points_total');
							   $clubDonatedAmount = $whook['clubDonatedAmount'];
							   $charitypoint = $clubDonatedAmount * 100;
							   $model->setData([
								   'customer_id' => $customerId,
								   'rule_id' => -1,
								   'points_change' => $charitypoint,                   
								   'product_id' => 2,
								   'comment' => 'Donated point(s) to '.$whook['clubName'],
								   'transaction_id' => $transactionId,
								   'points_donated' => $charitypoint,
								   'points_after' => $pointsT
								  
							   ]);						   
									
							   $this->_transactionResource->save($model);
						 
						
							}
						  // Code end add Donation rows
                       }
					 
                   }
				    
                   echo json_encode(['success' => 'data success']);
               }
           }
       }else{
          echo json_encode(['error' => 'please pass data into proper format']);
       }
    }
}
