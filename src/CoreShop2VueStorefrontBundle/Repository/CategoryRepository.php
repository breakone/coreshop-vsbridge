<?php


namespace CoreShop2VueStorefrontBundle\Repository;


use Pimcore\Model\Listing\AbstractListing;
use CoreShop\Component\Store\Model\StoreInterface;
use CoreShop\Bundle\CoreBundle\Pimcore\Repository\CategoryRepository as BaseCategoryRepository;

class CategoryRepository extends BaseCategoryRepository implements StoreAwareRepositoryInterface, DateAwareRepositoryInterface
{

    public function addStoreCondition(AbstractListing $listing, StoreInterface $store)
    {
        $listing->addConditionParam('stores LIKE ?', '%,' . $store->getId() . ',%');
    }

    public function addDateCondition(AbstractListing $listing, \DateTimeInterface $since) {
        $listing->addConditionParam('o_modificationDate >= ?', $since->getTimestamp());
    }
}
