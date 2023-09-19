<?php

namespace Drupal\elasticsearch_helper_content\Plugin\ElasticsearchNormalizer\Field;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Field\FieldItemInterface;
use Drupal\elasticsearch_helper\Elasticsearch\Index\FieldDefinition;

/**
 * The boolean field normalizer plugin class.
 *
 * @ElasticsearchFieldNormalizer(
 *   id = "boolean",
 *   label = @Translation("Boolean"),
 *   field_types = {
 *     "boolean"
 *   }
 * )
 */
class Boolean extends FieldNormalizerBase {

  /**
   * {@inheritdoc}
   */
  public function getFieldItemValue(EntityInterface $entity, FieldItemInterface $item, array $context = []) {
    return (boolean) $item->value;
  }

  /**
   * {@inheritdoc}
   */
  public function getFieldDefinition() {
    return FieldDefinition::create('boolean');
  }

}
