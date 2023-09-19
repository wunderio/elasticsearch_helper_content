<?php

namespace Drupal\elasticsearch_helper_content\Plugin\ElasticsearchNormalizer\Field;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Field\FieldItemInterface;
use Drupal\elasticsearch_helper\Elasticsearch\Index\FieldDefinition;

/**
 * The entity reference ID field normalizer plugin class.
 *
 * @ElasticsearchFieldNormalizer(
 *   id = "entity_reference_id",
 *   label = @Translation("Entity reference (ID)"),
 *   field_types = {
 *     "entity_reference"
 *   },
 *   weigth = 5
 * )
 */
class EntityReferenceId extends EntityReference {

  /**
   * {@inheritdoc}
   *
   * @return int|string
   *   The referenced entity ID.
   */
  protected function getReferencedEntityValues(EntityInterface $referenced_entity, FieldItemInterface $field_item, EntityInterface $entity, array $context = []) {
    return $referenced_entity->id();
  }

  /**
   * {@inheritdoc}
   */
  public function getFieldDefinition() {
    return FieldDefinition::create('keyword');
  }

}
