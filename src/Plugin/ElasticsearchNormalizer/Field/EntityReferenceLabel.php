<?php

namespace Drupal\elasticsearch_helper_content\Plugin\ElasticsearchNormalizer\Field;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Field\FieldItemInterface;
use Drupal\elasticsearch_helper\Elasticsearch\Index\FieldDefinition;

/**
 * @ElasticsearchFieldNormalizer(
 *   id = "entity_reference_label",
 *   label = @Translation("Entity reference (label)"),
 *   field_types = {
 *     "entity_reference"
 *   }
 * )
 */
class EntityReferenceLabel extends EntityReference {

  /**
   * {@inheritdoc}
   *
   * @return string
   */
  protected function getReferencedEntityValues(EntityInterface $referenced_entity, FieldItemInterface $field_item, EntityInterface $entity, array $context = []) {
    return $referenced_entity->label();
  }

  /**
   * {@inheritdoc}
   */
  public function getFieldDefinition() {
    return FieldDefinition::create('text');
  }

}
