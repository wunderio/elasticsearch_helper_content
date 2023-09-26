<?php

namespace Drupal\elasticsearch_helper_content\Plugin\ElasticsearchNormalizer\Field;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Field\FieldItemInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\elasticsearch_helper\Elasticsearch\Index\FieldDefinition;

/**
 * The link URI field normalizer plugin class.
 *
 * @ElasticsearchFieldNormalizer(
 *   id = "link_uri",
 *   label = @Translation("Link (URI)"),
 *   field_types = {
 *     "link"
 *   },
 *   weight = 5
 * )
 */
class LinkUri extends LinkBase {

  use StringTranslationTrait;

  /**
   * {@inheritdoc}
   */
  public function getFieldItemValue(EntityInterface $entity, FieldItemInterface $item, array $context = []) {
    return $this->getUri($item->get('uri')->getValue());
  }

  /**
   * {@inheritdoc}
   */
  public function getFieldDefinition() {
    return FieldDefinition::create('keyword');
  }

}
