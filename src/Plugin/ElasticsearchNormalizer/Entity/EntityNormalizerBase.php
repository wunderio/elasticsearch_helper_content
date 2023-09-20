<?php

namespace Drupal\elasticsearch_helper_content\Plugin\ElasticsearchNormalizer\Entity;

use Drupal\elasticsearch_helper\Elasticsearch\Index\FieldDefinition;
use Drupal\elasticsearch_helper\Elasticsearch\Index\MappingDefinition;
use Drupal\elasticsearch_helper_content\Plugin\ElasticsearchNormalizer\ElasticsearchNormalizerBase;

/**
 * The Elasticsearch entity normalizer base class.
 */
abstract class EntityNormalizerBase extends ElasticsearchNormalizerBase implements EntityNormalizerInterface {

  /**
   * {@inheritdoc}
   *
   * Returns most common entity fields.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The index-able entity.
   * @param array $context
   *   The context array.
   */
  public function normalize($entity, array $context = []) {
    $entity_type_id = $entity->getEntityTypeId();
    $bundle = $entity->bundle();

    $data['entity_type'] = $entity_type_id;
    $data['bundle'] = $bundle;
    $data['id'] = $entity->id();
    $data['langcode'] = $entity->language()->getId();

    return $data;
  }

  /**
   * Returns mapping definition for the default fields.
   *
   * @return \Drupal\elasticsearch_helper\Elasticsearch\Index\MappingDefinition
   *   The mapping definition.
   *
   * @see \Drupal\elasticsearch_helper_content\Plugin\ElasticsearchNormalizer\Entity\FieldNormalizer::getDefaultFields()
   */
  public function getDefaultMappingDefinition() {
    return MappingDefinition::create()
      ->addProperty('entity_type', FieldDefinition::create('keyword'))
      ->addProperty('bundle', FieldDefinition::create('keyword'))
      ->addProperty('id', FieldDefinition::create('keyword'))
      ->addProperty('langcode', FieldDefinition::create('keyword'));
  }

}
