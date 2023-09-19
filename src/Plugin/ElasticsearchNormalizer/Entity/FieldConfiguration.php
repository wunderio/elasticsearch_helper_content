<?php

namespace Drupal\elasticsearch_helper_content\Plugin\ElasticsearchNormalizer\Entity;

use Drupal\Core\Field\FieldDefinitionInterface;

/**
 * Field normalizer field instance class.
 */
class FieldConfiguration {

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
   * The field configuration metadata array.
   *
   * For internal use only.
   *
   * @var array
   */
  protected $metadata = [];

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
   * @param array $metadata
   *   The metadata array.
   */
  public function __construct($entity_type, $bundle, array $configuration, array $metadata = []) {
    $this->targetEntityType = $entity_type;
    $this->targetBundle = $bundle;
    $this->setConfiguration($configuration);
    $this->metadata = $metadata;
  }

  /**
   * Creates Elasticsearch field object from entity field definition.
   *
   * @param \Drupal\Core\Field\FieldDefinitionInterface $field_definition
   *   The field definition instance.
   * @param array $metadata
   *   The metadata array.
   *
   * @return static
   *   An instance of self.
   */
  public static function createFromFieldDefinition(FieldDefinitionInterface $field_definition, array $metadata = []) {
    $entity_type = $field_definition->getTargetEntityTypeId();
    $bundle = $field_definition->getTargetBundle();
    $field_name = $field_definition->getName();

    // Get the entity key for the field.
    $entity_key = static::translateFieldNameToEntityKey($entity_type, $field_name);

    return new static(
      $entity_type,
      $bundle,
      [
        'field_name' => $entity_key ?: $field_name,
        'entity_field_name' => $field_name,
        'label' => $field_definition->getLabel(),
      ],
      $metadata
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
   * @param array $metadata
   *   The metadata array.
   *
   * @return static
   */
  public static function createFromConfiguration($entity_type, $bundle, array $configuration, array $metadata = []) {
    return new static($entity_type, $bundle, $configuration, $metadata);
  }

  /**
   * Returns field normalizer plugin manager instance.
   *
   * @return \Drupal\elasticsearch_helper_content\ElasticsearchFieldNormalizerManagerInterface
   *   The field normalizer manager instance.
   */
  public static function getFieldNormalizerManager() {
    return \Drupal::service('plugin.manager.elasticsearch_field_normalizer');
  }

  /**
   * Returns entity type manager instance.
   *
   * @return \Drupal\Core\Entity\EntityTypeManagerInterface
   *   The entity type manager instance.
   */
  public static function getEntityTypeManager() {
    return \Drupal::service('entity_type.manager');
  }

  /**
   * Returns entity field manager instance.
   *
   * @return \Drupal\Core\Entity\EntityFieldManagerInterface
   *   The entity field manager instance.
   */
  public static function getEntityFieldManager() {
    return \Drupal::service('entity_field.manager');
  }

  /**
   * Sets field configuration.
   *
   * @param array $configuration
   *   The field configuration array.
   */
  public function setConfiguration(array $configuration) {
    // Set explicitly defined field type.
    if (isset($configuration['type'])) {
      $this->setType($configuration['type']);
    }

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

      // Entity fields return their own type.
      if ($entity_field_name) {
        if ($field_definition = $this->getEntityFieldDefinition()) {
          $this->type = $field_definition->getType();
        }
        else {
          $this->type = FALSE;
        }
      }
      else {
        $this->type = FieldTypeInterface::CUSTOM;
      }
    }

    return $this->type;
  }

  /**
   * Sets the field type.
   *
   * @param string $type
   *   The field type.
   */
  public function setType($type) {
    $this->type = $type;
  }

  /**
   * Returns the field metadata.
   *
   * @return array
   *   The field metadata.
   */
  public function getMetadata() {
    return $this->metadata;
  }

  /**
   * Returns a boolean weather the field is a system field.
   *
   * @return bool
   *   Returns TRUE if the field is a system field.
   */
  public function isSystemField() {
    return !empty($this->metadata['system_field']);
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
   * Translates base field name into entity key name.
   *
   * @param string $entity_type_id
   *   The entity type ID.
   * @param string $field_name
   *   The field name.
   *
   * @return string
   *   The entity key name.
   */
  public static function translateFieldNameToEntityKey($entity_type_id, $field_name) {
    if ($entity_keys = static::getEntityTypeManager()->getDefinition($entity_type_id, FALSE)->getKeys()) {
      $index = array_search($field_name, $entity_keys);

      if ($index !== FALSE) {
        return $index;
      }
    }

    return NULL;
  }

  /**
   * Translates entity key name into base field name.
   *
   * @param string $entity_type_id
   *   The entity type ID.
   * @param string $entity_key
   *   The entity key name.
   *
   * @return string|null
   *   The base field name of the entity key.
   */
  public static function translateEntityKeyToFieldName($entity_type_id, $entity_key) {
    if ($entity_keys = static::getEntityTypeManager()->getDefinition($entity_type_id, FALSE)->getKeys()) {
      if (isset($entity_keys[$entity_key])) {
        return $entity_keys[$entity_key];
      }
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
