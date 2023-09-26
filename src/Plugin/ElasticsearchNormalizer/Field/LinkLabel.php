<?php

namespace Drupal\elasticsearch_helper_content\Plugin\ElasticsearchNormalizer\Field;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Field\FieldItemInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\elasticsearch_helper\Elasticsearch\Index\FieldDefinition;

/**
 * The link label field normalizer plugin class.
 *
 * @ElasticsearchFieldNormalizer(
 *   id = "link_label",
 *   label = @Translation("Link (label)"),
 *   field_types = {
 *     "link"
 *   },
 *   weight = 10
 * )
 */
class LinkLabel extends Keyword {

  use StringTranslationTrait;

  /**
   * {@inheritdoc}
   */
  public function getFieldItemValue(EntityInterface $entity, FieldItemInterface $item, array $context = []) {
    return $item->get('title')->getValue();
  }

  /**
   * {@inheritdoc}
   */
  public function getFieldDefinition() {
    return FieldDefinition::create('keyword');
  }

}
