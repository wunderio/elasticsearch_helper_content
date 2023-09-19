<?php

namespace Drupal\elasticsearch_helper_content\Plugin\ElasticsearchNormalizer\Field;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Field\FieldItemInterface;
use Drupal\elasticsearch_helper\Elasticsearch\Index\FieldDefinition;

/**
 * The integer field normalizer plugin class.
 *
 * @ElasticsearchFieldNormalizer(
 *   id = "integer",
 *   label = @Translation("Integer"),
 *   field_types = {
 *     "integer"
 *   }
 * )
 */
class Integer extends FieldNormalizerBase {

  /**
   * {@inheritdoc}
   */
  public function getFieldItemValue(EntityInterface $entity, FieldItemInterface $item, array $context = []) {
    return (int) $item->value;
  }

  /**
   * {@inheritdoc}
   */
  public function getFieldDefinition() {
    return FieldDefinition::create('integer');
  }

}
