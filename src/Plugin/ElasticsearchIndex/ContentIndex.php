<?php

namespace Drupal\elasticsearch_helper_content\Plugin\ElasticsearchIndex;

use Drupal\Core\Entity\EntityPublishedInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\elasticsearch_helper\Elasticsearch\Index\FieldDefinition;
use Drupal\elasticsearch_helper\Elasticsearch\Index\IndexDefinition;
use Drupal\elasticsearch_helper\Elasticsearch\Index\SettingsDefinition;
use Drupal\elasticsearch_helper\ElasticsearchLanguageAnalyzer;
use Drupal\elasticsearch_helper\Plugin\ElasticsearchIndexBase;
use Drupal\elasticsearch_helper_content\Entity\ElasticsearchContentIndex;
use Elasticsearch\Client;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Serializer\Serializer;

/**
 * @ElasticsearchIndex(
 *   id = "content_index",
 *   deriver = "Drupal\elasticsearch_helper_content\Plugin\Deriver\ContentIndexDeriver"
 * )
 */
class ContentIndex extends ElasticsearchIndexBase {

  /**
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * @var \Drupal\Core\Language\LanguageManagerInterface
   */
  protected $languageManager;

  /**
   * @var \Drupal\elasticsearch_helper_content\ElasticsearchContentIndexInterface|null
   */
  protected $indexEntity;

  /**
   * ContentIndex constructor.
   *
   * @param array $configuration
   * @param $plugin_id
   * @param $plugin_definition
   * @param \Elasticsearch\Client $client
   * @param \Symfony\Component\Serializer\Serializer $serializer
   * @param \Psr\Log\LoggerInterface $logger
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   * @param \Drupal\Core\Language\LanguageManagerInterface $language_manager
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, Client $client, Serializer $serializer, LoggerInterface $logger, EntityTypeManagerInterface $entity_type_manager, LanguageManagerInterface $language_manager) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $client, $serializer, $logger);

    $this->entityTypeManager = $entity_type_manager;
    $this->languageManager = $language_manager;

    $this->indexEntity = $this->getContentIndexEntity();

    // Add language placeholder to index name if index supports multiple
    // languages.
    if ($this->isMultilingual()) {
      $this->pluginDefinition['indexName'] .= '_{langcode}';
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('elasticsearch_helper.elasticsearch_client'),
      $container->get('serializer'),
      $container->get('logger.factory')->get('elasticsearch_helper'),
      $container->get('entity_type.manager'),
      $container->get('language_manager')
    );
  }

  /**
   * Returns Elasticsearch content index entity.
   *
   * @return \Drupal\elasticsearch_helper_content\ElasticsearchContentIndexInterface|null
   */
  public function getContentIndexEntity() {
    try {
      /** @var \Drupal\elasticsearch_helper_content\ElasticsearchContentIndexInterface $entity */
      $entity = $this->entityTypeManager->getStorage('elasticsearch_content_index')->load($this->pluginDefinition['content_index_entity_id']);
    }
    catch (\Exception $e) {
      watchdog_exception('elasticsearch_helper_content', $e);
      $entity = NULL;
    }

    return $entity;
  }

  /**
   * Returns TRUE if content is multilingual.
   *
   * Multilingual configuration is taken from plugin definition which enables
   * other modules to change the behaviour of the plugin by instantiating
   * the plugin directly.
   *
   * @return bool
   *
   * @see \Drupal\elasticsearch_helper_content\Plugin\Deriver\ContentIndexDeriver::getDerivativeDefinitions()
   */
  protected function isMultilingual() {
    return !empty($this->pluginDefinition['multilingual']);
  }

  /**
   * Returns a list of index names this plugin produces.
   *
   * List is keyed by language code.
   *
   * @return array
   */
  public function getIndexNames() {
    if ($this->isMultilingual()) {
      $index_names = [];

      foreach ($this->languageManager->getLanguages() as $language) {
        $langcode = $language->getId();
        $index_names[$langcode] = $this->getIndexName(['langcode' => $langcode]);
      }
    }
    else {
      $index_names = [NULL => $this->getIndexName([])];
    }

    return $index_names;
  }

  /**
   * {@inheritdoc}
   */
  public function setup() {
    try {
      foreach ($this->getIndexNames() as $langcode => $index_name) {
        // Only setup index if it's not already existing.
        if (!$this->client->indices()->exists(['index' => $index_name])) {
          $context = ['langcode' => $langcode];

          // Get index definition.
          $index_definition = $this->getIndexDefinition($context);

          // Get index name.
          $index_name = $this->getIndexName(['langcode' => $langcode]);

          // For multi-lingual indices (where langcode is not null), add
          // analyzer parameter to "text" fields.
          if (!is_null($langcode)) {
            // Get default analyzer for the language.
            $analyzer = $this->getDefaultLanguageAnalyzer($langcode);

            // Put analyzer parameter to all "text" fields in the mapping.
            $this->setAnalyzer($index_definition->getMappingDefinition(), $analyzer);
          }

          $this->client->indices()->create([
            'index' => $index_name,
            'body' => $index_definition->toArray(),
          ]);
        }
      }
    }
    catch (\Exception $e) {
      watchdog_exception('elasticsearch_helper_content', $e);
    }
  }

