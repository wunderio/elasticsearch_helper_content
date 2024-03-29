<?php

namespace Drupal\elasticsearch_helper_content\Plugin\ElasticsearchIndex;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\elasticsearch_helper\Elasticsearch\Index\FieldDefinition;
use Drupal\elasticsearch_helper\ElasticsearchLanguageAnalyzer;
use Drupal\elasticsearch_helper\Event\ElasticsearchOperations;
use Drupal\elasticsearch_helper\Plugin\ElasticsearchIndexBase;
use Elasticsearch\Client;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Serializer\Serializer;

/**
 * The "content_index" Elasticsearch index plugin.
 *
 * @ElasticsearchIndex(
 *   id = "content_index",
 *   deriver = "Drupal\elasticsearch_helper_content\Plugin\Deriver\ContentIndexDeriver"
 * )
 */
class ContentIndex extends ElasticsearchIndexBase {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The language manager instance.
   *
   * @var \Drupal\Core\Language\LanguageManagerInterface
   */
  protected $languageManager;

  /**
   * The Elasticsearch content index entity.
   *
   * @var \Drupal\elasticsearch_helper_content\ElasticsearchContentIndexInterface|null
   */
  protected $indexEntity;

  /**
   * ContentIndex constructor.
   *
   * @param array $configuration
   *   The plugin configuration array.
   * @param string $plugin_id
   *   The plugin ID.
   * @param array $plugin_definition
   *   The plugin definition.
   * @param \Elasticsearch\Client $client
   *   The Elasticsearch client instance.
   * @param \Symfony\Component\Serializer\Serializer $serializer
   *   The serializer service instance.
   * @param \Psr\Log\LoggerInterface $logger
   *   The logger instance.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager instance.
   * @param \Drupal\Core\Language\LanguageManagerInterface $language_manager
   *   The language manager instance.
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
   *   The Elasticsearch content index entity.
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
   *   Returns TRUE if index is multilingual.
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
   *   The list of index names.
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
      $operation = ElasticsearchOperations::INDEX_CREATE;

      foreach ($this->getIndexNames() as $langcode => $index_name) {
        // Only setup index if it's not already existing.
        if (!$this->client->indices()->exists(['index' => $index_name])) {
          $context = ['langcode' => $langcode];

          // Get index name.
          $index_name = $this->getIndexName($context);

          // Get index definition.
          $index_definition = $this->getIndexDefinition($context);

          // For multilingual indices (where langcode is not null), add
          // analyzer parameter to "text" fields.
          if (!empty($langcode)) {
            // Get default analyzer for the language.
            $analyzer = $this->getDefaultLanguageAnalyzer($langcode);

            // Put analyzer parameter to all "text" fields in the mapping.
            $this->setAnalyzer($index_definition->getMappingDefinition(), $analyzer);
          }

          // Create the index.
          $this->createIndex($index_name, $index_definition);
        }
      }
    }
    catch (\Throwable $e) {
      $this->dispatchOperationErrorEvent($e, $operation);
    }
  }

  /**
   * Sets analyzer option on fields of type "text".
   *
   * @param \Drupal\elasticsearch_helper\Elasticsearch\Index\MappingDefinition|\Drupal\elasticsearch_helper\Elasticsearch\Index\FieldDefinition $property
   *   The field definition instance.
   * @param string $analyzer
   *   The language analyzer name.
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
  public function getMappingDefinition(array $context = []) {
    return $this->indexEntity->getNormalizerInstance()->getMappingDefinition();
  }

  /**
   * Returns default analyzer for given language.
   *
   * @param string|null $langcode
   *   The language code.
   *
   * @return string
   *   The language analyzer name.
   */
  protected function getDefaultLanguageAnalyzer($langcode = NULL) {
    return ElasticsearchLanguageAnalyzer::get($langcode);
  }

  /**
   * {@inheritdoc}
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $source
   *   The index-able entity.
   */
  public function index($source) {
    if ($this->isMultilingual()) {
      foreach ($source->getTranslationLanguages() as $langcode => $language) {
        $translation = $source->getTranslation($langcode);
        parent::index($translation);
      }
    }
    else {
      parent::index($source);
    }
  }

  /**
   * {@inheritdoc}
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $source
   *   The index-able entity.
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
   * This method allows removing a single translation from the index from
   * outside the class.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $source
   *   The index-able entity.
   */
  public function deleteTranslation($source) {
    parent::delete($source);
  }

  /**
   * {@inheritdoc}
   *
   * @param \Drupal\Core\Entity\EntityInterface $source
   *   The index-able entity.
   * @param array $context
   *   The context array.
   */
  public function serialize($source, $context = []) {
    $normalizer_instance = $this->indexEntity->getNormalizerInstance();
    $data = $normalizer_instance->normalize($source, $context);

    // Add the language code if it's missing.
    // @see static::__construct()
    if ($this->isMultilingual() && empty($data['langcode'])) {
      $data['langcode'] = $source->language()->getId();
    }

    return $data;
  }

}
