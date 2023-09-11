<?php

namespace Drupal\elasticsearch_helper_content\Plugin\ElasticsearchNormalizer\Entity;

use Drupal\Component\Utility\Html;
use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Form\FormState;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Form\SubformState;
use Drupal\elasticsearch_helper_content\ElasticsearchEntityNormalizerBase;
use Drupal\elasticsearch_helper_content\ElasticsearchExtraFieldManager;
use Drupal\elasticsearch_helper_content\ElasticsearchFieldNormalizerInterface;
use Drupal\elasticsearch_helper_content\ElasticsearchFieldNormalizerManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * @ElasticsearchEntityNormalizer(
 *   id = "field",
 *   label = @Translation("Content entity field"),
 *   weight = 0
 * )
 */
class FieldNormalizer extends ElasticsearchEntityNormalizerBase {

  /**
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * @var \Drupal\Core\Entity\EntityFieldManagerInterface
   */
  protected $entityFieldManager;

  /**
   * @var \Drupal\elasticsearch_helper_content\ElasticsearchExtraFieldManager
   */
  protected $elasticsearchExtraFieldManager;

  /**
   * @var \Drupal\elasticsearch_helper_content\ElasticsearchFieldNormalizerManagerInterface
   */
  protected $elasticsearchFieldNormalizerManager;

  /**
   * ElasticsearchEntityFieldNormalizer constructor.
   *
   * @param array $configuration
   * @param $plugin_id
   * @param $plugin_definition
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   * @param \Drupal\Core\Entity\EntityFieldManagerInterface $entity_field_manager
   * @param \Drupal\elasticsearch_helper_content\ElasticsearchFieldNormalizerManagerInterface $elasticsearch_field_normalizer_manager
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, EntityTypeManagerInterface $entity_type_manager, EntityFieldManagerInterface $entity_field_manager, ElasticsearchExtraFieldManager $elasticsearch_extra_field_manager, ElasticsearchFieldNormalizerManagerInterface $elasticsearch_field_normalizer_manager) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);

    $this->entityTypeManager = $entity_type_manager;
    $this->entityFieldManager = $entity_field_manager;
    $this->elasticsearchExtraFieldManager = $elasticsearch_extra_field_manager;
    $this->elasticsearchFieldNormalizerManager = $elasticsearch_field_normalizer_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager'),
      $container->get('entity_field.manager'),
      $container->get('plugin.manager.elasticsearch_extra_field'),
      $container->get('plugin.manager.elasticsearch_field_normalizer')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'fields' => [],
    ] + parent::defaultConfiguration();
  }

  /**
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *
   * @return array|\Drupal\elasticsearch_helper_content\Plugin\ElasticsearchNormalizer\Entity\FieldConfiguration[]|mixed
   */
  public function getFieldConfiguration(FormStateInterface $form_state) {
    if ($form_state->isRebuilding()) {
      $field_configurations = $form_state->get('field_configurations') ?: [];
    }
    else {
      $field_configurations = $this->getFieldConfigurationInstances();
      $form_state->set('field_configurations', $field_configurations);
    }

    return $field_configurations;
  }

  /**
   * Returns a list of field configuration instances.
   *
   * @return \Drupal\elasticsearch_helper_content\Plugin\ElasticsearchNormalizer\Entity\FieldConfiguration[]
   */
  public function getFieldConfigurationInstances() {
    $fields = [];

    try {
      foreach ($this->configuration['fields'] as $delta => $field_configuration) {
        $fields[$delta] = FieldConfiguration::createFromConfiguration($this->targetEntityType, $this->targetBundle, $field_configuration);
      }
    }
    catch (\Exception $e) {
      watchdog_exception('elasticsearch_helper_content', $e);
    }

    return $fields;
  }

  /**
   * Returns a list of field normalizer instances.
   *
   * @return \Drupal\elasticsearch_helper_content\ElasticsearchFieldNormalizerInterface[]
   */
  protected function getFieldNormalizerInstances() {
    $instances = [];

    try {
      foreach ($this->getFieldConfigurationInstances() as $delta => $field) {
        $instances[$delta] = $field->createNormalizerInstance();
      }
    }
    catch (\Exception $e) {
      watchdog_exception('elasticsearch_helper_content', $e);
    }

    return $instances;
  }

