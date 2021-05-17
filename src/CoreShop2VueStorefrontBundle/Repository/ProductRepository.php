<?php


namespace CoreShop2VueStorefrontBundle\Repository;


use CoreShop\Bundle\CoreBundle\Pimcore\Repository\ProductRepository as BaseProductRepository;
use CoreShop\Component\Store\Model\StoreInterface;
use Pimcore\Model\Listing\AbstractListing;

class ProductRepository extends BaseProductRepository implements StoreAwareRepositoryInterface, DateAwareRepositoryInterface
{

    public function addStoreCondition(AbstractListing $listing, StoreInterface $store)
    {
        $listing->addConditionParam('stores LIKE ?', '%,' . $store->getId() . ',%');
    }

    public function addDateCondition(AbstractListing $listing, \DateTimeInterface $since) {
        $listing->addConditionParam('o_modificationDate >= ?', $since->getTimestamp());
    }
}
