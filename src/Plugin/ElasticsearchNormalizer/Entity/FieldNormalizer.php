<?php

namespace Drupal\elasticsearch_helper_content\Plugin\ElasticsearchNormalizer\Entity;

use Drupal\Component\Utility\Html;
use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Form\SubformState;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * The "field" Elasticsearch entity normalizer plugin class.
 *
 * @ElasticsearchEntityNormalizer(
 *   id = "field",
 *   label = @Translation("Content entity fields")
 * )
 */
class FieldNormalizer extends EntityNormalizerBase {

  use StringTranslationTrait;

  /**
   * The entity field manager instance.
   *
   * @var \Drupal\Core\Entity\EntityFieldManagerInterface
   */
  protected $entityFieldManager;

  /**
   * ElasticsearchEntityFieldNormalizer constructor.
   *
   * @param array $configuration
   *   The plugin configuration array.
   * @param string $plugin_id
   *   The plugin ID.
   * @param array $plugin_definition
   *   The plugin definition.
   * @param \Drupal\Core\Entity\EntityFieldManagerInterface $entity_field_manager
   *   The entity field manager instance.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, EntityFieldManagerInterface $entity_field_manager) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);

    $this->entityFieldManager = $entity_field_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_field.manager'),
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
   * {@inheritdoc}
   */
  public function normalize($entity, array $context = []) {
    $data = parent::normalize($entity, $context);

    foreach ($this->configuration['fields'] as $field_configuration_raw) {
      try {
        // Create a field configuration instance.
        $field_configuration = FieldConfiguration::createFromConfiguration($this->getTargetEntityType(), $this->getTargetBundle(), $field_configuration_raw);

        // Check for field validity.
        if ($field_configuration->isValidField()) {
          $field_name = $field_configuration->getFieldName();
          $entity_field_name = $field_configuration->getEntityFieldName();

          // Prepare field item list instance.
          $field = $entity->hasField($entity_field_name) ? $entity->get($entity_field_name) : NULL;

          // Get field output.
          $data[$field_name] = $field_configuration->createNormalizerInstance()->normalize($entity, $field, $context);
        }
      }
      catch (\Exception $e) {
        watchdog_exception('elasticsearch_helper_content', $e);
      }
    }

    return $data;
  }