  /**
   * {@inheritdoc}
   */
  public function normalize($entity, array $context = []) {
    $data = parent::normalize($entity, $context);

    try {
      // Get entity type instance.
      $entity_type = $this->entityTypeManager->getDefinition($entity->getEntityTypeId());

      foreach ($this->getFieldNormalizerInstances() as $field_name => $field_normalizer_instance) {
        // Convert field name if it's it's an entity key.
        $entity_field_name = $entity_type->getKey($field_name) ?: $field_name;
        // Set default field item list instance.
        $field = NULL;

        if ($entity->hasField($entity_field_name)) {
          $field = $entity->get($entity_field_name);
        }

        $data[$field_name] = $field_normalizer_instance->normalize($entity, $field, $context);
      }
    }
    catch (\Exception $e) {
      watchdog_exception('elasticsearch_helper_content', $e);
    }

    return $data;
  }

  /**
   * {@inheritdoc}
   */
  public function getMappingDefinition() {
    $properties = [];

    foreach ($this->getFieldNormalizerInstances() as $field_name => $field_normalizer_instance) {
      $properties[$field_name] = $field_normalizer_instance->getFieldDefinition();
    }

    return $this->getDefaultMappingDefinition()
      ->addProperties($properties);
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $entity_type_id = $this->targetEntityType;
    $bundle = $this->targetBundle;

//    $parent_form = &$form_state->getCompleteForm();
//    $parent_form['#entity_builders'][] = [[$this, 'entityBuilder']];

//    // Every form element on this form has "#field_delta" attribute.
//    if ($triggering_element && isset($triggering_element['#field_delta'])) {
//      $parent_offset = isset($triggering_element['#parent_offset']) ? $triggering_element['#parent_offset'] : NULL;
//      $form_parents = $this->getParentsArray($triggering_element['#parents'], $parent_offset);
//      $field_configurations = NestedArray::getValue($form_state->getUserInput(), $form_parents);
//    }
//    else {
//      $field_configurations = $this->configuration['fields'];
//    }

    if (!isset($entity_type_id, $bundle)) {
      return [];
    }

//    /** @var \Drupal\elasticsearch_helper_content\Plugin\ElasticsearchNormalizer\Entity\Field[] $fields */
//    $fields = array_map(function ($configuration) {
//      return Field::createFromConfiguration($configuration);
//    }, $this->configuration['fields']);

//    // Gather extra fields from Elasticsearch extra field plugins.
//    $extra_fields = $this->elasticsearchExtraFieldManager->getExtraFields();
//    // Merge extra fields with base fields.
//    $fields = array_merge($fields, $extra_fields);

    $form_id_string = 'elasticsearch-entity-field-normalizer-form';
    $form_id = Html::getId($form_id_string);
    $fields_table_id = Html::getId($form_id_string . '-table');

    $form += [
//      '#entity_builders' => [[$this, 'entityBuilder']],
//      '#tree' => TRUE,
      '#type' => 'container',
      '#id' => $form_id,
      'fields' => [
        '#id' => $fields_table_id,
        '#type' => 'table',
        '#title' => t('Title'),
        '#header' => [t('Label'), t('Field name'), t('Normalizer'), t('Settings')],
        '#empty' => t('There are no fields added.'),
        '#default_value' => [],
      ],
    ];

    $ajax_attribute = [
      'callback' => [$this, 'submitAjax'],
      'wrapper' => $form_id_string,
      'progress' => [
        'type' => 'throbber',
        'message' => NULL,
      ],
    ];

    if ($form_state->isRebuilding()) {
      $field_configurations = $form_state->get('field_configurations') ?: [];
    }
    else {
      $field_configurations = $this->getFieldConfigurationInstances();
      $form_state->set('field_configurations', $field_configurations);
    }

    // Loop over fields.
    foreach ($field_configurations as $delta => $field_configuration) {
      // Get field normalizer definitions.
      $field_normalizer_definitions = $field_configuration->getAvailableFieldNormalizerDefinitions();

      // Get field normalizer.
      $normalizer = $field_configuration->getNormalizer();

      // Get field name.
      $field_name = $field_configuration->getFieldName();

      $selected_field_delta = [$delta];
      $form_field_row = &$form['fields'][$delta];

      $form_field_row['label'] = [
        '#type' => 'hidden',
        '#value' => $field_configuration->getLabel(),
        '#suffix' => $field_configuration->getLabel(),
      ];

      $form_field_row['field_name'] = [
        '#type' => 'hidden',
        '#value' => $field_name,
        '#suffix' => $field_name,
      ];

      $form_field_row['normalizer'] = [
        '#type' => 'select',
        '#options' => array_map(function ($plugin) {
          return $plugin['label'];
        }, $field_normalizer_definitions),
        '#default_value' => $normalizer,
        '#access' => !empty($field_normalizer_definitions),
        '#selected_field_delta' => $selected_field_delta,
        '#ajax' => $ajax_attribute,
        '#op' => 'select_normalizer',
        '#submit' => [[$this, 'multistepSubmit']],
        '#parents' => ['normalizer_configuration', 'fields', $delta, 'normalizer'],
      ];
      $form_field_row['settings'] = [];

      try {
        $field_normalizer = $field_configuration->createNormalizerInstance();

//        // Check if normalizer instance is set and if it matches the selected
//        // normalizer.
//        if (!$this->instanceMatchesPluginId($normalizer, $field_normalizer)) {
//          $field_normalizer = $field_configuration->createNormalizerInstance();
//          $this->setStoredFieldNormalizerInstance($form_state, $field_normalizer, $delta);
//        }

        // Prepare the subform state.
        $configuration_form = [];
        $subform_state = SubformState::createForSubform($configuration_form, $form, $form_state);
        $configuration_form = $field_normalizer->buildConfigurationForm($configuration_form, $subform_state);

        if ($configuration_form) {
          $editable_field_delta = $form_state->get('editable_field_delta') ?: [];

          if ($editable_field_delta && strpos(implode('][', $editable_field_delta), implode('][', $selected_field_delta)) === 0) {
            $triggering_element = $form_state->getTriggeringElement();

            $form_field_row['settings'] = [
              '#type' => 'container',
            ];

            $form_field_row['settings']['configuration'] = $configuration_form;
            $form_field_row['settings']['configuration']['#parents'] = ['normalizer_configuration', 'fields', $delta];

            $form_field_row['settings']['actions'] = [
              '#type' => 'actions',
              'save_settings' => [
                '#type' => 'submit',
                '#value' => t('Update'),
//                '#name' => implode(':', $selected_field_delta) . '_update',
                '#parents' => ['field_configuration_update'],
                '#op' => 'update',
                '#submit' => [[$this, 'multistepSubmit']],
                '#selected_field_delta' => $selected_field_delta,
                '#ajax' => $ajax_attribute,
//                  '#return_form_parents' => array_merge($parent_form_array_parents, ['fields']),
                '#limit_validation_errors' => [$triggering_element['#array_parents']],
              ],
              'cancel_settings' => [
                '#type' => 'submit',
                '#value' => t('Cancel'),
//                '#name' => implode(':', $selected_field_delta) . '_cancel',
                '#parents' => ['field_configuration_cancel'],
                '#op' => 'cancel',
                '#submit' => [[$this, 'multistepSubmit']],
                '#selected_field_delta' => $selected_field_delta,
                '#ajax' => $ajax_attribute,
//                  '#return_form_parents' => array_merge($parent_form_array_parents, ['fields']),
                '#limit_validation_errors' => [$triggering_element['#array_parents']],
              ],
            ];
          }
          else {
            $form_field_row['settings'] = [
              '#type' => 'image_button',
              '#src' => 'core/misc/icons/787878/cog.svg',
              '#attributes' => ['alt' => t('Edit')],
              '#name' => implode(':', $selected_field_delta) . '_edit',
              '#parents' => ['field_configuration_edit'],
              '#return_value' => t('Configure'),
              '#op' => 'edit',
              '#submit' => [[$this, 'multistepSubmit']],
              '#selected_field_delta' => $selected_field_delta,
              '#ajax' => $ajax_attribute,
              '#limit_validation_errors' => [],
              //            '#return_form_parents' => array_merge($parent_form_array_parents, ['fields'])
            ];
          }
        }
      }
      catch (\Exception $e) {
        watchdog_exception('elasticsearch_helper_content', $e);
      }
    }

    $custom_field_states_condition = [
      ':input[name="add_field[entity_field_name]"]' => ['value' => '_custom'],
    ];

    $form['add_field'] = [
      '#type' => 'details',
      '#title' => t('Add field'),
      '#parents' => ['add_field'],
      '#open' => TRUE,
      'entity_field_name' => [
        '#type' => 'select',
        '#options' => $this->getEntityFieldOptions($entity_type_id, $bundle) + [
          '_custom' => t('Custom field'),
        ],
        '#parents' => ['normalizer_configuration', 'add_field', 'entity_field_name'],
      ],
      'label' => [
        '#type' => 'textfield',
        '#title' => t('Field label'),
        '#maxlength' => 255,
        '#default_value' => NULL,
//        '#required' => TRUE,
        '#weight' => 5,
        '#states' => [
          'visible' => $custom_field_states_condition,
          'required' => $custom_field_states_condition,
        ],
        '#parents' => ['normalizer_configuration', 'add_field', 'label'],
      ],
      'field_name' => [
        '#type' => 'textfield',
        '#default_value' => NULL,
        '#title' => t('Field name'),
//        '#machine_name' => [
//          'exists' => '\Drupal\elasticsearch_helper_content\Entity\ElasticsearchContentIndex::load',
////          'source' => ['normalizer_configuration', 'configuration', 'add_field', 'label'],
//          'source' => ['normalizer_configuration', 'configuration', 'add_field_label'],
//          'label' => t('Field name'),
//        ],
        '#weight' => 10,
        '#states' => [
          'visible' => $custom_field_states_condition,
          'required' => $custom_field_states_condition,
        ],
        '#parents' => ['normalizer_configuration', 'add_field', 'field_name'],
      ],
      'actions' => [
        '#type' => 'actions',
        'add_button' => [
          '#type' => 'submit',
          '#value' => t('Add field'),
          '#op' => 'add_field',
          '#submit' => [[$this, 'multistepSubmit']],
          '#ajax' => $ajax_attribute,
//          '#return_form_parents' => ['normalizer_configuration', 'configuration'],
        ],
      ],
    ];

    return $form;
  }

