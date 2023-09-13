<?php

namespace Drupal\elasticsearch_helper_content\Plugin\ElasticsearchNormalizer\Entity;

use Drupal\Core\Field\FieldDefinitionInterface;

/**
 * Field normalizer field instance.
 */
class FieldConfiguration {

  /**
   * Defines the broken field type.
   *
   * This type is used when entity field cannot be found.
   */
  const TYPE_BROKEN = '_broken';

  /**
   * Defines the extra field type.
   */
  const TYPE_EXTRA_FIELD = '_extra_field';

  /**
   * The target entity type.
   *
   * @var string
   */
  protected $targetEntityType;

  /**
   * The target bundle.
   *
   * @var string
   */
  protected $targetBundle;

  /**
   * The field configuration array.
   *
   * @var array
   */
  protected $configuration = [];

  /**
   * The field type.
   *
   * @var string
   */
  protected $type;

  /**
   * Field constructor.
   *
   * @param string $entity_type
   *   The entity type ID.
   * @param string $bundle
   *   The bundle name.
   * @param array $configuration
   *   The field configuration array.
   */
  public function __construct($entity_type, $bundle, array $configuration) {
    $this->targetEntityType = $entity_type;
    $this->targetBundle = $bundle;
    $this->setConfiguration($configuration);
  }

  /**
   * Creates Elasticsearch field object from entity field definition.
   *
   * @param \Drupal\Core\Field\FieldDefinitionInterface $field_definition
   *   The field definition instance.
   *
   * @return static
   *   An instance of self.
   */
  public static function createFromFieldDefinition(FieldDefinitionInterface $field_definition) {
    return new static(
      $field_definition->getTargetEntityTypeId(),
      $field_definition->getTargetBundle(),
      [
        'field_name' => $field_definition->getName(),
        'entity_field_name' => $field_definition->getName(),
        'label' => $field_definition->getLabel(),
      ]
    );
  }

  /**
   * Creates Elasticsearch field object from field configuration.
   *
   * @param string $entity_type
   *   The entity type ID.
   * @param string $bundle
   *   The bundle name.
   * @param array $configuration
   *   The field configuration array.
   *
   * @return static
   */
  public static function createFromConfiguration($entity_type, $bundle, array $configuration) {
    return new static($entity_type, $bundle, $configuration);
  }

  /**
   * Returns field normalizer plugin manager instance.
   *
   * @return \Drupal\elasticsearch_helper_content\ElasticsearchFieldNormalizerManagerInterface
   *   The field normalizer manager instance.
   */
  public function getFieldNormalizerManager() {
    return \Drupal::service('plugin.manager.elasticsearch_field_normalizer');
  }

  /**
   * Returns entity field manager instance.
   *
   * @return \Drupal\Core\Entity\EntityFieldManagerInterface
   *   The entity field manager instance.
   */
  public function getEntityFieldManager() {
    return \Drupal::service('entity_field.manager');
  }

  /**
   * Sets field configuration.
   *
   * @param array $configuration
   *   The field configuration array.
   */
  public function setConfiguration(array $configuration) {
    $this->configuration = $configuration + [
      'label' => NULL,
      'field_name' => NULL,
      'entity_field_name' => NULL,
      'normalizer' => NULL,
      'normalizer_configuration' => [],
    ];

    // If no normalizer is defined, set it to first available normalizer.
    $this->setDefaultNormalizer();
  }

  /**
   * Returns complete configuration array.
   *
   * @return array
   *   The field configuration array.
   */
  public function getConfiguration() {
    return $this->configuration;
  }

  /**
   * Returns field label.
   *
   * @return string
   *   The field label.
   */
  public function getLabel() {
    return $this->configuration['label'];
  }

  /**
   * Sets the label.
   *
   * @param string $label
   *   The field label.
   */
  public function setLabel($label) {
    $this->configuration['label'] = $label;
  }

  /**
   * Returns field name.
   *
   * @return string
   *   The field name.
   */
  public function getFieldName() {
    return $this->configuration['field_name'];
  }

  /**
   * Sets the field name.
   *
   * @param string $field_name
   *   The field name.
   */
  public function setFieldName($field_name) {
    $this->configuration['field_name'] = $field_name;
  }

  /**
   * Returns entity field name.
   *
   * @return string
   *   The entity field name.
   */
  public function getEntityFieldName() {
    return $this->configuration['entity_field_name'];
  }

  /**
   * Sets entity field name.
   *
   * @param string $entity_field_name
   *   The entity field name.
   */
  public function setEntityFieldName($entity_field_name) {
    $this->configuration['entity_field_name'] = $entity_field_name;
  }

  /**
   * Returns TRUE if field is an entity field.
   *
   * @return bool
   *   Returns TRUE if field is an entity field.
   */
  public function isEntityField() {
    return !empty($this->getEntityFieldName());
  }

