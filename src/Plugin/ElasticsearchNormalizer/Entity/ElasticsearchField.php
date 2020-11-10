<?php

namespace Drupal\elasticsearch_helper_content\Plugin\ElasticsearchNormalizer\Entity;

use Drupal\Core\Field\FieldDefinitionInterface;

/**
 * Class ElasticsearchField
 */
class ElasticsearchField {

  /**
   * @var array
   */
  protected $configuration = [];

  /**
   * @var string
   */
  protected $type;

  /**
   * ElasticsearchField constructor.
   *
   * @param array $configuration
   * @param string|null $type
   */
  public function __construct(array $configuration, $type = NULL) {
    $this->configuration = $configuration;
    $this->type = $type;
  }

  /**
   * Creates Elasticsearch field object from entity field definition.
   *
   * @param \Drupal\Core\Field\FieldDefinitionInterface $field_definition
   *
   * @return static
   */
  public static function createFromFieldDefinition(FieldDefinitionInterface $field_definition) {
    return new static(
      [
        'field_name' => $field_definition->getName(),
        'label' => $field_definition->getLabel(),
      ],
      $field_definition->getType());
  }

  /**
   * Creates Elasticsearch field object from field configuration.
   *
   * @param array $configuration
   *
   * @return static
   */
  public static function createFromConfiguration(array $configuration) {
    return new static($configuration);
  }

  /**
   * Returns complete configuration array.
   *
   * @return array
   */
  public function getConfiguration() {
    return $this->configuration;
  }

  /**
   * Returns field name.
   *
   * @return string
   */
  public function getFieldName() {
    return $this->configuration['field_name'];
  }

  /**
   * Returns field label.
   *
   * @return string
   */
  public function getLabel() {
    return $this->configuration['label'];
  }

  /**
   * Returns field type.
   *
   * @return string
   */
  public function getType() {
    return $this->type;
  }

}
