<?php

namespace Drupal\elasticsearch_helper_content\Plugin\ElasticsearchNormalizer\Field;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Field\FieldItemInterface;
use Drupal\elasticsearch_helper\Elasticsearch\Index\FieldDefinition;

/**
 * The link field normalizer plugin class.
 *
 * @ElasticsearchFieldNormalizer(
 *   id = "link",
 *   label = @Translation("Link (URI, label)"),
 *   field_types = {
 *     "link"
 *   }
 * )
 */
class Link extends LinkBase {

  /**
   * {@inheritdoc}
   */
  public function getFieldItemValue(EntityInterface $entity, FieldItemInterface $item, array $context = []) {
    $label = $item->get('title')->getValue();
    $uri = $this->getUri($item->get('uri')->getValue());

    return [
      'uri' => $uri,
      'label' => $label,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getFieldDefinition() {
    return FieldDefinition::create('object')
      ->addProperty('uri', FieldDefinition::create('keyword'))
      ->addProperty('label', FieldDefinition::create('text')
        ->addMultiField('keyword', FieldDefinition::create('keyword'))
      );
  }

}