  /**
   * Sets analyzer option on fields of type "text".
   *
   * @param \Drupal\elasticsearch_helper\Elasticsearch\Index\MappingDefinition|\Drupal\elasticsearch_helper\Elasticsearch\Index\FieldDefinition $property
   * @param $analyzer
   */
  protected function setAnalyzer($property, $analyzer) {
    if ($property instanceof FieldDefinition) {
      // Add analyzer to the property.
      if ($property->getDataType()->getType() == 'text') {
        $property->addOption('analyzer', $analyzer);
      }
    }

    // Add analyzer to all sub-properties.
    foreach ($property->getProperties() as $sub_property) {
      $this->setAnalyzer($sub_property, $analyzer);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getIndexDefinition(array $context = []) {
    $settings = $this->getIndexSettingsDefinition($context);
    $mappings = $this->getMappingDefinition($context);

    $index_definition = IndexDefinition::create()
      ->setSettingsDefinition($settings)
      ->setMappingDefinition($mappings);

    // If you are using Elasticsearch < 7, add the type to the index definition.
    $index_definition->setType($this->getTypeName([]));

    return $index_definition;
  }

  /**
   * Returns index settings.
   *
   * @param array $context
   *   Additional context parameters.
   *
   * @return \Drupal\elasticsearch_helper\Elasticsearch\Index\SettingsDefinition
   */
  protected function getIndexSettingsDefinition(array $context = []) {
    return SettingsDefinition::create()
      ->addOptions([
        'number_of_shards' => 1,
        'number_of_replicas' => 0,
      ]);
  }

  /**
   * {@inheritdoc}
   */
  public function getMappingDefinition(array $context = []) {
    return $this->indexEntity->getNormalizerInstance()->getMappingDefinition();
  }

  /**
   * Returns default analyzer for given language.
   *
   * @param string|null $langcode
   *
   * @return string
   */
  protected function getDefaultLanguageAnalyzer($langcode = NULL) {
    return ElasticsearchLanguageAnalyzer::get($langcode);
  }

  /**
   * {@inheritdoc}
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $source
   */
  public function index($source) {
    if ($this->isMultilingual()) {
      foreach ($source->getTranslationLanguages() as $langcode => $language) {
        if ($source->hasTranslation($langcode)) {
          $translation = $source->getTranslation($langcode);
          $this->indexOrDeleteTranslation($translation);
        }
      }
    }
    else {
      $this->indexOrDeleteTranslation($source);
    }
  }

  /**
   * Returns TRUE if entity is publishing status aware.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $source
   *
   * @return bool
   */
  protected function isPublishAware($source) {
    return $source instanceof EntityPublishedInterface;
  }

  /**
   * Returns TRUE if translation of the entity should be added to the index.
   *
   * @param $source
   *
   * @return bool
   */
  protected function isIndexable($source) {
    $index_unpublished = $this->indexEntity->indexUnpublishedContent();

    // Return TRUE if entity type does not support publishing status or
    // unpublished content should be indexed.
    if (in_array($index_unpublished, [ElasticsearchContentIndex::INDEX_UNPUBLISHED_NA, ElasticsearchContentIndex::INDEX_UNPUBLISHED], TRUE)) {
      return TRUE;
    }

    if ($index_unpublished == ElasticsearchContentIndex::INDEX_UNPUBLISHED_IGNORE) {
      if ($this->isPublishAware($source) && $source->isPublished()) {
        return TRUE;
      }
    }

    // Stay on the safe side and do not index by default.
    return FALSE;
  }

  /**
   * Returns TRUE if translation of the entity should be removed from the index.
   *
   * @param $source
   *
   * @return bool
   */
  protected function isDeletable($source) {
    $index_unpublished = $this->indexEntity->indexUnpublishedContent();

    // Return FALSE if entity type does not support publishing status or
    // unpublished content should be indexed.
    if (in_array($index_unpublished, [ElasticsearchContentIndex::INDEX_UNPUBLISHED_NA, ElasticsearchContentIndex::INDEX_UNPUBLISHED], TRUE)) {
      return FALSE;
    }

    if ($index_unpublished == ElasticsearchContentIndex::INDEX_UNPUBLISHED_IGNORE) {
      if ($this->isPublishAware($source) && $source->isPublished()) {
        return FALSE;
      }
    }

    // Stay on the safe side and remove by default.
    return TRUE;
  }

  /**
   * Indexes or removes translation of the entity.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $source
   */
  protected function indexOrDeleteTranslation($source) {
    if ($this->isIndexable($source)) {
      // Parent method is called here as this method is invoked from index().
      parent::index($source);
    }
    elseif ($this->isDeletable($source)) {
      $this->deleteTranslation($source);
    }
  }

  /**
   * {@inheritdoc}
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $source
   */
  public function delete($source) {
    if ($this->isMultilingual()) {
      foreach ($source->getTranslationLanguages() as $langcode => $language) {
        if ($source->hasTranslation($langcode)) {
          $translation = $source->getTranslation($langcode);
          $this->deleteTranslation($translation);
        }
      }
    }
    else {
      $this->deleteTranslation($source);
    }
  }

  /**
   * Removes translation of the entity.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $source
   */
  public function deleteTranslation($source) {
    parent::delete($source);
  }

  /**
   * {@inheritdoc}
   *
   * @param \Drupal\Core\Entity\EntityInterface $source
   */
  public function serialize($source, $context = []) {
    $data = [];

    try {
      if ($this->indexEntity) {
        $normalizer_instance = $this->indexEntity->getNormalizerInstance();
        $data = $normalizer_instance->normalize($source, $context);
      }
    }
    catch (\Exception $e) {
      watchdog_exception('elasticsearch_helper_content', $e);
    }

    // Add the language code to be used as a token.
    $data['langcode'] = $source->language()->getId();

    return $data;
  }

}