  /**
   * {@inheritdoc}
   */
  public function getMappingDefinition() {
    $properties = [];
    $mapping_definition = parent::getDefaultMappingDefinition();

    foreach ($this->configuration['fields'] as $field_configuration_raw) {
      try {
        // Create a field configuration instance.
        $field_configuration = FieldConfiguration::createFromConfiguration($this->getTargetEntityType(), $this->getTargetBundle(), $field_configuration_raw);

        // Check for field validity.
        if ($field_configuration->isValidField()) {
          $field_name = $field_configuration->getFieldName();
          // Get field definition.
          $field_definition = $field_configuration->createNormalizerInstance()->getFieldDefinition();

          // Metadata is available in Elasticsearch Helper since 8.1.
          if (method_exists($field_definition, 'setMetadata')) {
            // Set label in the metadata.
            $field_definition->setMetadata('label', $field_configuration->getLabel());
          }

          // Add field definition to the property.
          $properties[$field_name] = $field_definition;
        }
      }
      catch (\Exception $e) {
        watchdog_exception('elasticsearch_helper_content', $e);
      }
    }

    return $mapping_definition->addProperties($properties);
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $entity_type_id = $this->getTargetEntityType();
    $bundle = $this->getTargetBundle();

    if (!isset($entity_type_id, $bundle)) {
      return [];
    }

    // Temporary set configuration into the form state storage.
    $this->setTemporaryConfiguration($this->getConfiguration(), $form_state);

    $form_id_string = 'elasticsearch-entity-field-normalizer-form';
    $form_id = Html::getId($form_id_string);
    $fields_table_id = Html::getId($form_id_string . '-table');

    $form += [
      '#type' => 'container',
      '#id' => $form_id,
      'fields' => [
        '#id' => $fields_table_id,
        '#type' => 'table',
        '#title' => $this->t('Title'),
        '#header' => [
          $this->t('Label'),
          $this->t('Field name'),
          $this->t('Entity field name'),
          $this->t('Type'),
          $this->t('Normalizer'),
          $this->t('Settings'),
          $this->t('Remove'),
        ],
        '#empty' => $this->t('There are no fields added.'),
        '#default_value' => [],
        '#tree' => TRUE,
        '#parents' => ['normalizer_configuration', 'fields'],
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

    // Get ajax-opened form opened deltas.
    $configuration_opened_delta = $form_state->get('normalizer_configuration_opened_delta') ?? NULL;
    $remove_opened_delta = $form_state->get('field_remove_opened_delta') ?? NULL;

    // Loop over fields.
    $configuration = $this->getTemporaryConfiguration($form_state);

    /** @var \Drupal\elasticsearch_helper_content\Plugin\ElasticsearchNormalizer\Entity\FieldConfiguration $field_configuration */
    foreach ($configuration['fields'] as $delta => $field_configuration) {
      $field_row = &$form['fields'][$delta];

      $field_row['label'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Label'),
        '#size' => 20,
        '#required' => TRUE,
        '#default_value' => $field_configuration->getLabel(),
        '#disabled' => $field_configuration->isSystemField(),
      ];

      $field_row['field_name'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Field name'),
        '#size' => 20,
        '#required' => TRUE,
        '#default_value' => $field_configuration->getFieldName(),
        '#disabled' => $field_configuration->isSystemField(),
        '#element_validate' => [[$this, 'elementValidateFieldName']],
        '#delta' => $delta,
      ];

      $field_row['entity_field_name'] = [
        '#markup' => $field_configuration->getEntityFieldName(),
      ];

      $field_row['field_type'] = [
        '#markup' => $field_configuration->getType(),
      ];

      // Get field normalizer definitions.
      $field_normalizer_definitions = $field_configuration->getAvailableFieldNormalizerDefinitions();

      // Do not show the normalizer dropdown if the normalizer configuration
      // of the field removal confirmation form is opened.
      if (!in_array($delta, [$configuration_opened_delta, $remove_opened_delta], TRUE)) {
        $field_row['normalizer'] = [
          '#type' => 'select',
          '#options' => array_map(function ($plugin) {
            return $plugin['label'];
          }, $field_normalizer_definitions),
          '#default_value' => $field_configuration->getNormalizer(),
          '#disabled' => $field_configuration->isSystemField(),
          '#access' => $field_configuration->isValidField(),
          '#ajax' => $ajax_attribute,
          '#op' => 'select_normalizer',
          '#required' => TRUE,
          '#submit' => [[$this, 'multistepSubmit']],
          '#parents' => [
            'normalizer_configuration',
            'fields',
            $delta,
            'normalizer',
          ],
        ];
      }

      // Do not display the fields if the field removal confirmation form
      // is opened.
      if (!in_array($delta, [$remove_opened_delta], TRUE)) {
        // Get field normalize instance.
        try {
          $field_normalizer_instance = $field_configuration->createNormalizerInstance();

          // Prepare the subform state.
          $configuration_form = [];
          $subform_state = SubformState::createForSubform($configuration_form, $form, $form_state);
          $configuration_form = $field_normalizer_instance->buildConfigurationForm([], $subform_state);

          if ($configuration_form) {
            if ($configuration_opened_delta === $delta) {
              // Define configuration form parents.
              $configuration_parents = [
                'normalizer_configuration',
                'fields',
                $delta,
                'configuration',
              ];

              $field_row['settings'] = [
                '#type' => 'container',
                '#wrapper_attributes' => ['colspan' => 2],
                'configuration' => $configuration_form + [
                  '#parents' => $configuration_parents,
                ],
                'actions' => [
                  '#type' => 'actions',
                  'save_settings' => [
                    '#type' => 'submit',
                    '#value' => $this->t('Update'),
                    '#name' => sprintf('normalizer_configuration_%d_update', $delta),
                    '#op' => 'normalizer_configuration_update',
                    '#submit' => [[$this, 'multistepSubmit']],
                    '#delta' => $delta,
                    '#ajax' => $ajax_attribute,
                    '#limit_validation_errors' => [$configuration_parents],
                  ],
                  'cancel_settings' => [
                    '#type' => 'submit',
                    '#value' => $this->t('Cancel'),
                    '#name' => sprintf('normalizer_configuration_%d_cancel', $delta),
                    '#op' => 'normalizer_configuration_cancel',
                    '#submit' => [[$this, 'multistepSubmit']],
                    '#delta' => $delta,
                    '#ajax' => $ajax_attribute,
                    '#limit_validation_errors' => [],
                  ],
                ],
              ];
            }
            else {
              $field_row['settings'] = [
                'button' => [
                  '#type' => 'image_button',
                  '#src' => 'core/misc/icons/787878/cog.svg',
                  '#attributes' => ['alt' => $this->t('Configure')],
                  '#name' => sprintf('normalizer_configuration_%d_edit', $delta),
                  '#disabled' => $field_configuration->isSystemField(),
                  '#return_value' => $this->t('Configure'),
                  '#op' => 'normalizer_configuration_edit',
                  '#submit' => [[$this, 'multistepSubmit']],
                  '#limit_validation_errors' => [],
                  '#delta' => $delta,
                  '#ajax' => $ajax_attribute,
                ],
                'summary' => [
                  '#type' => 'inline_template',
                  '#template' => '<div><small>{{ summary|safe_join("<br />") }}</small></div>',
                  '#context' => [
                    'summary' => $field_normalizer_instance->configurationSummary(),
                  ],
                ],
              ];
            }
          }
          else {
            $field_row['settings'] = [];
          }
        }
        catch (\Exception $e) {
          $field_row['settings'] = [];
          watchdog_exception('elasticsearch_helper_content', $e);
        }
      }

      if ($remove_opened_delta === $delta) {
        $field_row['remove'] = [
          '#type' => 'container',
          '#wrapper_attributes' => ['colspan' => 3],
          'configuration' => [
            '#markup' => $this->t('Are you sure you want to remove the "@field_name" field?', [
              '@field_name' => $field_configuration->getFieldName(),
            ]),
          ],
          'actions' => [
            '#type' => 'actions',
            'confirm' => [
              '#type' => 'submit',
              '#value' => $this->t('Remove'),
              '#name' => sprintf('field_remove_%d_remove', $delta),
              '#op' => 'field_remove_remove',
              '#submit' => [[$this, 'multistepSubmit']],
              '#delta' => $delta,
              '#ajax' => $ajax_attribute,
              '#limit_validation_errors' => [],
            ],
            'cancel' => [
              '#type' => 'submit',
              '#value' => $this->t('Cancel'),
              '#name' => sprintf('field_remove_%d_cancel', $delta),
              '#op' => 'field_remove_cancel',
              '#submit' => [[$this, 'multistepSubmit']],
              '#delta' => $delta,
              '#ajax' => $ajax_attribute,
              '#limit_validation_errors' => [],
            ],
          ],
        ];
      }
      else {
        $field_row['remove'] = [
          '#type' => 'image_button',
          '#src' => 'core/misc/icons/787878/ex.svg',
          '#attributes' => ['alt' => $this->t('Remove')],
          '#name' => sprintf('field_remove_%d_open', $delta),
          '#access' => !$field_configuration->isSystemField(),
          '#return_value' => $this->t('Remove'),
          '#op' => 'field_remove_open',
          '#submit' => [[$this, 'multistepSubmit']],
          '#limit_validation_errors' => [],
          '#delta' => $delta,
          '#ajax' => $ajax_attribute,
        ];
      }
    }

    $form['add_field'] = [
      '#type' => 'details',
      '#title' => t('Add field'),
      '#parents' => ['add_field'],
      '#open' => TRUE,
      'field_name' => [
        '#type' => 'select',
        '#options' => $this->getAddFieldOptions($entity_type_id, $bundle),
        '#parents' => [
          'normalizer_configuration',
          'add_field',
          'field_name',
        ],
      ],
      'actions' => [
        '#type' => 'actions',
        'add_button' => [
          '#type' => 'submit',
          '#value' => $this->t('Add field'),
          '#op' => 'add_field',
          '#submit' => [[$this, 'multistepSubmit']],
          '#limit_validation_errors' => [],
          '#ajax' => $ajax_attribute,
        ],
      ],
    ];

    return $form;
  }

  /**
   * Sets configuration in the temporary storage.
   *
   * @param array $configuration
   *   The configuration array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state instance.
   */
  protected function setTemporaryConfiguration(array $configuration, FormStateInterface $form_state) {
    $is_rebuilding = $form_state->isRebuilding();

    // If form is rebuilding, get the values from the form state storage.
    if ($is_rebuilding) {
      $configuration = $this->getTemporaryConfiguration($form_state);
      // Get form state values to monitor the changes.
      $values = $form_state->getCompleteFormState()->getValue('normalizer_configuration');
    }

    // Set default fields.
    if (!$form_state->get('default_fields_set')) {
      $configuration['fields'] = array_merge(array_values($this->getDefaultFields()), array_values($configuration['fields']));
      $form_state->set('default_fields_set', TRUE);
    }

    foreach ($configuration['fields'] as $delta => $field_configuration) {
      // Replace the scalar configuration with a FieldConfiguration object
      // instance.
      if (is_array($field_configuration)) {
        $configuration['fields'][$delta] = FieldConfiguration::createFromConfiguration($this->getTargetEntityType(), $this->getTargetBundle(), $field_configuration);
      }

      // If there are changes in the normalizer values, change the
      // configuration accordingly.
      if (isset($values['fields'][$delta]['normalizer'])) {
        $current_normalizer = $values['fields'][$delta]['normalizer'];

        if ($configuration['fields'][$delta]->getNormalizer() != $current_normalizer) {
          $configuration['fields'][$delta]->setNormalizer($current_normalizer);
          $configuration['fields'][$delta]->setNormalizerConfiguration([]);
        }
      }
    }

    $form_state->set('configuration', $configuration);
  }

  /**
   * Returns the configuration from the temporary storage.
   *
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state instance.
   *
   * @return array
   *   The configuration array.
   */
  protected function &getTemporaryConfiguration(FormStateInterface $form_state) {
    $configuration = &$form_state->get('configuration');

    // Add the fields element if empty.
    if (!isset($configuration['fields'])) {
      $configuration['fields'] = [];
    }

    return $configuration;
  }

  /**
   * {@inheritdoc}
   */
  public function validateConfigurationForm(array &$form, FormStateInterface $form_state) {
    parent::validateConfigurationForm($form, $form_state);

    $configuration = $this->getTemporaryConfiguration($form_state);
    /** @var \Drupal\elasticsearch_helper_content\Plugin\ElasticsearchNormalizer\Entity\FieldConfiguration[] $fields */
    $fields = $configuration['fields'];

    // Execute validation handler on the field normalizer instance.
    foreach ($fields as $delta => $field_configuration) {
      // Do not throw exceptions in validation stage.
      try {
        // Get field normalizer configuration.
        $field_normalizer_instance = $field_configuration->createNormalizerInstance();
        $configuration_parents = ['fields', $delta, 'settings', 'configuration'];

        if ($subform = &NestedArray::getValue($form, $configuration_parents)) {
          $subform_state = SubformState::createForSubform($subform, $form, $form_state);
          $field_normalizer_instance->validateConfigurationForm($subform, $subform_state);
        }
      }
      catch (\Exception $e) {
        watchdog_exception('elasticsearch_helper_content', $e);
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    $result = [];
    $values = $form_state->getValue('fields');

    $configuration = $this->getTemporaryConfiguration($form_state);
    /** @var \Drupal\elasticsearch_helper_content\Plugin\ElasticsearchNormalizer\Entity\FieldConfiguration[] $fields */
    $fields = $configuration['fields'];

    foreach ($fields as $delta => $field_configuration) {
      // Do not store system fields.
      if ($field_configuration->isSystemField()) {
        continue;
      }

      $field_normalizer_instance = $field_configuration->createNormalizerInstance();
      $configuration_parents = ['fields', $delta, 'settings', 'configuration'];

      // Submit all open normalizer forms.
      if ($subform = &NestedArray::getValue($form, $configuration_parents)) {
        $subform_state = SubformState::createForSubform($subform, $form, $form_state);
        $field_normalizer_instance->submitConfigurationForm($subform, $subform_state);
        $field_configuration->setNormalizerConfiguration($field_normalizer_instance->getConfiguration());
      }

      // Store submitted values.
      $field_configuration->setFieldName($values[$delta]['field_name']);
      $field_configuration->setLabel($values[$delta]['label']);

      // Store field configuration.
      $result[] = $field_configuration->getConfiguration();
    }

    $this->configuration['fields'] = $result;
  }

  /**
   * Ajax submit handler.
   *
   * @param array $form
   *   The form render array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state instance.
   *
   * @return array
   *   The form render array.
   */
  public function submitAjax($form, FormStateInterface $form_state) {
    $form_state->setRebuild();

    $form_parents = ['normalizer_configuration', 'configuration'];
    $return_form = NestedArray::getValue($form, $form_parents);

    return $return_form;
  }

  /**
   * Form element change submit handler.
   *
   * @param array $form
   *   The form render array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state instance.
   */
  public function multistepSubmit($form, FormStateInterface $form_state) {
    $triggering_element = $form_state->getTriggeringElement();
    $op = $triggering_element['#op'];
    $delta = $triggering_element['#delta'] ?? NULL;

    $target_entity_type = $this->getTargetEntityType();
    $target_bundle = $this->getTargetBundle();

    switch ($op) {
      case 'add_field':
        $new_field_configuration = [];

        // Get the new field values. Given that "Add field" button has
        // #limit_validation_errors set to an empty array, the values are
        // available only from the user input.
        $new_field_parents = ['normalizer_configuration', 'add_field'];
        $new_field_values = NestedArray::getValue($form_state->getUserInput(), $new_field_parents);

        $new_field_name_option = $new_field_values['field_name'] ?? '::';
        [$field_group, $field_name, $field_type] = explode(':', $new_field_name_option);

        // Create the field configuration for an entity field.
        if ($field_group == 'entity_field') {
          $entity_field_name = $field_name;

          // Use entity key for field name.
          if ($entity_key = FieldConfiguration::translateFieldNameToEntityKey($target_entity_type, $field_name)) {
            $field_name = $entity_key;
          }

          // Prepare new field configuration.
          $new_field_configuration['entity_field_name'] = $entity_field_name;
          $new_field_configuration['field_name'] = $field_name;

          // Create the field configuration instance.
          $field_configuration = FieldConfiguration::createFromConfiguration($target_entity_type, $target_bundle, $new_field_configuration);

          // Set label to entity label.
          $entity_field_label = $field_configuration->getEntityFieldLabel();
          $field_configuration->setLabel($entity_field_label);
        }
        elseif ($field_type == FieldTypeInterface::ENTITY) {
          // Create the field configuration instance for an entity renderer.
          $field_configuration = FieldConfiguration::createFromConfiguration($target_entity_type, $target_bundle, [
            'type' => $field_type,
          ]);
        }
        elseif ($field_type == FieldTypeInterface::CUSTOM) {
          // Create the field configuration instance for a custom field
          // normalizer.
          $field_configuration = FieldConfiguration::createFromConfiguration($target_entity_type, $target_bundle, []);
        }

        // Store the field in the temporary configuration.
        $configuration = &$this->getTemporaryConfiguration($form_state);
        $configuration['fields'][] = $field_configuration;

        break;

      case 'normalizer_configuration_edit':
        $form_state->set('normalizer_configuration_opened_delta', $delta);

        break;

      case 'normalizer_configuration_update':
        // Store the field in the temporary configuration.
        $configuration = &$this->getTemporaryConfiguration($form_state);
        /** @var \Drupal\elasticsearch_helper_content\Plugin\ElasticsearchNormalizer\Entity\FieldConfiguration $field_configuration */
        $field_configuration = $configuration['fields'][$delta] ?? NULL;

        if ($field_configuration) {
          $field_normalizer_instance = $field_configuration->createNormalizerInstance();
          $configuration_parents = [
            'normalizer_configuration',
            'configuration',
            'fields',
            $delta,
            'settings',
            'configuration',
          ];

          // Submit all open normalizer forms.
          if ($subform = &NestedArray::getValue($form, $configuration_parents)) {
            $subform_state = SubformState::createForSubform($subform, $form, $form_state);
            $field_normalizer_instance->submitConfigurationForm($subform, $subform_state);
            $field_configuration->setNormalizerConfiguration($field_normalizer_instance->getConfiguration());
          }
        }

        $form_state->set('normalizer_configuration_opened_delta', NULL);

        break;

      case 'normalizer_configuration_cancel':
        $form_state->set('normalizer_configuration_opened_delta', NULL);

        break;

      case 'field_remove_open':
        $form_state->set('field_remove_opened_delta', $delta);

        break;

      case 'field_remove_remove':
        // Update field normalizer configuration.
        $configuration = &$this->getTemporaryConfiguration($form_state);
        unset($configuration['fields'][$delta]);

        $form_state->set('field_remove_opened_delta', NULL);

        break;

      case 'field_remove_cancel':
        $form_state->set('field_remove_opened_delta', NULL);

        break;
    }

    // Rebuild the form.
    $form_state->setRebuild();
  }

  /**
   * Validates field name element.
   *
   * @param array $element
   *   The field name element.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state instance.
   *
   * @return array
   *   The element render array.
   */
  public static function elementValidateFieldName(array $element, FormStateInterface $form_state) {
    $delta = $element['#delta'] ?? NULL;
    $value = $element['#value'];

    // Get submitted configuration.
    $configuration = $form_state->getValue('normalizer_configuration');
    $fields = $configuration['fields'] ?? [];

    // Check field name value.
    if ($fields) {
      ksort($fields);

      // Gather all field names prior to given element field name.
      $field_names = [];

      foreach ($fields as $field_delta => $field_configuration) {
        if ($field_delta < $delta) {
          $field_names[] = $field_configuration['field_name'];
        }
        else {
          break;
        }
      }

      // Duplicate field names are not allowed.
      if (in_array($value, $field_names)) {
        $form_state->setError($element, t('Field name %name is already in use.', ['%name' => $value]));
      }
    }

    // Do not allow special characters in the field names.
    $forbidden_characters = [' ', '+', '-', '.'];

    foreach ($forbidden_characters as $character) {
      if (stripos($value, $character)) {
        $form_state->setError($element, t('Character "%character" is not allowed in the field name.', ['%character' => $character]));
        break;
      }
    }

    return $element;
  }

  /**
   * Returns a list of options for the "add field" dropdown field.
   *
   * @param string $entity_type_id
   *   The entity type ID.
   * @param string $bundle
   *   The bundle name.
   *
   * @return array
   *   A list of options for the "add field" dropdown field.
   */
  public function getAddFieldOptions($entity_type_id, $bundle) {
    return [
      (string) $this->t('Entity fields') => $this->getEntityFieldOptions($entity_type_id, $bundle),
      (string) $this->t('Other fields') => $this->getCustomFieldOptions(),
    ];
  }

  /**
   * Returns a list of entity field labels keyed by entity field name.
   *
   * @param string $entity_type_id
   *   The entity type ID.
   * @param string $bundle
   *   The bundle name.
   *
   * @return \Drupal\Core\StringTranslation\TranslatableMarkup[]|string[]
   *   The list of fields for given bundle.
   */
  protected function getEntityFieldOptions($entity_type_id, $bundle) {
    $result = [];

    $field_definitions = $this->entityFieldManager->getFieldDefinitions($entity_type_id, $bundle);

    foreach ($field_definitions as $field_name => $definition) {
      // The key is defined by three values:
      // - field group
      // - field name (not needed for custom fields)
      // - field type (not needed for entity fields)
      $key = sprintf('entity_field:%s:', $field_name);
      $label = sprintf('%s (%s)', $definition->getLabel(), $definition->getName());
      $result[$key] = $label;
    }

    return $result;
  }

  /**
   * Returns a list of custom field options.
   *
   * @return array
   *   A list of custom field options.
   */
  protected function getCustomFieldOptions() {
    return [
      // The key is defined by three values:
      // - field group
      // - field name (not needed for custom fields)
      // - field type (not needed for entity fields)
      'custom::entity' => $this->t('Rendered entity'),
      'custom::any' => $this->t('Custom field'),
    ];
  }

  /**
   * Returns a list of default field definitions which are added automatically.
   *
   * The rendering of said fields should be handled by the normalize() method.
   *
   * NOTE: Always use entity key of the field if possible. This allows all
   * indices to have a consistent set of base fields which are common to
   * most entity types.
   *
   * Example:
   * - use "bundle" field instead of "type" field in "node" entity type or
   * "vid" field in "taxonomy_term" entity type.
   * - use "id" field instead of "nid" field in "node" entity type or
   * "nid" field in "taxonomy_term" entity type.
   *
   * Refer to the entity type defining class which should have a list of
   * entity keys. For example, see \Drupal\node\Entity\Node.
   *
   * @return array[]
   *   The list of default field definitions.
   *
   * @see \Drupal\elasticsearch_helper_content\Plugin\ElasticsearchNormalizer\Entity\FieldNormalizer::getDefaultFields()
   * @see \Drupal\elasticsearch_helper_content\Plugin\ElasticsearchNormalizer\Entity\EntityNormalizerBase::getDefaultMappingDefinition()
   */
  protected function getDefaultFieldDefinitions() {
    return [
      'entity_type' => [
        // Defines the field label.
        'label' => $this->t('Entity type'),
        // Defines the type of the field.
        'type' => 'string',
        // Defines the normalizer.
        'normalizer' => 'keyword',
        // Defines if field is an entity base field or an entity key.
        'entity_field' => FALSE,
      ],
      'bundle' => [
        'label' => $this->t('Bundle'),
        'normalizer' => 'keyword',
        'type' => 'string',
        'entity_field' => TRUE,
      ],
      'id' => [
        'label' => $this->t('ID'),
        'normalizer' => 'keyword',
        'type' => 'string',
        'entity_field' => TRUE,
      ],
      'langcode' => [
        'label' => $this->t('Language code'),
        'normalizer' => 'keyword',
        'type' => 'string',
        'entity_field' => TRUE,
      ],
    ];
  }

  /**
   * Returns a list of default fields that are added to the index.
   *
   * @return \Drupal\elasticsearch_helper_content\Plugin\ElasticsearchNormalizer\Entity\FieldConfiguration[]
   *   A list of default fields.
   *
   * @see static::getDefaultFieldDefinitions()
   * @see \Drupal\elasticsearch_helper_content\Plugin\ElasticsearchNormalizer\Entity\EntityNormalizerBase::getDefaultMappingDefinition()
   */
  protected function getDefaultFields() {
    $result = [];

    $entity_type = $this->getTargetEntityType();
    $bundle = $this->getTargetBundle();

    foreach ($this->getDefaultFieldDefinitions() as $field_name => $default_field) {
      $field_configuration_raw = [
        'field_name' => $field_name,
        'label' => $default_field['label'],
      ];

      // Set the type.
      if (!empty($default_field['type'])) {
        $field_configuration_raw['type'] = $default_field['type'];
      }

      // Set the normalizer.
      if (isset($default_field['normalizer'])) {
        $field_configuration_raw['normalizer'] = $default_field['normalizer'];
      }

      // Set the entity key to entity fields.
      if (!empty($default_field['entity_field'])) {
        if ($entity_field_name = FieldConfiguration::translateEntityKeyToFieldName($entity_type, $field_name)) {
          $field_configuration_raw['entity_field_name'] = $entity_field_name;
        }
      }

      $field_configuration = FieldConfiguration::createFromConfiguration($entity_type, $bundle, $field_configuration_raw, [
        'system_field' => TRUE,
      ]);

      $result[] = $field_configuration;
    }

    return $result;
  }

}
