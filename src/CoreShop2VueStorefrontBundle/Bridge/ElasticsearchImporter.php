<?php

declare(strict_types=1);

namespace CoreShop2VueStorefrontBundle\Bridge;

use CoreShop\Component\Pimcore\BatchProcessing\BatchListing;
use CoreShop\Component\Resource\Repository\PimcoreRepositoryInterface;
use CoreShop\Component\Store\Model\StoreInterface;
use CoreShop2VueStorefrontBundle\Repository\LanguageAwareRepositoryInterface;
use CoreShop2VueStorefrontBundle\Repository\StoreAwareRepositoryInterface;
use Pimcore\Model\Listing\AbstractListing;

class ElasticsearchImporter implements ImporterInterface
{
    private $repository;
    private $list;
    private $store;
    private $language;
    private $type;
    private $persister;
    /** @var StoreInterface|null */
    private $concreteStore;

    public function __construct(PimcoreRepositoryInterface $repository, EnginePersister $persister, string $store, string $language, string $type, ?StoreInterface $concreteStore = null, ?\DateTimeInterface $since = null)
    {
        $this->repository = $repository;
        $this->persister = $persister;
        $this->store = $store;
        $this->language = $language;
        $this->type = $type;
        $this->concreteStore = $concreteStore;
        $this->since = $since;
    }

    public function describe(): string
    {
        return sprintf('%1$s: %2$s (%3$s)', $this->store, $this->type, $this->language);
    }
    
    public function getTarget(): string
    {
        return $this->persister->getIndexName();
    }

    public function count(): int
    {
        //return $this->getList()->count();
        return count($this->getList());
    }

    public function import(callable $callback): void
    {
        $list = $this->getList();
        if ($list instanceof AbstractListing) {
            $listing = new BatchListing($list, 100);
        } elseif (is_iterable($list)) {
            $listing = $list;
        }

        foreach ($listing as $object) {
            $callback($object);

            $this->persister->persist($object);
        }
    }

    private function getList(): AbstractListing
    {
        if (null === $this->list) {
            if ($this->repository instanceof PimcoreRepositoryInterface) {
                $this->list = $this->repository->getList();

                if ($this->repository instanceof StoreAwareRepositoryInterface && $this->concreteStore instanceof StoreInterface) {
                    $this->repository->addStoreCondition($this->list, $this->concreteStore);
                }

                if ($this->since !== null) {
                    $this->list->addConditionParam('o_modificationDate >= ?', $this->since->getTimestamp());
                }
            } else {
                // TODO: how to do since here?
                $this->list = $this->repository->findAll();
            }
        }

        return $this->list;
    }
}
