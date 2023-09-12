<?php

namespace Drupal\elasticsearch_helper_content\Plugin\ElasticsearchNormalizer\Entity;

use Drupal\Component\Utility\Html;
use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Form\SubformState;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\elasticsearch_helper_content\ElasticsearchEntityNormalizerBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * The "field" Elasticsearch entity normalizer plugin class.
 *
 * @ElasticsearchEntityNormalizer(
 *   id = "field",
 *   label = @Translation("Content entity field"),
 *   weight = 0
 * )
 */
class FieldNormalizer extends ElasticsearchEntityNormalizerBase {

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

    try {
      foreach ($this->configuration['fields'] as $delta => $field_configuration_raw) {
        $field_configuration = FieldConfiguration::createFromConfiguration($this->targetEntityType, $this->targetBundle, $field_configuration_raw);

        $field_name = $field_configuration->getFieldName();
        $entity_field_name = $field_configuration->getEntityFieldName();

        if ($entity->hasField($entity_field_name)) {
          $field = $entity->get($entity_field_name);
        }

        $data[$field_name] = $field_configuration->createNormalizerInstance()->normalize($entity, $field, $context);
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

    foreach ($this->configuration['fields'] as $delta => $field_configuration_raw) {
      $field_configuration = FieldConfiguration::createFromConfiguration($this->targetEntityType, $this->targetBundle, $field_configuration_raw);

      $field_name = $field_configuration->getFieldName();
      $properties[$field_name] = $field_configuration->createNormalizerInstance()->getFieldDefinition();
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
      ];

      $field_row['field_name'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Field name'),
        '#size' => 20,
        '#required' => TRUE,
        '#default_value' => $field_configuration->getFieldName(),
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

      $field_row['normalizer'] = [
        '#type' => 'select',
        '#options' => array_map(function ($plugin) {
          return $plugin['label'];
        }, $field_normalizer_definitions),
        '#default_value' => $field_configuration->getNormalizer(),
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

      // Get field normalize instance.
      $field_normalizer_instance = $field_configuration->createNormalizerInstance();

      // Prepare the subform state.
      $configuration_form = [];
      $subform_state = SubformState::createForSubform($configuration_form, $form, $form_state);
      $configuration_form = $field_normalizer_instance->buildConfigurationForm([], $subform_state);

      if ($configuration_form) {
        $opened_delta = $form_state->get('normalizer_configuration_opened_delta') ?? NULL;

        if ($opened_delta === $delta) {
          $field_row['settings'] = [
            '#type' => 'container',
            'configuration' => $configuration_form + [
              '#parents' => [
                'normalizer_configuration',
                'fields',
                $delta,
                'configuration',
              ],
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
                '#limit_validation_errors' => [
                  ['normalizer_configuration', 'fields'],
                ],
              ],
              'cancel_settings' => [
                '#type' => 'submit',
                '#value' => $this->t('Cancel'),
                '#name' => sprintf('normalizer_configuration_%d_cancel', $delta),
                '#op' => 'normalizer_configuration_cancel',
                '#submit' => [[$this, 'multistepSubmit']],
                '#delta' => $delta,
                '#ajax' => $ajax_attribute,
                '#limit_validation_errors' => [
                  ['normalizer_configuration', 'fields'],
                ],
              ],
            ],
          ];
        }
        else {
          $field_row['settings'] = [
            '#type' => 'image_button',
            '#src' => 'core/misc/icons/787878/cog.svg',
            '#attributes' => ['alt' => $this->t('Configure')],
            '#name' => sprintf('normalizer_configuration_%d_edit', $delta),
            '#return_value' => $this->t('Configure'),
            '#op' => 'normalizer_configuration_edit',
            '#submit' => [[$this, 'multistepSubmit']],
            '#limit_validation_errors' => [
              ['normalizer_configuration', 'fields'],
            ],
            '#delta' => $delta,
            '#ajax' => $ajax_attribute,
          ];
        }
      }
      else {
        $field_row['settings'] = [];
      }

      $opened_delta = $form_state->get('field_remove_opened_delta') ?? NULL;

      if ($opened_delta === $delta) {
        $field_row['remove'] = [
          '#type' => 'container',
          'configuration' => [
            '#markup' => $this->t('Are you sure you want to remove this field?'),
          ],
          'actions' => [
            '#type' => 'actions',
            'confirm' => [
              '#type' => 'submit',
              '#value' => $this->t('Remove'),
              '#name' => sprintf('field_remove_%d_confirm', $delta),
              '#op' => 'field_remove_confirm',
              '#submit' => [[$this, 'multistepSubmit']],
              '#delta' => $delta,
              '#ajax' => $ajax_attribute,
              '#limit_validation_errors' => [
                ['normalizer_configuration', 'fields'],
              ],
            ],
            'cancel' => [
              '#type' => 'submit',
              '#value' => $this->t('Cancel'),
              '#name' => sprintf('field_remove_%d_cancel', $delta),
              '#op' => 'field_remove_cancel',
              '#submit' => [[$this, 'multistepSubmit']],
              '#delta' => $delta,
              '#ajax' => $ajax_attribute,
              '#limit_validation_errors' => [
                ['normalizer_configuration', 'fields'],
              ],
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
          '#return_value' => $this->t('Remove'),
          '#op' => 'field_remove_open',
          '#submit' => [[$this, 'multistepSubmit']],
          '#limit_validation_errors' => [
            ['normalizer_configuration', 'fields'],
          ],
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
      'entity_field_name' => [
        '#type' => 'select',
        '#options' => $this->getEntityFieldOptions($entity_type_id, $bundle) + [
          '_custom' => $this->t('Custom field'),
        ],
        '#parents' => [
          'normalizer_configuration',
          'add_field',
          'entity_field_name',
        ],
      ],
      'actions' => [
        '#type' => 'actions',
        'add_button' => [
          '#type' => 'submit',
          '#value' => $this->t('Add field'),
          '#op' => 'add_field',
          '#submit' => [[$this, 'multistepSubmit']],
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

    $fields = $configuration['fields'];

    foreach ($fields as $delta => $field_configuration) {
      // Replace the scalar configuration with a FieldConfiguration object
      // instance.
      if (is_array($field_configuration)) {
        $configuration['fields'][$delta] = FieldConfiguration::createFromConfiguration($this->targetEntityType, $this->targetBundle, $field_configuration);
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
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    $values = $form_state->getValue('fields');

    $configuration = $this->getTemporaryConfiguration($form_state);
    /** @var \Drupal\elasticsearch_helper_content\Plugin\ElasticsearchNormalizer\Entity\FieldConfiguration[] $fields */
    $fields = $configuration['fields'];

    foreach ($fields as $delta => $field_configuration) {
      $field_normalizer_configuration = [];

      // Get field normalizer configuration.
      if ($field_normalizer_instance = $field_configuration->createNormalizerInstance()) {
        // Submit all open normalizer forms.
        $configuration_parents = ['fields', $delta, 'settings', 'configuration'];

        if ($subform = &NestedArray::getValue($form, $configuration_parents)) {
          $subform_state = SubformState::createForSubform($subform, $form, $form_state);
          $field_normalizer_instance->submitConfigurationForm($subform, $subform_state);

          // Get field normalizer configuration.
          $field_normalizer_configuration = $field_normalizer_instance->getConfiguration();
        }
      }

      // Store submitted values.
      $field_configuration->setFieldName($values[$delta]['field_name']);
      $field_configuration->setLabel($values[$delta]['label']);
      $field_configuration->setNormalizer($values[$delta]['normalizer']);
      $field_configuration->setNormalizerConfiguration($field_normalizer_configuration);

      // Store field configuration.
      $this->configuration['fields'][$delta] = $field_configuration->getConfiguration();
    }
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
    $delta = $triggering_element['#delta'];

    switch ($op) {
      case 'add_field':
        $field_configuration = FieldConfiguration::createFromConfiguration($this->targetEntityType, $this->targetBundle, []);

        // Get new field submitted values.
        $new_field_parents = ['normalizer_configuration', 'add_field'];
        $new_field_values = $form_state->getValue($new_field_parents);

        // Add the entity field name if the field is an entity field.
        if ($new_field_values['entity_field_name'] != '_custom') {
          $entity_field_name = $new_field_values['entity_field_name'];

          $field_configuration->setEntityFieldName($entity_field_name);
          $entity_field_label = $field_configuration->getEntityFieldLabel($entity_field_name);
          $field_configuration->setLabel($entity_field_label);
          $field_configuration->setFieldName($entity_field_name);
        }

        // Store the field in the temporary configuration.
        $configuration = &$this->getTemporaryConfiguration($form_state);
        $configuration['fields'][] = $field_configuration;

        break;

      case 'normalizer_configuration_edit':
        $form_state->set('normalizer_configuration_opened_delta', $delta);

        break;

      case 'normalizer_configuration_update':
        // Get field normalizer configuration.
        $configuration_parents = [
          'normalizer_configuration',
          'fields',
          $delta,
          'configuration',
        ];
        $normalizer_configuration = $form_state->getValue($configuration_parents);

        // Update field normalizer configuration.
        $configuration = &$this->getTemporaryConfiguration($form_state);
        $configuration['fields'][$delta]->setNormalizerConfiguration($normalizer_configuration);

        $form_state->set('normalizer_configuration_opened_delta', NULL);

        break;

      case 'normalizer_configuration_cancel':
        $form_state->set('normalizer_configuration_opened_delta', NULL);

        break;

      case 'field_remove_open':
        $form_state->set('field_remove_opened_delta', $delta);

        break;

      case 'field_remove_confirm':
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

    $configuration = $form_state->get('configuration') ?? [];
    $fields = $configuration['fields'] ?? [];

    // Gather all field names prior to given element field name.
    $field_names = [];

    foreach ($fields as $field_delta => $field_configuration) {
      if ($field_delta < $delta) {
        $field_names[] = $field_configuration->getFieldName();
      }
    }

    // Duplicate field names are not allowed.
    if (in_array($value, $field_names)) {
      $form_state->setError($element, t('Field name %name is already in use.', ['%name' => $value]));
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
    $fields_definitions = $this->entityFieldManager->getFieldDefinitions($entity_type_id, $bundle);

    return array_map(function (FieldDefinitionInterface $definition) {
      return sprintf('%s (%s)', $definition->getLabel(), $definition->getName());
    }, $fields_definitions);
  }

}
