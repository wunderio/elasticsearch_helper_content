<?php

namespace Drupal\elasticsearch_helper_content\Plugin\ElasticsearchNormalizer\Field;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Field\FieldItemInterface;
use Drupal\Core\TypedData\TranslatableInterface;
use Drupal\elasticsearch_helper\Elasticsearch\Index\FieldDefinition;
use Drupal\elasticsearch_helper_content\ElasticsearchFieldNormalizerBase;

/**
 * The entity reference field normalizer plugin class.
 *
 * @ElasticsearchFieldNormalizer(
 *   id = "entity_reference",
 *   label = @Translation("Entity reference (ID, label)"),
 *   field_types = {
 *     "entity_reference"
 *   }
 * )
 */
class EntityReference extends ElasticsearchFieldNormalizerBase {

  /**
   * {@inheritDoc}
   */
  public function getFieldItemValue(EntityInterface $entity, FieldItemInterface $item, array $context = []) {
    $result = $this->getEmptyFieldValue($entity, NULL, $context);
    $langcode = $entity->language()->getId();

    if ($referenced_entity = $item->entity) {
      if ($referenced_entity instanceof TranslatableInterface) {
        $referenced_entity = \Drupal::service('entity.repository')->getTranslationFromContext($referenced_entity, $langcode);
      }

      $result = $this->getReferencedEntityValues($referenced_entity, $item, $entity, $context);
    }

    return $result;
  }

  /**
   * Returns values of the referenced entity.
   *
   * @param \Drupal\Core\Entity\EntityInterface $referenced_entity
   *   Referenced entity from the field item.
   * @param \Drupal\Core\Field\FieldItemInterface $field_item
   *   Field item from original entity.
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   Original entity.
   * @param array $context
   *   The context array.
   *
   * @return array
   *   The array with referenced entity ID and label as elements.
   */
  protected function getReferencedEntityValues(EntityInterface $referenced_entity, FieldItemInterface $field_item, EntityInterface $entity, array $context = []) {
    return [
      'id' => $referenced_entity->id(),
      'label' => $referenced_entity->label(),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getEmptyFieldValue($entity, $field, array $context = []) {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function getFieldDefinition() {
    $label_definition = FieldDefinition::create('text')
      ->addMultiField('keyword', FieldDefinition::create('keyword'));

    return FieldDefinition::create('object')
      ->addProperty('id', FieldDefinition::create('integer'))
      ->addProperty('label', $label_definition);
  }

}
