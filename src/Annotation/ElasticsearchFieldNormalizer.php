<?php

namespace Drupal\elasticsearch_helper_content\Annotation;

use Drupal\Component\Annotation\Plugin;

/**
 * Defines a Elasticsearch field normalizer item annotation object.
 *
 * @see \Drupal\elasticsearch_helper_content\ElasticsearchFieldNormalizerManager
 * @see plugin_api
 *
 * @Annotation
 */
class ElasticsearchFieldNormalizer extends Plugin {

  /**
   * The plugin ID.
   *
   * @var string
   */
  public $id;

  /**
   * The label of the plugin.
   *
   * @var \Drupal\Core\Annotation\Translation
   *
   * @ingroup plugin_translatable
   */
  public $label;

  /**
   * The field types that field normalizer supports.
   *
   * @var array
   */
  public $field_types;

  /**
   * Weight of the plugin.
   *
   * @var int
   */
  public $weight;

}
