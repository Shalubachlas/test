<?php

namespace Netsmartz\Product\Plugin;

use Magento\Catalog\Helper\Image;
use Magento\Catalog\Model\CategoryRepository;
use Magento\Catalog\Model\Category;
use Magento\Catalog\Model\ResourceModel\Category\CollectionFactory;
use Magento\Store\Model\App\Emulation;
use Netsmartz\Product\Helper\Data;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Review\Model\ResourceModel\Review\CollectionFactory as ReviewCollectionFactory;
use Magento\Catalog\Api\Data\ProductLinkInterfaceFactory;
use Magento\Catalog\Api\ProductRepositoryInterface;

class ProductImgSrc
{
    protected $imageHelper;
    private   $categoryRepository;
    protected $mageCategory;
    protected $categoryCollection;
    protected $appEmulation;
    protected $helper;
    protected $storeManager;
    protected $_reviewCollection; 
    protected $productLinkFactory;
    private $productRepository; 
    protected $_voteFactory;    
    
    public function __construct(
        Image $imageHelper,
        CategoryRepository $categoryRepository,
        Category $mageCategory,
        CollectionFactory $categoryCollection,
        Emulation $appEmulation,
        Data $helper,
        StoreManagerInterface $storeManagerInterface,
        ReviewCollectionFactory $reviewCollection,
        ProductLinkInterfaceFactory $productLink,
        ProductRepositoryInterface $productRepository,
        \Magento\Review\Model\Rating\Option\VoteFactory $voteFactory
    ){
        $this->imageHelper = $imageHelper;
        $this->categoryRepository = $categoryRepository;
        $this->mageCategory = $mageCategory;
        $this->categoryCollection = $categoryCollection;
        $this->appEmulation = $appEmulation;
        $this->helper = $helper;
        $this->storeManager = $storeManagerInterface;
        $this->_reviewCollection = $reviewCollection;
        $this->productLinkFactory = $productLink;
        $this->productRepository = $productRepository;
        $this->_voteFactory = $voteFactory;
    }