  /**
   * Returns TRUE if entity field exists.
   *
   * @return bool
   *   Returns TRUE if entity field exists.
   */
  public function isValidEntityField() {
    return (bool) $this->getEntityFieldDefinition();
  }

  /**
   * Returns TRUE if the field is valid.
   *
   * The valid field has the following properties:
   * - it's an extra field;
   * - it's an entity field which exists.
   *
   * The field configuration for an entity field which is no longer found
   * on the entity type is no longer valid.
   *
   * @return bool
   *   Returns TRUE if the field is valid.
   */
  public function isValidField() {
    $is_entity_field = $this->isEntityField();
    $is_valid_entity_field = $this->isValidEntityField();

    return !$is_entity_field || ($is_entity_field && $is_valid_entity_field);
  }

  /**
   * Returns field normalizer plugin ID.
   *
   * @return string
   *   The field normalizer plugin ID.
   */
  public function getNormalizer() {
    return $this->configuration['normalizer'];
  }

  /**
   * Sets field normalizer plugin ID.
   *
   * @param string $normalizer
   *   The field normalizer plugin ID.
   */
  public function setNormalizer($normalizer) {
    $this->configuration['normalizer'] = $normalizer;
  }

  /**
   * Sets default normalizer if it hasn't been defined.
   */
  protected function setDefaultNormalizer() {
    if (!$this->configuration['normalizer']) {
      $available_normalizers = $this->getAvailableFieldNormalizerDefinitions();
      $first_normalizer = key($available_normalizers);
      $this->setNormalizer($first_normalizer);
    }
  }

  /**
   * Returns field normalizer configuration.
   *
   * @return array
   *   The field normalizer configuration array.
   */
  public function getNormalizerConfiguration() {
    return $this->configuration['normalizer_configuration'];
  }

  /**
   * Sets field normalizer configuration.
   *
   * @param array $configuration
   *   The field normalizer configuration array.
   */
  public function setNormalizerConfiguration(array $configuration) {
    $this->configuration['normalizer_configuration'] = $configuration;
  }

  /**
   * Returns field type.
   *
   * @param bool $reset
   *   A boolean indicating that type needs to be reset and recalculated.
   *
   * @return string
   *   The field type. Possible values are the field types provided by the
   *   Drupal core or other contrib modules, or special values like "any" or
   *   "_extra_field".
   *
   * @see \Drupal\elasticsearch_helper_content\ElasticsearchFieldNormalizerManager::getDefinitionsByFieldType()
   */
  public function getType($reset = FALSE) {
    if (is_null($this->type)) {
      $entity_field_name = $this->getEntityFieldName();

      // Entity fields return their own type. If field is not found, the
      // type is set to "broken".
      if ($entity_field_name) {
        if ($field_definition = $this->getEntityFieldDefinition()) {
          $this->type = $field_definition->getType();
        }
        else {
          $this->type = static::TYPE_BROKEN;
        }
      }
      // Custom fields are considered to be extra fields.
      else {
        $this->type = static::TYPE_EXTRA_FIELD;
      }
    }

    return $this->type;
  }

  /**
   * Returns the entity field definition.
   *
   * @return \Drupal\Core\Field\FieldDefinitionInterface|null
   *   The entity field definition or null.
   */
  public function getEntityFieldDefinition() {
    if ($entity_field_name = $this->getEntityFieldName()) {
      $field_definitions = $this->getEntityFieldManager()->getFieldDefinitions($this->targetEntityType, $this->targetBundle);

      return $field_definitions[$entity_field_name] ?? NULL;
    }

    return NULL;
  }

  /**
   * Returns entity field label.
   *
   * @return \Drupal\Core\StringTranslation\TranslatableMarkup|string|null
   *   The entity field label.
   */
  public function getEntityFieldLabel() {
    if ($field_definition = $this->getEntityFieldDefinition()) {
      return $field_definition->getLabel();
    }

    return NULL;
  }

  /**
   * Returns field normalizer plugin instance.
   *
   * @return \Drupal\elasticsearch_helper_content\ElasticsearchFieldNormalizerInterface
   *   The Elasticsearch field normalizer plugin instance.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   */
  public function createNormalizerInstance() {
    $normalizer_configuration = $this->getNormalizerConfiguration();

    $normalizer_configuration['entity_type'] = $this->targetEntityType;
    $normalizer_configuration['bundle'] = $this->targetBundle;

    /** @var \Drupal\elasticsearch_helper_content\ElasticsearchFieldNormalizerInterface $instance */
    $instance = $this->getFieldNormalizerManager()->createInstance($this->getNormalizer(), $normalizer_configuration);

    return $instance;
  }

  /**
   * Returns a list of available field normalizer definitions.
   *
   * @return array
   *   The list of available field normalizer definitions.
   */
  public function getAvailableFieldNormalizerDefinitions() {
    $type = $this->getType();

    return $this->getFieldNormalizerManager()->getDefinitionsByFieldType($type);
  }

}