  /**
   * Returns parents array.
   *
   * Generally form elements of this plugin's configuration form are two
   * levels away from the parent's form, hence -2 is assumed as an offset.
   *
   * @param array $source
   * @param int|null $offset
   *
   * @return array
   */
  protected function getParentsArray(array $source, $offset = NULL) {
    $offset = $offset ?: -2;
    array_splice($source, $offset);

    return $source;
  }

  /**
   * Ajax submit handler.
   *
   * @param $form
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *
   * @return array
   */
  public function submitAjax($form, FormStateInterface $form_state) {
//    $triggering_element = $form_state->getTriggeringElement();
//
//    if (isset($triggering_element['#return_form_parents'])) {
//      $form_parents = $triggering_element['#return_form_parents'];
//    }
//    else {
//      // @todo Remove this.
//      $parent_offset = isset($triggering_element['#parent_offset']) ? $triggering_element['#parent_offset'] : NULL;
//      $form_parents = $this->getParentsArray($triggering_element['#array_parents'], $parent_offset);
//    }

    $form_state->setRebuild();

    // Set changes on the content index entity.
    $form_state->getFormObject()->getEntity()->setNormalizerConfiguration($this->configuration);

    $form_parents = ['normalizer_configuration', 'configuration'];

    $return_form = NestedArray::getValue($form, $form_parents);

    return $return_form;
  }

