<?php

namespace Drupal\elasticsearch_helper_content\Plugin\ElasticsearchNormalizer\Entity;

use Drupal\Core\Field\FieldDefinitionInterface;

/**
 * Field normalizer field instance.
 */
class FieldConfiguration {

  /**
   * Defines the default entity field type.
   */
  const TYPE_ENTITY_FIELD_ANY = 'any';

  /**
   * Defines the extra field type.
   */
  const TYPE_EXTRA_FIELD = '_extra_field';

  /**
   * Target entity type.
   *
   * @var string
   */
  protected $targetEntityType;

  /**
   * Target bundle.
   *
   * @var string
   */
  protected $targetBundle;

  /**
   * Field configuration.
   *
   * @var array
   */
  protected $configuration = [];

  /**
   * Field type.
   *
   * @var string
   */
  protected $type;

  /**
   * Field constructor.
   *
   * @param $entity_type
   * @param $bundle
   * @param array $configuration
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
   *
   * @return static
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
   * @param $entity_type
   * @param $bundle
   * @param array $configuration
   *
   * @return static
   */
  public static function createFromConfiguration($entity_type, $bundle, array $configuration) {
    return new static($entity_type, $bundle, $configuration);
  }

  /**
   * Returns field normalizer plugin manager instance.
   *
   * @return \Drupal\elasticsearch_helper_content\ElasticsearchFieldNormalizerManagerInterface $field_normalizer_manager
   */
  public function getFieldNormalizerManager() {
    return \Drupal::service('plugin.manager.elasticsearch_field_normalizer');
  }

  /**
   * Returns entity field manager instance.
   *
   * @return \Drupal\Core\Entity\EntityFieldManagerInterface
   */
  public function getEntityFieldManager() {
    return \Drupal::service('entity_field.manager');
  }

  /**
   * Sets field configuration.
   *
   * @param array $configuration
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
    if (!$this->configuration['normalizer']) {
      $available_normalizers = $this->getAvailableFieldNormalizerDefinitions();
      $this->configuration['normalizer'] = key($available_normalizers);
    }
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
   * Returns field label.
   *
   * @return string
   */
  public function getLabel() {
    return $this->configuration['label'];
  }

  /**
   * Sets the label.
   *
   * @param $label
   *
   * @return void
   */
  public function setLabel($label) {
    $this->configuration['label'] = $label;
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
   * Sets the field name.
   *
   * @param $field_name
   *
   * @return void
   */
  public function setFieldName($field_name) {
    $this->configuration['field_name'] = $field_name;
  }

  /**
   * Returns entity field name.
   *
   * @return string
   */
  public function getEntityFieldName() {
    return $this->configuration['entity_field_name'];
  }

  /**
   * Sets entity field name.
   *
   * @param string $entity_field_name
   *
   * @return void
   */
  public function setEntityFieldName($entity_field_name) {
    $this->configuration['entity_field_name'] = $entity_field_name;
  }

  /**
   * Returns normalizer plugin ID.
   *
   * @return string
   */
  public function getNormalizer() {
    return $this->configuration['normalizer'];
  }

  /**
   * Sets normalizer plugin ID.
   *
   * @param $normalizer
   *
   * @return void
   */
  public function setNormalizer($normalizer) {
    $this->configuration['normalizer'] = $normalizer;
  }

  /**
   * Returns normalizer configuration.
   *
   * @return array
   */
  public function getNormalizerConfiguration() {
    return $this->configuration['normalizer_configuration'];
  }

  /**
   * Sets normalizer configuration.
   *
   * @param array $configuration
   *
   * @return void
   */
  public function setNormalizerConfiguration(array $configuration) {
    $this->configuration['normalizer_configuration'] = $configuration;
  }

  /**
   * Returns field type.
   *
   * @return string
   *
   * @see \Drupal\elasticsearch_helper_content\ElasticsearchFieldNormalizerManager::getDefinitionsByFieldType()
   */
  public function getType() {
    if (is_null($this->type)) {
      $entity_field_name = $this->getEntityFieldName();

      // Entity fields by default get "any" as a field type.
      // Custom fields are considered to be extra fields.
      $this->type = $entity_field_name ? static::TYPE_ENTITY_FIELD_ANY : static::TYPE_EXTRA_FIELD;

      if ($entity_field_name) {
        $field_definitions = $this->getEntityFieldManager()->getFieldDefinitions($this->targetEntityType, $this->targetBundle);

        if (isset($field_definitions[$entity_field_name])) {
          $this->type = $field_definitions[$entity_field_name]->getType();
        }
      }
    }

    return $this->type;
  }

  /**
   * Returns entity field label.
   *
   * @param $entity_field_name
   *
   * @return \Drupal\Core\StringTranslation\TranslatableMarkup|string|null
   */
  public function getEntityFieldLabel($entity_field_name) {
    $field_definitions = $this->getEntityFieldManager()->getFieldDefinitions($this->targetEntityType, $this->targetBundle);

    if (isset($field_definitions[$entity_field_name])) {
      return $field_definitions[$entity_field_name]->getLabel();
    }

    return NULL;
  }

  /**
   * Returns field normalizer plugin instance.
   *
   * @return \Drupal\elasticsearch_helper_content\ElasticsearchFieldNormalizerInterface
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
   */
  public function getAvailableFieldNormalizerDefinitions() {
    $type = $this->getType();

    return $this->getFieldNormalizerManager()->getDefinitionsByFieldType($type);
  }

}