<?php

declare(strict_types=1);

namespace CoreShop2VueStorefrontBundle\Bridge;

use CoreShop\Component\Core\Repository\CategoryRepositoryInterface;
use CoreShop\Component\Core\Repository\ProductRepositoryInterface;
use CoreShop2VueStorefrontBundle\Bridge\DocumentMapper\DocumentMapperFactory;
use CoreShop2VueStorefrontBundle\CoreShop2VueStorefrontBundle;
use ONGR\ElasticsearchBundle\Service\ManagerFactory;
use Symfony\Component\OptionsResolver\Exception\InvalidOptionsException;
use Symfony\Component\OptionsResolver\Options;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ImporterFactory
{
    private const TYPE_CATEGORY = 'category';
    private const TYPE_PRODUCT = 'product';

    private $hosts;
    private $indexTemplate;
    private $stores;

    /**
     * @var ManagerFactory
     */
    private $managerFactory;

    /**
     * @var ManagerFactory
     */
    private $documentMapperFactory;

    /**
     * @var ProductRepositoryInterface
     */
    private $productRepository;

    /**
     * @var CategoryRepositoryInterface
     */
    private $categoryRepository;

    /**
     * @var OptionsResolver
     */
    private $resolver;

    public function __construct(ManagerFactory $managerFactory, DocumentMapperFactory $documentMapperFactory, ProductRepositoryInterface $productRepository, CategoryRepositoryInterface $categoryRepository, array $hosts, string $indexTemplate, array $stores = [])
    {
        $this->managerFactory = $managerFactory;
        $this->documentMapperFactory = $documentMapperFactory;
        $this->productRepository = $productRepository;
        $this->categoryRepository = $categoryRepository;

        $this->hosts = $hosts;
        $this->indexTemplate = $indexTemplate;
        $this->stores = $stores;
        $this->resolver = $this->configureOptions(new OptionsResolver());
    }

    /**
     * @return array<ImporterFactory>
     */
    public function create(?string $store = null, ?string $language = null, ?string $type = null): array
    {
        $options = $this->resolver->resolve(['store' => $store, 'language' => $language, 'type' => $type]);

        $stores = (array) ($options['store'] ?? array_keys($this->stores));

        $importers = [];
        foreach ($stores as $name) {
            $store = $this->stores[$name];

            $languages = (array) ($options['language'] ?? $store['languages']);
            foreach ($languages as $language) {
                $types = (array) ($options['type'] ?? [self::TYPE_CATEGORY, self::TYPE_PRODUCT]);

                foreach ($types as $type) {
                    switch ($type) {
                        case self::TYPE_CATEGORY:
                            $repository = $this->categoryRepository;
                            break;
                        case self::TYPE_PRODUCT:
                            $repository = $this->productRepository;
                            break;
                    }

                    $variables = ['store' => $name, 'language' => $language, 'type' => $type];
                    $manager = $this->managerFactory->createManager(
                        sprintf('coreshop2vuestorefront.%1$s.%2$s.%3$s', $name, $type, $language),
                        [
                            'hosts' => $this->inject($this->hosts, $variables),
                            'index_name' => $this->inject($this->indexTemplate, $variables),
                            'settings' => [],
                        ],
                        [],
                        [
                            'logger' => ['enabled' => false],
                            'mappings' => ['CoreShop2VueStorefrontBundle'],
                            'commit_mode' => 'flush',
                            'bulk_size' => 100,
                        ]
                    );
                    $persister = new EnginePersister($manager, $this->documentMapperFactory, $language);
                    $importers[] = new ElasticsearchImporter($repository, $persister, $name, $language, $type);
                }
            }
        }

        return $importers;
    }

    private function configureOptions(OptionsResolver $resolver): OptionsResolver
    {
        $stores = $this->stores;

        $resolver->setDefined(['store', 'language', 'type']);

        $resolver->setAllowedValues('store', array_merge([null], array_keys($stores)));

        $resolver->setAllowedTypes('language', ['null' ,'string']);
        $resolver->setNormalizer('language', function (Options $options, $language) use ($stores) {
            $store = $options['store'];
            if ($language === null || $store === null) {
                return null;
            }

            $storeLanguages = $stores[$store]['languages'];
            if (!in_array($language, $storeLanguages, true)) {
                $message = sprintf(
                    'The option "language" with value %s is invalid. Accepted values are: null, "%s".',
                    $language,
                    implode('", "', $storeLanguages)
                );

                throw new InvalidOptionsException($message);
            }

            return $language;
        });

        $resolver->setAllowedValues('type', [null, 'product', 'category']);

        return $resolver;
    }

    /**
     * @param array|string $template
     * @param array        $variables
     *
     * @return array|string
     */
    private function inject($template, array $variables)
    {
        if (is_array($template)) {
            foreach ($template as $idx => $item) {
                $template[$idx] = $this->inject($item, $variables);
            }

            return $template;
        }

        $keys = array_map(function(string $key) {
            return '{'. $key .'}';
        }, array_keys($variables));

        return str_replace($keys, array_values($variables), $template);
    }
}
