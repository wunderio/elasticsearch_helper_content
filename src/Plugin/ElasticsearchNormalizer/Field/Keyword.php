<?php

namespace Drupal\elasticsearch_helper_content\Plugin\ElasticsearchNormalizer\Field;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Field\FieldItemInterface;
use Drupal\elasticsearch_helper\Elasticsearch\Index\FieldDefinition;

/**
 * The keyword field normalizer plugin class.
 *
 * @ElasticsearchFieldNormalizer(
 *   id = "keyword",
 *   label = @Translation("Keyword"),
 *   field_types = {
 *     "string",
 *     "uuid",
 *     "language",
 *     "text",
 *     "text_long",
 *     "text_with_summary",
 *     "list_string",
 *     "integer",
 *     "float",
 *     "decimal"
 *   },
 *   weight = 5
 * )
 */
class Keyword extends FieldNormalizerBase {

  /**
   * {@inheritdoc}
   */
  public function getFieldItemValue(EntityInterface $entity, FieldItemInterface $item, array $context = []) {
    return $item->value;
  }

  /**
   * {@inheritdoc}
   */
  public function getFieldDefinition() {
    return FieldDefinition::create('keyword');
  }

}
