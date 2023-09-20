<?php

namespace Drupal\elasticsearch_helper_content\Plugin\ElasticsearchNormalizer\Entity;

use Drupal\Component\Plugin\ConfigurableInterface;
use Drupal\Component\Plugin\PluginInspectionInterface;
use Drupal\Core\Plugin\PluginFormInterface;

/**
 * Defines interface for Elasticsearch field normalizer plugins.
 */
interface EntityNormalizerInterface extends PluginInspectionInterface, ConfigurableInterface, PluginFormInterface {

  /**
   * Normalizes an object into a set of arrays/scalars.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The index-able entity.
   * @param array $context
   *   The context array.
   *
   * @return array|string|int|float|bool
   *   The normalized entity representation.
   */
  public function normalize($entity, array $context = []);

  /**
   * Returns index mapping definition.
   *
   * @return \Drupal\elasticsearch_helper\Elasticsearch\Index\MappingDefinition|null
   *   The index mapping definition.
   */
  public function getMappingDefinition();

}
