<?php

namespace Drupal\elasticsearch_helper_content\Plugin\ElasticsearchNormalizer\Field;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Field\FieldItemInterface;
use Drupal\elasticsearch_helper\Elasticsearch\Index\FieldDefinition;
use Drupal\elasticsearch_helper_content\ElasticsearchFieldNormalizerBase;

/**
 * The link field normalizer plugin class.
 *
 * @ElasticsearchFieldNormalizer(
 *   id = "link",
 *   label = @Translation("Link (URI, title)"),
 *   field_types = {
 *     "link"
 *   },
 * )
 */
class Link extends ElasticsearchFieldNormalizerBase {

  /**
   * {@inheritdoc}
   */
  public function getFieldItemValue(EntityInterface $entity, FieldItemInterface $item, array $context = []) {
    // @todo Return relative or absolute URL. Make the option configurable.
    return [
      'uri' => $item->get('uri')->getValue(),
      'title' => $item->get('title')->getValue(),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getFieldDefinition() {
    $definition = FieldDefinition::create('object')
      ->addProperty('uri', FieldDefinition::create('keyword'))
      ->addProperty('title', FieldDefinition::create('text'));

    return $definition;
  }

}
