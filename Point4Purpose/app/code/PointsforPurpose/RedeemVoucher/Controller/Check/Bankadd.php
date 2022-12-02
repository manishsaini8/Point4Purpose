<?php

namespace PointsforPurpose\RedeemVoucher\Controller\Check;

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
class Bankadd extends Action
{
    public $customerFactory;

    /**
     * @var PageFactory
     */
    public $resultPageFactory;

    protected $_customer;

    /**
     * @var \Magenest\RewardPoints\Helper\Data
     */
    protected $helper;

    /**
     * @var ForwardFactory
     */
    protected $resultForwardFactory;

    /**
     * @var \Magenest\RewardPoints\Model\TransactionFactory
     */
    protected $transactionFactory;

    protected $resultJsonFactory;

    /**
     * @var Transaction
     */
    protected $_transactionResource;

    /**
     * View constructor.
     *
     * @param Context $context
     * @param PageFactory $resultPageFactory
     * @param ForwardFactory $resultForwardFactory
     * @param Data $helperData
     */
    public function __construct(
        Context $context,
        PageFactory $resultPageFactory,
        Transaction $transactionResource,
        \Magento\Customer\Model\CustomerFactory $customerFactory,
        \Magento\Customer\Model\Customer $customers,
        \Magenest\RewardPoints\Model\TransactionFactory $transactionFactory,
        \Magenest\RewardPoints\Helper\Data $help,
        ForwardFactory $resultForwardFactory,
        JsonFactory $resultJsonFactory
    ) {
        $this->resultPageFactory = $resultPageFactory;
        $this->helper            = $help;
        $this->customerFactory  = $customerFactory;
        $this->_customer = $customers;
        $this->transactionFactory = $transactionFactory;
        $this->_transactionResource = $transactionResource;
        $this->resultJsonFactory   = $resultJsonFactory;
        $this->resultForwardFactory = $resultForwardFactory;

        parent::__construct($context);
    }

    /**
     * @return ResponseInterface|ResultInterface|Page
     */
    public function execute()
    {
        $data = $this->getRequest()->getParams();
        $result = $this->resultJsonFactory->create();

        $resultOutput = [
            'message' => 'Something went wrong please try again!',
            'status' => 'error'
        ];

        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
		$withdraw_limit = $objectManager->get('Magento\Framework\App\Config\ScopeConfigInterface')->getValue('lofcouponcode/general_settings/set_withdraw_limit');
        if($withdraw_limit != ''){
			$withdraw_limit = $withdraw_limit;
		}else{
			$withdraw_limit = 3000;	
		}
		$customerSession = $objectManager->get('Magento\Customer\Model\Session');
        if ($customerSession->isLoggedIn()) {
            $customer = $objectManager->create('\Magenest\RewardPoints\Block\Customer\Points');
            $availPoint = 0;
            $_donation = $customer->getDonation();
            $availPoint = $customer->getAccount()->getPointsCurrent() - $_donation;
            if ($availPoint > $withdraw_limit) {
                $accountFactory = $objectManager->create('\Magenest\RewardPoints\Model\AccountFactory');
                $accCollection = $objectManager->create('\Magenest\RewardPoints\Model\ResourceModel\Account\CollectionFactory');

                $tp = $customer->getAccount()->getPointsTotal();
                $uToken = $customerSession->getCustomer()->getCardToken();

                $redeemResponse = false;
                if ($uToken) {
					
                    $addBankResponse = $this->addBankDetails($data, $uToken);
                    if (isset($addBankResponse['id']) && $addBankResponse['id'] != '') {
                        $redeemResponse = $this->redeem($data, $uToken);
					
                    }
                    $payoutResponse = $this->payout($uToken);
                }

                if (isset($redeemResponse['success']) && $redeemResponse['success'] == 1) {
                    // withdrawn entry
                    $calculatedPoint = $data['amount'] * 100;
                    $customerId = $customerSession->getCustomer()->getId();
                    $model = $this->transactionFactory->create();
                    $model->setData([
                        'customer_id' => $customerId,
                        'rule_id' => 8,
                        'product_id' => 3,
                        'points_change' => $calculatedPoint,
                        'points_after' => $tp - $calculatedPoint,
                        'comment' => 'Withdrawn Points',
                    ]);
                    $this->_transactionResource->save($model);

                    $accountModel = $accountFactory->create();
                    $account = $accCollection->create()->addFieldToFilter('customer_id', $customerId)->getFirstItem();
                    if ($account->getId()) {
                        $accountModel->load($account->getId());
                    }

                    $data = [
                        'customer_id' => $customerId,
                        'store_id' => 1,
                        'points_total' => $account->getData('points_total') - $calculatedPoint,
                        'points_current' => $account->getData('points_current') - $calculatedPoint,
                        'points_spent' => $account->getData('points_spent') + $calculatedPoint,
                    ];
                    $accountModel->addData($data)->save();
                } else if (isset($redeemResponse['errors'])) {
                    $errorMsg = null;
                    if (is_array($redeemResponse['errors'])) {
                        $errorMsg = implode(', ', $redeemResponse['errors']);
                    } else if (is_string($redeemResponse['errors'])) {
                        $errorMsg = $redeemResponse['errors'];
                    }
                    if ($errorMsg) {
                        $resultOutput['message'] = $errorMsg;
                    }
                }

                if (isset($redeemResponse['success']) && $redeemResponse['success'] == 1) {
                    $resultOutput = [
                        'message' => 'Withdraw request submitted successfully!',
                        'status' => 'success',
                    ];
                }
            } else {
                $resultOutput['message'] = "Your 'Available Points' must reach ".$withdraw_limit." to be able to withdraw.";
            }
        }

        return $result->setData($resultOutput);
    }

