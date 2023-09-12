<?php

namespace Drupal\elasticsearch_helper_content;

use Drupal\Component\Plugin\ConfigurableInterface;
use Drupal\Component\Plugin\PluginInspectionInterface;
use Drupal\Core\Plugin\PluginFormInterface;

/**
 * Defines interface for Elasticsearch field normalizer plugins.
 */
interface ElasticsearchFieldNormalizerInterface extends PluginInspectionInterface, ConfigurableInterface, PluginFormInterface {

  /**
   * Normalizes field item list into a scalar value or an array.
   *
   * @param mixed $entity
   *   The index-able entity.
   * @param \Drupal\Core\Field\FieldItemListInterface|null $field
   *   The field item list instance.
   * @param array $context
   *   The context array.
   *
   * @return array|string|int|float|bool|null
   *   The normalized representation of the entity.
   */
  public function normalize($entity, $field, array $context = []);

  /**
   * Returns field definition.
   *
   * This method should return the same data structure that normalize()
   * method returns.
   *
   * Example with scalar values:
   *
   *   - normalize():
   *
   *   return "foo";
   *
   *   - getFieldDefinition():
   *
   *   return getFieldDefinition::create('text');
   *
   * Example with complex structure:
   *
   *   - normalize():
   *
   *    return [
   *      'string_value' => "foo",
   *      'number_value' => 123,
   *      'elements' => [
   *        'one' => 'alpha',
   *        'two' => 'beta',
   *      ],
   *    ]
   *   - getFieldDefinition():
   *
   *    $elements = getFieldDefinition::create('object')
   *      ->addProperty('one', FieldDefinition::create('keyword'))
   *      ->addProperty('two', FieldDefinition::create('keyword'));
   *
   *    return getFieldDefinition::create('object')
   *      ->addProperty('string_value', FieldDefinition::create('text'))
   *      ->addProperty('number_value', FieldDefinition::create('integer'))
   *      ->addProperty('elements', $elements);
   *
   * @return \Drupal\elasticsearch_helper\Elasticsearch\Index\FieldDefinition
   *   The field definition instance.
   */
  public function getFieldDefinition();

  /**
   * Returns empty field value.
   *
   * @param mixed $entity
   *   The index-able entity.
   * @param \Drupal\Core\Field\FieldItemListInterface|null $field
   *   The field item list instance.
   * @param array $context
   *   The context array.
   *
   * @return array|string|int|float|bool|null
   *   The empty field value.
   */
  public function getEmptyFieldValue($entity, $field, array $context = []);

}
