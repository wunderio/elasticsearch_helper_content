<?php

namespace Drupal\elasticsearch_helper_content\Plugin\ElasticsearchIndex;

use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\elasticsearch_helper\Elasticsearch\Index\FieldDefinition;
use Drupal\elasticsearch_helper\Elasticsearch\Index\MappingDefinition;
use Drupal\elasticsearch_helper\ElasticsearchLanguageAnalyzer;
use Drupal\elasticsearch_helper\Event\ElasticsearchOperations;
use Drupal\elasticsearch_helper\Plugin\ElasticsearchIndexBase;
use Elasticsearch\Client;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Serializer\Serializer;

/**
 * A multilingual content index base class.
 */
abstract class MultilingualContentIndex extends ElasticsearchIndexBase {

  /**
   * @var \Drupal\Core\Language\LanguageManagerInterface
   */
  protected $languageManager;

  /**
   * MultilingualContentIndex constructor.
   *
   * @param array $configuration
   * @param string $plugin_id
   * @param mixed $plugin_definition
   * @param \Elasticsearch\Client $client
   * @param \Symfony\Component\Serializer\Serializer $serializer
   * @param \Psr\Log\LoggerInterface $logger
   * @param \Drupal\Core\Language\LanguageManagerInterface $languageManager
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, Client $client, Serializer $serializer, LoggerInterface $logger, LanguageManagerInterface $languageManager) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $client, $serializer, $logger);

    $this->languageManager = $languageManager;
  }

  /**
   * @param \Symfony\Component\DependencyInjection\ContainerInterface $container
   * @param array $configuration
   * @param string $plugin_id
   * @param mixed $plugin_definition
   * @return static
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('elasticsearch_helper.elasticsearch_client'),
      $container->get('serializer'),
      $container->get('logger.factory')->get('elasticsearch_helper'),
      $container->get('language_manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function serialize($source, $context = []) {
    /** @var \Drupal\core\Entity\ContentEntityBase $source */

    $data = parent::serialize($source, $context);

    // Add the language code to be used as a token.
    $data['langcode'] = $source->language()->getId();

    return $data;
  }

  /**
   * {@inheritdoc}
   */
  public function index($source) {
    /** @var \Drupal\core\Entity\ContentEntityBase $source */
    foreach ($source->getTranslationLanguages() as $langcode => $language) {
      if ($source->hasTranslation($langcode)) {
        parent::index($source->getTranslation($langcode));
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function delete($source) {
    /** @var \Drupal\core\Entity\ContentEntityBase $source */
    foreach ($source->getTranslationLanguages() as $langcode => $language) {
      if ($source->hasTranslation($langcode)) {
        parent::delete($source->getTranslation($langcode));
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function setup() {
    try {
      // Create one index per language, so that we can have different analyzers.
      foreach ($this->languageManager->getLanguages() as $langcode => $language) {
        // Get index name.
        $index_name = $this->getIndexName(['langcode' => $langcode]);

        // Check if index exists.
        if (!$this->client->indices()->exists(['index' => $index_name])) {
          // Get index definition.
          $index_definition = $this->getIndexDefinition(['langcode' => $langcode]);

          // Get analyzer for the language.
          $analyzer = ElasticsearchLanguageAnalyzer::get($langcode);

          // Put analyzer parameter to all "text" fields in the mapping.
          foreach ($index_definition->getMappingDefinition()->getProperties() as $property) {
            if ($property->getDataType()->getType() == 'text') {
              $property->addOption('analyzer', $analyzer);
            }
          }

          $this->createIndex($index_name, $index_definition);
        }
      }
    }
    catch (\Throwable $e) {
      $request_wrapper = isset($request_wrapper) ? $request_wrapper : NULL;
      $this->dispatchOperationErrorEvent($e, ElasticsearchOperations::INDEX_CREATE, $request_wrapper);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getMappingDefinition(array $context = []) {
    return MappingDefinition::create()
      ->addProperty('id', FieldDefinition::create('integer'))
      ->addProperty('uuid', FieldDefinition::create('keyword'))
      ->addProperty('entity', FieldDefinition::create('keyword'))
      ->addProperty('bundle', FieldDefinition::create('keyword'))
      ->addProperty('entity_label', FieldDefinition::create('keyword'))
      ->addProperty('bundle_label', FieldDefinition::create('keyword'))
      ->addProperty('url_internal', FieldDefinition::create('keyword'))
      ->addProperty('url_alias', FieldDefinition::create('keyword'))
      ->addProperty('label', FieldDefinition::create('text'))
      ->addProperty('created', FieldDefinition::create('date')
        ->addOption('format', 'epoch_second')
      )
      ->addProperty('status', FieldDefinition::create('boolean'))
      ->addProperty('content', FieldDefinition::create('text')
        // Trade off index size for better highlighting.
        ->addOption('term_vector', 'with_positions_offsets')
      )
      ->addProperty('rendered_search_result', FieldDefinition::create('keyword')
        ->addOption('index', FALSE)
        ->addOption('store', TRUE)
      );
  }

}