    /**
     * @param $data
     * @param $uToken
     * @return mixed
     */
    private function addBankDetails($data, $uToken)
    {
        // bank data prepar
        $jsonData = json_encode([
            "account" => $data['cardnumber'],
            "countryCode" => 'au',
            "bsb" => $data['bsb'],
        ]);
        // add bank details
        $curl = curl_init();
        curl_setopt_array($curl, array(
            CURLOPT_URL => 'https://api.pokitpal.com/v1/UserBank/Add',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_POSTFIELDS => $jsonData,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_HTTPHEADER => array(
                "Content-Type: application/json",
                "Authorization: Bearer $uToken",
            ),
        ));

        $response = curl_exec($curl);
        curl_close($curl);
        return json_decode($response, true);
    }

    /**
     * @param $data
     * @param $uToken
     * @return mixed
     */
    private function redeem($data, $uToken)
    {
        // reedem / transfer amount
        $curl = curl_init();
        $jsonData = json_encode(['amount' => $data['amount']]);
        curl_setopt_array($curl, array(
            CURLOPT_URL => 'https://api.pokitpal.com/v1/Cashback/Redeem',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_POSTFIELDS => $jsonData,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',

            CURLOPT_HTTPHEADER => array(
                "Content-Type: application/json",
                "Authorization: Bearer $uToken",
            ),
        ));

        $response = curl_exec($curl);
        curl_close($curl);
        return json_decode($response, true);
    }

    /**
     * @param $uToken
     * @return mixed
     */
    private function payout($uToken)
    {
        // payout API
        $curl = curl_init();
        curl_setopt_array($curl, array(
            CURLOPT_URL => 'https://api.pokitpal.com/v1/Cashback/Payout',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'GET',
            CURLOPT_HTTPHEADER => array(
                "Content-Type: application/json",
                "Authorization: Bearer $uToken",
            ),
        ));
        //payout response remaining
        $response = curl_exec($curl);
        curl_close($curl);
        return json_decode($response, true);
    }
}
