<?php

namespace Drupal\elasticsearch_helper_content\Plugin\ElasticsearchNormalizer\Field;

use Drupal\elasticsearch_helper\Elasticsearch\Index\FieldDefinition;
use Drupal\elasticsearch_helper_content\ElasticsearchFieldNormalizerBase;

/**
 * The rendered entity field normalizer plugin class.
 *
 * This field normalizer is able to render any content entity.
 *
 * @ElasticsearchFieldNormalizer(
 *   id = "rendered_entity",
 *   label = @Translation("Rendered entity"),
 *   field_types = {
 *     "entity"
 *   },
 *   weight = 100
 * )
 */
class RenderedEntity extends ElasticsearchFieldNormalizerBase {

  /**
   * {@inheritdoc}
   */
  public function normalize($entity, $field, array $context = []) {
    // Return an empty string.
    return '';
  }

  /**
   * {@inheritdoc}
   */
  public function getFieldDefinition() {
    return FieldDefinition::create('keyword');
  }

}
