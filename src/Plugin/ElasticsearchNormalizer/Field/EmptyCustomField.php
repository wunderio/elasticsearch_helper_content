<?php

namespace Drupal\elasticsearch_helper_content\Plugin\ElasticsearchNormalizer\Field;

use Drupal\elasticsearch_helper\Elasticsearch\Index\FieldDefinition;

/**
 * The empty custom field normalizer plugin class.
 *
 * @ElasticsearchFieldNormalizer(
 *   id = "custom_field_empty",
 *   label = @Translation("Empty custom field"),
 *   field_types = {
 *     "any"
 *   },
 *   weight = 100
 * )
 */
class EmptyCustomField extends FieldNormalizerBase {

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
