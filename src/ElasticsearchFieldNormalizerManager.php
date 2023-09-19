<?php

namespace Drupal\elasticsearch_helper_content;

use Drupal\Component\Utility\SortArray;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Plugin\DefaultPluginManager;
use Drupal\elasticsearch_helper_content\Plugin\ElasticsearchNormalizer\Entity\FieldTypeInterface;

/**
 * Provides the Elasticsearch field normalizer plugin manager.
 */
class ElasticsearchFieldNormalizerManager extends DefaultPluginManager implements ElasticsearchFieldNormalizerManagerInterface {

  /**
   * Constructs a new ElasticsearchFieldNormalizerManager object.
   *
   * @param \Traversable $namespaces
   *   An object that implements \Traversable which contains the root paths
   *   keyed by the corresponding namespace to look for plugin implementations.
   * @param \Drupal\Core\Cache\CacheBackendInterface $cache_backend
   *   Cache backend instance to use.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler to invoke the alter hook with.
   */
  public function __construct(\Traversable $namespaces, CacheBackendInterface $cache_backend, ModuleHandlerInterface $module_handler) {
    parent::__construct('Plugin/ElasticsearchNormalizer/Field', $namespaces, $module_handler, 'Drupal\elasticsearch_helper_content\ElasticsearchFieldNormalizerInterface', 'Drupal\elasticsearch_helper_content\Annotation\ElasticsearchFieldNormalizer');

    $this->alterInfo('elasticsearch_normalizer_field_info');
    $this->setCacheBackend($cache_backend, 'elasticsearch_normalizer_field_plugins');
  }

  /**
   * {@inheritdoc}
   *
   * @see \Drupal\elasticsearch_helper_content\Plugin\ElasticsearchNormalizer\Entity\FieldConfiguration::getType()
   */
  public function getDefinitionsByFieldType($type) {
    $definitions = $this->getDefinitions();

    $non_entity_field_types = [
      FieldTypeInterface::ENTITY,
      FieldTypeInterface::CUSTOM,
    ];

    if (in_array($type, $non_entity_field_types)) {
      $plugin_field_types = [$type];
    }
    // Add the field type and the "list" field type for entity fields.
    else {
      $plugin_field_types = [$type, 'list'];
    }

    $result = array_filter($definitions, function ($definition) use ($type, $plugin_field_types) {
      if (isset($definition['field_types'])) {
        // Qualify the definition if it supports the given type or all types.
        return array_intersect($plugin_field_types, $definition['field_types']);
      }

      return FALSE;
    });

    // Sort the plugins.
    uasort($result, [SortArray::class, 'sortByWeightElement']);

    return $result;
  }

}