  /**
   * Form element change submit handler.
   *
   * @param $form
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   */
  public function multistepSubmit($form, FormStateInterface $form_state) {
    $triggering_element = $form_state->getTriggeringElement();
    $op = $triggering_element['#op'];
    $selected_field_delta = $triggering_element['#selected_field_delta'];

    switch ($op) {
      case 'select_normalizer':

        break;

      case 'add_field':
        // Get add-field parents in form stage.
//        $parent_offset = isset($triggering_element['#parent_offset']) ? $triggering_element['#parent_offset'] : NULL;
//        $form_parents = $this->getParentsArray($triggering_element['#parents'], $parent_offset);

        $this->configuration['foo'] = 'bar';

        $field_configuration = [];

        // Get add-field submitted values.
        $add_field_values = $form_state->getValue(['normalizer_configuration', 'add_field']);

        if ($add_field_values['entity_field_name'] != '_custom') {
          // Get entity field label.
          $field_configuration['label'] = (string) $this->getEntityField($this->targetEntityType, $this->targetBundle, $add_field_values['entity_field_name'])->getLabel();

          // Get entity field key for selected entity field.
          $entity_field_key = $this->getEntityFieldKey($this->targetEntityType, $add_field_values['entity_field_name']);
          // If there's no key for entity field, use entity field as is.
          $field_configuration['field_name'] = $entity_field_key ?: $add_field_values['entity_field_name'];
          $field_configuration['entity_field_name'] = $add_field_values['entity_field_name'];
        }
        else {
          $field_configuration['label'] = $add_field_values['label'];
          $field_configuration['field_name'] = $add_field_values['field_name'];
        }

        // Store fields in form state to have then included in the entity.
//        $fields = &$form_state->getValue(['normalizer_configuration', 'fields']);
//        $fields = $fields ?: [];
//        $fields[] = $field_configuration;

        $fields = $form_state->get('field_configurations') ?: [];
        $fields[] = FieldConfiguration::createFromConfiguration($this->targetEntityType, $this->targetBundle, $field_configuration);
        $form_state->set('field_configurations', $fields);

        break;

      case 'edit':
        $form_state->set('editable_field_delta', $selected_field_delta);

        break;

      case 'update':
        $delta = reset($selected_field_delta);

        /** @var \Drupal\elasticsearch_helper_content\Plugin\ElasticsearchNormalizer\Entity\FieldConfiguration $field_configuration */
        $field_configuration = $form_state->get(['field_configurations', $delta]);
        $field_normalizer_instance = $field_configuration->createNormalizerInstance();

        // Trigger has the clue to parents array.
        $form_parents = $this->getParentsArray($triggering_element['#array_parents']);
        // Configuration add configuration form parent element.
//        $form_parents[] = 'configuration';

        if ($subform = &NestedArray::getValue($form, $form_parents)) {
          $subform_state = SubformState::createForSubform($subform, $form, $form_state);
          $field_normalizer_instance->submitConfigurationForm($subform, $subform_state);

          // Store field normalizer configuration.
//            $field = &$form_state->getValue(['normalizer_configuration', 'fields', $delta]);
//            $field['configuration'] = $field_normalizer_instance->getConfiguration();

//          $this->configuration['fields'][$delta]['configuration'] = $field_normalizer_instance->getConfiguration();
        }

        $fields[] = FieldConfiguration::createFromConfiguration($this->targetEntityType, $this->targetBundle, $field_configuration);
        $form_state->set('field_configurations', $fields);

        if ($field_normalizer_instance = $this->getStoredFieldNormalizerInstance($delta, $form_state)) {
          // Trigger has the clue to parents array.
          $form_parents = $this->getParentsArray($triggering_element['#array_parents']);
          // Configuration add configuration form parent element.
          $form_parents[] = 'configuration';

          if ($subform = &NestedArray::getValue($form, $form_parents)) {
            $subform_state = SubformState::createForSubform($subform, $form, $form_state);
            $field_normalizer_instance->submitConfigurationForm($subform, $subform_state);

            // Store field normalizer configuration.
//            $field = &$form_state->getValue(['normalizer_configuration', 'fields', $delta]);
//            $field['configuration'] = $field_normalizer_instance->getConfiguration();

            $this->configuration['fields'][$delta]['configuration'] = $field_normalizer_instance->getConfiguration();
          }
        }

        array_pop($selected_field_delta);
        $form_state->set('editable_field_delta', $selected_field_delta);

        break;

      case 'cancel':
        array_pop($selected_field_delta);
        $form_state->set('editable_field_delta', $selected_field_delta);

        break;
    }

    // Update form state.
    $this->updateFormState($form, $form_state);

    // Rebuild the form.
    $form_state->setRebuild();
  }

