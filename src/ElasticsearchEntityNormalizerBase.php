<?php

namespace Drupal\elasticsearch_helper_content;

use Drupal\elasticsearch_helper\Elasticsearch\Index\FieldDefinition;
use Drupal\elasticsearch_helper\Elasticsearch\Index\MappingDefinition;

/**
 * The Elasticsearch entity normalizer base class.
 */
abstract class ElasticsearchEntityNormalizerBase extends ElasticsearchNormalizerBase implements ElasticsearchEntityNormalizerInterface {

  /**
   * {@inheritdoc}
   *
   * Returns most common entity fields.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   */
  public function normalize($entity, array $context = []) {
    $entity_type_id = $entity->getEntityTypeId();
    $bundle = $entity->bundle();

    $data['id'] = $entity->id();
    $data['uuid'] = $entity->uuid();
    $data['entity_type'] = $entity_type_id;
    $data['bundle'] = $bundle;
    $data['langcode'] = $entity->language()->getId();

    return $data;
  }

  /**
   * Returns mapping definition for the most common entity fields.
   *
   * @return \Drupal\elasticsearch_helper\Elasticsearch\Index\MappingDefinition
   */
  public function getDefaultMappingDefinition() {
    return MappingDefinition::create()
      ->addProperty('id', FieldDefinition::create('integer'))
      ->addProperty('uuid', FieldDefinition::create('keyword'))
      ->addProperty('entity_type', FieldDefinition::create('keyword'))
      ->addProperty('bundle', FieldDefinition::create('keyword'))
      ->addProperty('langcode', FieldDefinition::create('keyword'));
  }

}
