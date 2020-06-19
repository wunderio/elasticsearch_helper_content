<?php

namespace Drupal\elasticsearch_helper_content;

use Drupal\Component\Plugin\ConfigurableInterface;
use Drupal\Component\Plugin\PluginInspectionInterface;
use Drupal\Core\Plugin\PluginFormInterface;

/**
 * Defines interface for Elasticsearch field normalizer plugins.
 */
interface ElasticsearchEntityNormalizerInterface extends PluginInspectionInterface, ConfigurableInterface, PluginFormInterface {

  /**
   * Normalizes an object into a set of arrays/scalars.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   * @param array $context
   *
   * @return array|string|int|float|bool
   */
  public function normalize($entity, array $context = []);

  /**
   * Returns index mapping definition.
   *
   * @return \Drupal\elasticsearch_helper\Elasticsearch\Index\MappingDefinition|null
   */
  public function getMappingDefinition();
}