  protected function updateFormState(array $form, FormStateInterface $form_state) {
    $configuration = &$form_state->getValue(['normalizer_configuration']);
    $configuration = $this->configuration;

    /** @var \Drupal\Core\Entity\EntityFormInterface $form_object */
    $form_object = $form_state->getFormObject();
    $form_object->setEntity($form_object->buildEntity($form, $form_state));
  }

  /**
   * Returns field normalizer instance.
   *
   * @param $normalizer
   * @param array $normalizer_configuration
   *
   * @return \Drupal\elasticsearch_helper_content\ElasticsearchFieldNormalizerInterface
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   */
  public function createFieldNormalizerInstance($normalizer, array $normalizer_configuration) {
    // Explicitly set entity type and bundle. They are unset in field
    // normalizer plugins and are not stored in configuration.
    $normalizer_configuration['entity_type'] = $this->targetEntityType;
    $normalizer_configuration['bundle'] = $this->targetBundle;

    /** @var \Drupal\elasticsearch_helper_content\ElasticsearchFieldNormalizerInterface $result */
    $result = $this->elasticsearchFieldNormalizerManager->createInstance($normalizer, $normalizer_configuration);

    return $result;
  }

  /**
   * Returns field normalizer instance or NULL.
   *
   * @param $field_name
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *
   * @return \Drupal\elasticsearch_helper_content\ElasticsearchFieldNormalizerInterface|null
   */
  protected function getStoredFieldNormalizerInstance($field_name, FormStateInterface $form_state) {
    return $form_state->get(['field_normalizer', $field_name]);
  }

