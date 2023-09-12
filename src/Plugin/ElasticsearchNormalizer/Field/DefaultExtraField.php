<?php

namespace Drupal\elasticsearch_helper_content\Plugin\ElasticsearchNormalizer\Field;

use Drupal\elasticsearch_helper\Elasticsearch\Index\FieldDefinition;
use Drupal\elasticsearch_helper_content\ElasticsearchFieldNormalizerBase;

/**
 * The default extra field normalizer plugin class.
 *
 * @ElasticsearchFieldNormalizer(
 *   id = "extra_field_default",
 *   label = @Translation("Default extra field"),
 *   field_types = {
 *     "_extra_field"
 *   }
 * )
 */
class DefaultExtraField extends ElasticsearchFieldNormalizerBase {

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
