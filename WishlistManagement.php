<?php

namespace Netsmartz\Customer\Model;

use Exception;
use Magento\Wishlist\Controller\WishlistProvider;
use Magento\Wishlist\Model\ResourceModel\Item\CollectionFactory;
use Magento\Wishlist\Model\WishlistFactory;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Wishlist\Model\ItemFactory;
use Magento\Catalog\Helper\Image;
use Magento\Framework\App\Request\Http;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory as productCollectionFactory;
use Magento\Catalog\Model\Product\Attribute\Repository;
use Magento\Review\Model\ResourceModel\Review\CollectionFactory as ReviewCollectionFactory;
use Magento\Review\Model\Rating\Option\VoteFactory;
use Magento\Store\Model\StoreManagerInterface;

class WishlistManagement
{

    /**
     * @var CollectionFactory
     */
    protected $_wishlistCollectionFactory;
    protected $imageHelper;
    protected $request;

    /**
     * Wishlist item collection
     * @var \Magento\Wishlist\Model\ResourceModel\Item\Collection
     */
    protected $_itemCollection;

    /**
     * @var WishlistRepository
     */
    protected $_wishlistRepository;

    /**
     * @var ProductRepository
     */
    protected $_productRepository;

    /**
     * @var WishlistFactory
     */
    protected $_wishlistFactory;

    /**
     * @var Item
     */
    protected $_itemFactory;
    protected $_productCollection;
    protected $atrributesRepository;
    protected $_reviewCollection;
    protected $_voteFactory;
    protected $_storeManager;

    /**
     * @param CollectionFactory $wishlistCollectionFactory
     * @param \Magento\Catalog\Model\ProductFactory $productFactory
     * @param \Magento\Framework\Math\Random $mathRandom
     * @param \Magento\Framework\Stdlib\DateTime $dateTime
     * @param ProductRepositoryInterface $productRepository
     */
    public function __construct(
        CollectionFactory $wishlistCollectionFactory,
        WishlistFactory $wishlistFactory,
        ProductRepositoryInterface $productRepository,
        ItemFactory $itemFactory,
        Image $imageHelper,
        Http $request,
        productCollectionFactory $_productCollection,
        Repository $atrributesRepository,
        \Magento\Eav\Model\Config $eavConfig,
        ReviewCollectionFactory $reviewCollection,
        VoteFactory $voteFactory,
        StoreManagerInterface $storeManager

    ) {
        $this->_wishlistCollectionFactory   = $wishlistCollectionFactory;
        $this->_productRepository           = $productRepository;
        $this->_wishlistFactory             = $wishlistFactory;
        $this->_itemFactory                 = $itemFactory;
        $this->imageHelper                  = $imageHelper;
        $this->request                      = $request;
        $this->_productCollection           = $_productCollection;
        $this->atrributesRepository         = $atrributesRepository;
        $this->eavConfig                    = $eavConfig;
        $this->_reviewCollection            = $reviewCollection;
        $this->_voteFactory                 = $voteFactory;
        $this->_storeManager                = $storeManager;
    }

    /**
     * Get wishlist collection
     * @param int $customerId
     * @return array WishlistData
     */
    public function getWishlistForCustomer($customerId, $page_size, $sortBy, $soryByValue, $storeId)
    {
        if (empty($customerId) || !isset($customerId) || $customerId == "") {
            $message = __('Id required');
            $status = false;
            $response[] = [
                "message" => $message,
                "status"  => $status
            ];
            return $response;
        } else {
            $wishlistData = [];
            $allParameters = $this->request->getParams();
            
            $wishlistItems = $this->_wishlistCollectionFactory->create()->addCustomerIdFilter($customerId);
            return $this->getFilterWishlist($wishlistItems, $allParameters, $page_size, $sortBy, $soryByValue, $storeId);
        }
    }

    /**
     * Add wishlist item for the customer
     * @param int $customerId
     * @param int $productIdId
     * @return array|bool
     *
     */
    public function addWishlistForCustomer($customerId, $productId)
    {
        if ($productId == null) {
            $message = __('Invalid product, Please select a valid product');
            $status = false;
            $response[] = [
                "message" => $message,
                "status"  => $status
            ];
            return $response;
        }
        try {
            $product = $this->_productRepository->getById($productId);
           
        } catch (Exception $e) {
            return false;
        }
        try {
            $wishlist = $this->_wishlistFactory->create()
                ->loadByCustomerId($customerId, true);

            $wishlist->addNewItem($product);
           
            $wishlist->save();
        } catch (Exception $e) {
            return $e->getMessage();
        }
        $message = __('Item added to wishlist.');
        $status = true;
        $response[] = [
            "message" => $message,
            "status"  => $status
        ];
        return $response;
    }