  /**
   * Stores field normalizer instance in form state.
   *
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   * @param \Drupal\elasticsearch_helper_content\ElasticsearchFieldNormalizerInterface $instance
   * @param $delta
   */
  protected function setStoredFieldNormalizerInstance(FormStateInterface $form_state, ElasticsearchFieldNormalizerInterface $instance, $delta) {
    $form_state->set(['field_normalizer', $delta], $instance);
  }


  /**
   * Returns TRUE if field normalizer instance plugin ID matches.
   *
   * @param $plugin_id
   * @param \Drupal\elasticsearch_helper_content\ElasticsearchFieldNormalizerInterface|NULL $instance
   *
   * @return bool
   */
  protected function instanceMatchesPluginId($plugin_id, ElasticsearchFieldNormalizerInterface $instance = NULL) {
    return $instance && $instance->getPluginId() == $plugin_id;
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    foreach ($this->configuration['fields'] as $delta => $field_configuration) {
      $field_normalizer_configuration = [];

      // Gather configuration from field normalizer instances.
      if ($field_normalizer_instance = $this->getStoredFieldNormalizerInstance($delta, $form_state)) {
        // Submit all open normalizer forms.
        if ($subform = &NestedArray::getValue($form, ['fields', $delta, 'settings', 'configuration'])) {
          $subform_state = SubformState::createForSubform($subform, $form, $form_state);
          $field_normalizer_instance->submitConfigurationForm($subform, $subform_state);
        }

        $field_normalizer_configuration = $field_normalizer_instance->getConfiguration();
      }

      $this->configuration['fields'][$delta] = [
        'normalizer' => $field_configuration['normalizer'],
        'normalizer_configuration' => $field_normalizer_configuration,
      ];
    }
  }

  /**
   * Returns a list of entity field labels keyed by entity field name.
   *
   * @param $entity_type_id
   * @param $bundle
   *
   * @return \Drupal\Core\StringTranslation\TranslatableMarkup[]|string[]
   */
  protected function getEntityFieldOptions($entity_type_id, $bundle) {
    $fields_definitions = $this->entityFieldManager->getFieldDefinitions($entity_type_id, $bundle);

    return array_map(function (FieldDefinitionInterface $definition) {
      return sprintf('%s (%s)', $definition->getLabel(), $definition->getName());
    }, $fields_definitions);
  }

  /**
   * Returns entity field instance.
   *
   * @param $entity_type_id
   * @param $bundle
   * @param $field_name
   *
   * @return \Drupal\Core\Field\FieldDefinitionInterface|null
   */
  protected function getEntityField($entity_type_id, $bundle, $field_name) {
    $fields_definitions = $this->entityFieldManager->getFieldDefinitions($entity_type_id, $bundle);

    return isset($fields_definitions[$field_name]) ? $fields_definitions[$field_name] : NULL;
  }

  /**
   * Returns entity field key.
   *
   * For example, returns "label" for "title" field on "node" entity type.
   *
   * @param $entity_type_id
   * @param $field_name
   *
   * @return string|null
   */
  public function getEntityFieldKey($entity_type_id, $field_name) {
    try {
      $entity_type_instance = $this->entityTypeManager->getDefinition($entity_type_id);
      $flipped_entity_keys = array_flip($entity_type_instance->getKeys());

      return isset($flipped_entity_keys[$field_name]) ? $flipped_entity_keys[$field_name] : NULL;
    }
    catch (\Exception $e) {
    }

    return NULL;
  }

}