    public function afterGet(
        \Magento\Catalog\Api\ProductRepositoryInterface $subject,
        \Magento\Catalog\Api\Data\ProductInterface $entity
    )
    {  
        $product = $entity;

        $imageUrl = $this->helper->getProductPlaceholderImage();
        $existingMediaGalleryEntries = $product->getMediaGalleryEntries();

        foreach($existingMediaGalleryEntries as $gallery){
            $imageUrl = $this->imageHelper->init($product, 'product_page_image_small')
                ->setImageFile($gallery->getFile()) // image,small_image,thumbnail
                ->getUrl();
            $gallery->setFile($imageUrl);
        }

        if($product->getSmallImage() != '' && $product->getSmallImage() != 'no_selection'){
            $imageUrl = $this->imageHelper->init($product, 'product_page_image_small')
                ->setImageFile($product->getSmallImage())
                ->constrainOnly(FALSE)
                ->keepAspectRatio(TRUE)
                ->keepFrame(FALSE)
                ->resize(225)
                ->getUrl();
        }

        $product->setImage($imageUrl); 
        $product->setMediaGalleryEntries($existingMediaGalleryEntries);

        $color = 'N/A';
        if(isset($colorValue) && $colorValue != ''){
            $color = $colorValue;
        }

        $product->setColors($color);

        $size = 'N/A';
        if(isset($sizeValue) && $sizeValue != ''){
            $size = $sizeValue;
        }

        $extensionAttributes = $product->getExtensionAttributes(); /** get current extension attributes from entity **/

        /* Add Rating code **/
        $reviewCollection = $this->_reviewCollection->create()->addStatusFilter(\Magento\Review\Model\Review::STATUS_APPROVED)->addEntityFilter('product',$product->getId())->setDateOrder()->addRateVotes();

        $review_count = count($reviewCollection);
        $allRatingsAvg = 0;
        $totalrating = 0;  
        $allRatings = 0;
        $flag=0;

        if ($reviewCollection && count($reviewCollection) > 0) 
        {
            foreach ($reviewCollection AS $review) 
            {
                $review_id = $review->getId();              
                $ratingCollectionnew = $this->_voteFactory->create()->getResourceCollection()->setReviewFilter(
                $review_id)->addRatingInfo(
                $this->storeManager->getStore()->getId())->setStoreFilter($this->storeManager->getStore()->getId())->load();          
                $data = $ratingCollectionnew->getData();                    
                $flags = 0;  

                foreach($data as $new)
                {
                    $flags = $flags + 1 ;
                    $rating_code = $new['rating_code'];                  
                }
                $flag =$flags;            
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
        }else{
            $final_rating = $totalrating;
        }
    
        $ratingResult = number_format($final_rating, 1);         
        $extensionAttributes->setProductReview($ratingResult);
        /* Add Rating code **/

        /** Add Price under product_links for group product **/
        if($product->getTypeId() == 'grouped'){ 
            $existingLinks = $product->getProductLinks();
            $newLinks = [];
            foreach($existingLinks as $linkData){
                $productData = $this->productRepository->get($linkData->getLinkedProductSku());
                $productLink = $this->productLinkFactory->create();
                $productLink->setSku($linkData->getSku())
                    ->setLinkType($linkData->getLinkType())
                    ->setLinkedProductSku($linkData->getLinkedProductSku())
                    ->setLinkedProductType($linkData->getLinkedProductType())
                    ->setPosition($linkData->getPosition())
                    ->getExtensionAttributes()
                    ->setQty($linkData->getExtensionAttributes()->getQty())
                    ->setPrice($productData->getPrice());
                $newLinks[] = $productLink;
            }
            $product->setProductLinks($newLinks);
        }
        /** Add Price under product_links for group product **/

         /** Add Price under product_links for bundle product **/
        if($product->getTypeId() == 'bundle'){ 
            $minimumPrice = $product->getPriceInfo()->getPrice('final_price')->getMinimalPrice()->getValue(); 
            $maximumPrice = $product->getPriceInfo()->getPrice('final_price')->getMaximalPrice()->getValue();
            $extensionAttributes->setBundleProductFromPrice($minimumPrice);
            $extensionAttributes->setBundleProductToPrice($maximumPrice);
        }


        /** Add Price under product_links for group product **/

        $product->setExtensionAttributes($extensionAttributes);
        $product->setSize($size);
        return $product;
    }

    public function afterGetList(
        \Magento\Catalog\Api\ProductRepositoryInterface $subject,
        \Magento\Catalog\Api\Data\ProductSearchResultsInterface $searchCriteria
    ) : \Magento\Catalog\Api\Data\ProductSearchResultsInterface 
    {
       $products = [];
        foreach ($searchCriteria->getItems() as $entity) {
            $imageUrl = $this->helper->getProductPlaceholderImage();
            
            if($entity->getSmallImage() != '' && $entity->getSmallImage() != 'no_selection'){
                $imageUrl = $this->imageHelper->init($entity, 'product_page_image_small')
                    ->setImageFile($entity->getSmallImage()) // image,small_image,thumbnail
                    ->constrainOnly(FALSE)
                    ->keepAspectRatio(TRUE)
                    ->keepFrame(FALSE)
                    ->resize(225)
                    ->getUrl();
            }
            $entity->setImage($imageUrl);
            
            if($entity->getThumbnail() != '' && $entity->getThumbnail() != 'no_selection'){
                $hoverUrl = $this->imageHelper->init($entity, 'product_page_image_small')
                    ->setImageFile($entity->getThumbnail()) // image,small_image,thumbnail
                    ->getUrl();
                $entity->setThumbnail($hoverUrl);
            }else{
                $entity->setThumbnail($imageUrl);  
            }

            /* Add Rating code **/
            // $extensionAttributes = $entity->getExtensionAttributes(); /** get current extension attributes from entity **/
            // $reviewCollection = $this->_reviewCollection->create()->addStatusFilter(\Magento\Review\Model\Review::STATUS_APPROVED)->addEntityFilter('product',$entity->getId())->setDateOrder()->addRateVotes();
            // $review_count = count($reviewCollection);
            
            // $star = 0;
            // $allRatingsAvg = 0;
            // if ($reviewCollection && count($reviewCollection) > 0) 
            // {
            //     foreach ($reviewCollection AS $review) 
            //     {
            //         $countRatings = count($review->getRatingVotes());
            //         if ($countRatings > 0) 
            //         {
            //                 $allRatings = 0;
            //             foreach ($review->getRatingVotes() as $vote) 
            //             {
            //                     $allRatings = $allRatings + $vote->getPercent();
            //                     $allRatingsAvg = $allRatings / $countRatings;
            //             }
            //         }
            //     }
            // }

            // $rating = $allRatingsAvg/20;
            // $extensionAttributes->setProductReview($rating);
            // $entity->setExtensionAttributes($extensionAttributes);
            /* Add Rating code **/
            $products[] = $entity;
        }
        $searchCriteria->setItems($products);
        return $searchCriteria;
    }

}