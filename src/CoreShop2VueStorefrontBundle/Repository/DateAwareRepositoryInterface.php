<?php


namespace CoreShop2VueStorefrontBundle\Repository;

use CoreShop\Component\Resource\Repository\PimcoreRepositoryInterface;
use Pimcore\Model\Listing\AbstractListing;

interface DateAwareRepositoryInterface extends PimcoreRepositoryInterface
{

    public function addDateCondition(AbstractListing $listing, \DateTimeInterface $since);

}