    public function addtoWishlistbySku($customerId, $sku)
    {
        if ($sku == null) {
            $message = __('Invalid product, Please select a valid product');
            $status = false;
            $response[] = [
                "message" => $message,
                "status"  => $status
            ];
            return $response;
        }
        try {
            $product = $this->_productRepository->get($sku);
        } catch (Exception $e) {
            return false;
        }
        try {
            $wishlist = $this->_wishlistFactory->create()
                ->loadByCustomerId($customerId, true);

            $wishlist->addNewItem($product);
           
            $wishlist->save();
        } catch (Exception $e) {
            return $e->getMessage();
        }
        $message = __('Item added to wishlist.');
        $status = true;
        $response[] = [
            "message" => $message,
            "status"  => $status
        ];
        return $response;
    }

    /**
     * Delete wishlist item for customer
     * @param int $customerId
     * @param int $productIdId
     * @return array
     *
     */
    public function deleteWishlistForCustomer($customerId, $wishlistItemId)
    {

        $message = null;
        $status = null;
        if ($wishlistItemId == null) {
            $message = __('Invalid wishlist item, Please select a valid item');
            $status = false;
            $response[] = [
                "message" => $message,
                "status"  => $status
            ];
            return $response;
        }
        $item = $this->_itemFactory->create()->load($wishlistItemId);
        if (!$item->getId()) {
            $message = __('The requested Wish List Item doesn\'t exist .');
            $status = false;

            $response[] = [
                "message" => $message,
                "status"  => $status
            ];
            return $response;
        }
        $wishlistId = $item->getWishlistId();
        $wishlist = $this->_wishlistFactory->create();

        if ($wishlistId) {
            $wishlist->load($wishlistId);
        } elseif ($customerId) {
            $wishlist->loadByCustomerId($customerId, true);
        }
        if (!$wishlist) {
            $message = __('The requested Wish List Item doesn\'t exist .');
            $status = false;
            $response[] = [
                "message" => $message,
                "status"  => $status
            ];
            return $response;
        }
        if (!$wishlist->getId() || $wishlist->getCustomerId() != $customerId) {
            $message = __('The requested Wish List Item doesn\'t exist .');
            $status = false;
            $response[] = [
                "message" => $message,
                "status"  => $status
            ];
            return $response;
        }
        try {
            $item->delete();
            $wishlist->save();
        } catch (Exception $e) {
            return false;
        }

        $message = __(' Item has been removed from wishlist .');
        $status = true;
        $response[] = [
            "message" => $message,
            "status"  => $status
        ];
        return $response;
    }

