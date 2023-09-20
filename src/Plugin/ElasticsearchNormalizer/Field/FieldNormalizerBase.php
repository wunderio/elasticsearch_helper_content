<?php

namespace Drupal\elasticsearch_helper_content\Plugin\ElasticsearchNormalizer\Field;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Field\FieldItemInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\elasticsearch_helper_content\Plugin\ElasticsearchNormalizer\ElasticsearchNormalizerBase;

/**
 * The Elasticsearch field normalizer base class.
 */
abstract class FieldNormalizerBase extends ElasticsearchNormalizerBase implements FieldNormalizerInterface {

  /**
   * {@inheritdoc}
   */
  public function normalize($entity, $field, array $context = []) {
    $result = $this->getEmptyFieldValue($entity, $field, $context);

    try {
      if ($field) {
        $cardinality = $this->getCardinality($field);

        foreach ($field as $field_item) {
          $value = $this->getFieldItemValue($entity, $field_item, $context);

          if ($cardinality === 1) {
            $result = $value;
            break;
          }

          // Do not pass empty strings.
          if ($value !== '') {
            $result[] = $value;
          }
        }
      }
    }
    catch (\Exception $e) {
      watchdog_exception('elasticsearch_helper_content', $e);
    }

    return $result;
  }

  /**
   * Returns field cardinality.
   *
   * Defaults to 1 if cardinality cannot be established from field definition.
   *
   * @param \Drupal\Core\Field\FieldItemListInterface $field
   *   THe field item list instance.
   *
   * @return int
   *   The cardinality of the field.
   */
  public function getCardinality($field) {
    if ($field instanceof FieldItemListInterface) {
      return $field->getFieldDefinition()->getFieldStorageDefinition()->getCardinality();
    }

    return 1;
  }

  /**
   * Returns value of the field item.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The index-able entity.
   * @param \Drupal\Core\Field\FieldItemInterface $item
   *   The field item instance.
   * @param array $context
   *   Context options for the normalizer.
   *
   * @return mixed
   *   The field item value.
   */
  public function getFieldItemValue(EntityInterface $entity, FieldItemInterface $item, array $context = []) {
    return '';
  }

  /**
   * {@inheritdoc}
   */
  public function getEmptyFieldValue($entity, $field, array $context = []) {
    // Get field cardinality.
    $cardinality = $this->getCardinality($field);

    // Return NULL for single value fields, an array for multi-field values.
    return $cardinality == 1 ? NULL : [];
  }

  /**
   * {@inheritdoc}
   */
  public function configurationSummary() {
    return [];
  }

}