    public function getFilterWishlist($collection, $allParameters, $page_size, $sortBy, $soryByValue, $storeId)
    {

        $productIds = null;
        $filterWishlistColl = array();

        foreach ($collection as $item) {
            $productIds[] = $item->getProductId();
        }
       
        $collections = $this->_productCollection->create()->addAttributeToSelect('*')->addIdFilter($productIds);

        if(array_key_exists("name",$allParameters) && $allParameters['name']!=''){
           
            $searchterm = $allParameters['name'];
            $flag = 'false';
            $selectOptions = $this->atrributesRepository->get('brand')->getOptions();
            $brandValue = '';
            $nameArray = ucfirst($searchterm);
            foreach ($selectOptions as $selectOption) {
                $brandLabel = $selectOption->getLabel(); 
                if(stripos($brandLabel, $searchterm) !== FALSE) {
                    $flag = 'true';
                    $brandValue =$brandLabel;
                }
            }

            if($flag == 'true'){
                $collections->addAttributeToFilter([
                    ['attribute' => 'brand', array('eq' =>$this->eavConfig->getAttribute('catalog_product', 'brand')->getSource()->getOptionId($brandValue)), array('null' => true)]]);
            }else{
                $collections->addFieldToFilter('name', ['like' => '%' .$allParameters['name']. '%']);   
            }
        }

        $collections->addAttributeToFilter('visibility', \Magento\Catalog\Model\Product\Visibility::VISIBILITY_BOTH);
        $collections->addAttributeToFilter('status',\Magento\Catalog\Model\Product\Attribute\Source\Status::STATUS_ENABLED);
        $collections->setPageSize($page_size);
        $collections->addStoreFilter($storeId);
        $collections->setOrder($sortBy, $soryByValue);

        if(count($collections)>0){
            $imageUrl = $this->imageHelper->getDefaultPlaceholderUrl('image');
            foreach($collections as $product){
                if($product->getSmallImage() != 'no_selection' && $product->getSmallImage() != ''){
                    $imageUrl = $this->imageHelper->init($product, 'product_page_image_small')
                    ->setImageFile($product->getSmallImage()) // image,small_image,thumbnail
                    ->getUrl();   
                }

                /*Rating Code start*/
                $reviewCollection = $this->_reviewCollection->create()->addStatusFilter(\Magento\Review\Model\Review::STATUS_APPROVED)->addEntityFilter('product',$product->getId())->setDateOrder()->addRateVotes();
                $review_count     = count($reviewCollection);
                $allRatingsAvg    = 0;
                $totalrating      = 0;
                $allRatings       = 0;
                $flag             = 0;

                if ($reviewCollection && count($reviewCollection) > 0) 
                {
                    foreach ($reviewCollection AS $review) 
                    {
                        $review_id = $review->getId();                
                        $ratingCollectionnew = $this->_voteFactory->create()->getResourceCollection()->setReviewFilter(
                        $review_id)->addRatingInfo(
                        $this->_storeManager->getStore()->getId()
                        )->setStoreFilter(
                        $this->_storeManager->getStore()->getId()
                        )->load();    
                        $data = $ratingCollectionnew->getData();                  
                        $flags = 0;               
                        foreach($data as $new)
                        {
                            $flags = $flags + 1 ;
                            $rating_code = $new['rating_code'];                  
                        }
                        $flag = $flags; 

                        $countRatings = count($review->getRatingVotes());
                        if ($countRatings > 0) 
                            {                   
                            foreach($review->getRatingVotes() as $vote) 
                            {
                                $totalrating = $totalrating + $vote->getPercent();                                                    
                            }                                                                             
                        }                   
                    }                                               
                }     

                $final_rating = 0;
                if($flag > 0)
                {
                    $newrating = $totalrating/$review_count;
                    $rating = $newrating/20;
                    $final_rating = $rating/$flag;
                }
                else
                {
                    $final_rating = $totalrating;
                }    

                $ratingResult = number_format($final_rating, 1); 

                /*Rating Code end */
                $filterWishlistColl[$product->getId()]['name'] = $product->getName();
                $filterWishlistColl[$product->getId()]['sku'] = $product->getSku();
                $filterWishlistColl[$product->getId()]['description'] = $product->getShortDescription();
                $filterWishlistColl[$product->getId()]['price'] = number_format((float)$product->getPrice(), 2, '.', '');
                $filterWishlistColl[$product->getId()]['img_src'] = $imageUrl;
                $filterWishlistColl[$product->getId()]['brand'] = $product->getResource()->getAttribute('brand')->setStoreId($storeId)->getFrontend()->getValue($product);
                $filterWishlistColl[$product->getId()]['rating'] = (string)$ratingResult;

            }
            foreach ($collection as $item) {
                if(isset($filterWishlistColl[$item->getProductId()]['name'])){
                    $filterWishlistColl[$item->getProductId()]['wishlist_item_id'] = $item->getWishlistItemId();    
                    $filterWishlistColl[$item->getProductId()]['product_id'] = $item->getProductId();    
                    $filterWishlistColl[$item->getProductId()]['wishlist_id'] = $item->getWishlistId();    
                    $filterWishlistColl[$item->getProductId()]['qty'] = round($item->getQty());
                }
                    
            }
        }
        return $filterWishlistColl;
    }

    public function getCustomerWishlistItems($customerId)
    {
        if (empty($customerId) || !isset($customerId) || $customerId == "") {
            $message = __('Id required');
            $status = false;
            $response[] = [
                "message" => $message,
                "status"  => $status
            ];
            return $response;
        } else {
            $filterWishlistColl = array();
           
            $wishlistItems = $this->_wishlistCollectionFactory->create()->addCustomerIdFilter($customerId);
            
            foreach ($wishlistItems as $item) {
                $filterWishlistColl[$item->getProductId()]['wishlist_item_id'] = $item->getWishlistItemId();    
                $filterWishlistColl[$item->getProductId()]['id'] = $item->getProductId();
                $product = $this->_productRepository->getById($item->getProductId());   
                $filterWishlistColl[$item->getProductId()]['sku'] = $product->getSku();   
            }
            return $filterWishlistColl;
        }
    }
}
