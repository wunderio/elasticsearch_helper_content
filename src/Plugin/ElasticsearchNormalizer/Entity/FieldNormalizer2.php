<?php

namespace Drupal\elasticsearch_helper_content\Plugin\ElasticsearchNormalizer\Entity;

use Drupal\Component\Utility\Html;
use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Form\SubformState;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\elasticsearch_helper_content\ElasticsearchEntityNormalizerBase;
use Drupal\elasticsearch_helper_content\ElasticsearchFieldNormalizerManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * @ElasticsearchEntityNormalizer(
 *   id = "field2",
 *   label = @Translation("Content entity field 2"),
 *   weight = 0
 * )
 */
class FieldNormalizer2 extends ElasticsearchEntityNormalizerBase {

  /**
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * @var \Drupal\Core\Entity\EntityFieldManagerInterface
   */
  protected $entityFieldManager;

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
  public function __construct(array $configuration, $plugin_id, $plugin_definition, EntityTypeManagerInterface $entity_type_manager, EntityFieldManagerInterface $entity_field_manager, ElasticsearchFieldNormalizerManagerInterface $elasticsearch_field_normalizer_manager) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);

    $this->entityTypeManager = $entity_type_manager;
    $this->entityFieldManager = $entity_field_manager;
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
   * {@inheritdoc}
   */
  public function normalize($entity, array $context = []) {
    $data = parent::normalize($entity, $context);

    return $data;
  }

  /**
   * {@inheritdoc}
   */
  public function getMappingDefinition() {
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
        '#title' => t('Title'),
        '#header' => [t('Label'), t('Field name'), t('Type'), t('Normalizer'), t('Settings')],
        '#empty' => t('There are no fields added.'),
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
    $fields = $configuration['fields'];

    foreach ($fields as $delta => $field_configuration) {
      // $field_configuration = FieldConfiguration::createFromConfiguration($entity_type_id, $bundle, $field_configuration);

      $form_field_row = &$form['fields'][$delta];

      $field_name = $field_configuration->getFieldName();

      // Get field normalizer definitions.
      $field_normalizer_definitions = $field_configuration->getAvailableFieldNormalizerDefinitions();

      $form_field_row['label'] = [
        '#markup' => $field_configuration->getLabel(),
      ];

      $form_field_row['field_name'] = [
        '#markup' => $field_name,
      ];

      $form_field_row['field_type'] = [
        '#markup' => $field_configuration->getType(),
      ];

      $form_field_row['normalizer'] = [
        '#type' => 'select',
        '#options' => array_map(function ($plugin) {
          return $plugin['label'];
        }, $field_normalizer_definitions),
        '#default_value' => $field_configuration->getNormalizer(),
        '#access' => !empty($field_normalizer_definitions),
        '#selected_field_delta' => $delta,
        '#ajax' => $ajax_attribute,
        '#op' => 'select_normalizer',
        '#submit' => [[$this, 'multistepSubmit']],
        '#parents' => ['normalizer_configuration', 'fields', $delta, 'normalizer'],
      ];

      try {
        $field_normalizer_instance = $field_configuration->createNormalizerInstance();

        // Check if normalizer instance is set and if it matches the selected
        // normalizer.
//        if (!$this->instanceMatchesPluginId($field_normalizer, $field_normalizer_instance)) {
//          $field_normalizer_instance = $this->createFieldNormalizerInstance($field_normalizer, $field_configuration['normalizer_configuration']);
//
//          // Store field normalizer instance in form state.
//          $form_state->set(['field_normalizer', $field_name], $field_normalizer_instance);
//        }

        // Prepare the subform state.
        $configuration_form = [];
        $subform_state = SubformState::createForSubform($configuration_form, $form, $form_state);
        $configuration_form = $field_normalizer_instance->buildConfigurationForm([], $subform_state);

        if ($configuration_form) {
          $row_id_edit = $form_state->get('opened_row_id') ?? NULL;

          if ($row_id_edit === $delta) {
            $form_field_row['settings'] = [
              '#type' => 'container',
              'configuration' => $configuration_form + [
                '#parents' => ['normalizer_configuration', 'fields', $delta, 'configuration'],
              ],
              'actions' => [
                '#type' => 'actions',
                'save_settings' => [
                  '#type' => 'submit',
                  '#value' => t('Update'),
                  '#name' => $delta . '_update',
                  '#op' => 'update',
                  '#submit' => [[$this, 'multistepSubmit']],
                  '#row_id' => $delta,
                  '#ajax' => $ajax_attribute,
                  '#limit_validation_errors' => [['normalizer_configuration', 'fields']],
                  // '#parent_offset' => -4,
                ],
                'cancel_settings' => [
                  '#type' => 'submit',
                  '#value' => t('Cancel'),
                  '#name' => $delta . '_cancel',
                  '#op' => 'cancel',
                  '#submit' => [[$this, 'multistepSubmit']],
                  '#row_id' => $delta,
                  '#ajax' => $ajax_attribute,
                  '#limit_validation_errors' => [['normalizer_configuration', 'fields']],
                  // '#parent_offset' => -4,
                ],
              ],
            ];
          }
          else {
            $form_field_row['settings'] = [
              '#type' => 'image_button',
              '#src' => 'core/misc/icons/787878/cog.svg',
              '#attributes' => ['alt' => t('Edit')],
              '#name' => $delta . '_edit',
              '#return_value' => t('Configure'),
              '#op' => 'edit',
              '#submit' => [[$this, 'multistepSubmit']],
              '#limit_validation_errors' => [['normalizer_configuration', 'fields']],
              '#row_id' => $delta,
              '#ajax' => $ajax_attribute,
            ];
          }
        }
        else {
          $form_field_row['settings'] = [];
        }
      }
      catch (\Exception $e) {
        watchdog_exception('elasticsearch_helper_content', $e);
        $form_field_row['settings'] = [];
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
        '#weight' => 5,
        '#element_validate' => [[$this, 'validateNewFieldLabel']],
//        '#required' => TRUE,
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
//          'source' => ['normalizer_configuration', 'configuration', 'add_field', 'label'],
//          'label' => t('Field name'),
//        ],
        '#weight' => 10,
//        '#required' => TRUE,
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
        ],
      ],
    ];

    return $form;
  }

  public static function validateNewFieldLabel(array $element, FormStateInterface $form_state) {
    $triggering_element = $form_state->getTriggeringElement();
    $op = $triggering_element['#op'] ?? NULL;

    if ($op == 'add_field' && $element['#value'] === '') {
      $form_state->setError($element, new TranslatableMarkup('@name field is required.', ['@name' => $element['#title']]));
    }

    return $element;
  }

  protected function setTemporaryConfiguration(array $configuration, FormStateInterface $form_state) {
    $is_rebuilding = $form_state->isRebuilding();

    if ($is_rebuilding) {
      $configuration = $this->getTemporaryConfiguration($form_state);
      $form_state_values = $form_state->getCompleteFormState()->getValue('normalizer_configuration');
    }

    try {
      $fields = $configuration['fields'];

      foreach ($fields as $delta => $field_configuration) {
        if (is_array($field_configuration)) {
          $configuration['fields'][$delta] = FieldConfiguration::createFromConfiguration($this->targetEntityType, $this->targetBundle, $field_configuration);
        }

        if (isset($form_state_values['fields'][$delta]['normalizer'])) {
          $current_normalizer = $form_state_values['fields'][$delta]['normalizer'];

          if ($configuration['fields'][$delta]->getNormalizer() != $current_normalizer) {
            $configuration['fields'][$delta]->setNormalizer($current_normalizer);
            $configuration['fields'][$delta]->setNormalizerConfiguration([]);
          }
        }
      }
    }
    catch (\Exception $e) {
      watchdog_exception('elasticsearch_helper_content', $e);
    }

    $form_state->set('configuration', $configuration);
  }

  protected function &getTemporaryConfiguration(FormStateInterface $form_state) {
    $configuration = &$form_state->get('configuration');

    if (!isset($configuration['fields'])) {
      $configuration['fields'] = [];
    }

    return $configuration;
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    $configuration = $this->getTemporaryConfiguration($form_state);
    /** @var \Drupal\elasticsearch_helper_content\Plugin\ElasticsearchNormalizer\Entity\FieldConfiguration[] $fields */
    $fields = $configuration['fields'];

    foreach ($fields as $delta => $field_configuration) {
      // Get field normalizer configuration.
      if ($field_normalizer_instance = $field_configuration->createNormalizerInstance()) {
        // Submit all open normalizer forms.
        if ($subform = &NestedArray::getValue($form, ['fields', $delta, 'settings', 'configuration'])) {
          $subform_state = SubformState::createForSubform($subform, $form, $form_state);
          $field_normalizer_instance->submitConfigurationForm($subform, $subform_state);
        }

        // Get field normalizer configuration.
        $field_normalizer_configuration = $field_normalizer_instance->getConfiguration();
        // Store field normalizer configuration.
        $field_configuration->setNormalizerConfiguration($field_normalizer_configuration);
      }

      // Store field configuration.
      $this->configuration['fields'][$delta] = $field_configuration->getConfiguration();
    }
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
    $form_state->setRebuild();

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
    $row_id = $triggering_element['#row_id'];

    switch ($op) {
      case 'add_field':
        $field_configuration = [];

        // Get new field submitted values.
        $new_field_values = $form_state->getValue(['normalizer_configuration', 'add_field']);

        if ($new_field_values['entity_field_name'] != '_custom') {
          $field_configuration['entity_field_name'] = $new_field_values['entity_field_name'];
        }

        $field_configuration['label'] = $new_field_values['label'];
        $field_configuration['field_name'] = $new_field_values['field_name'];

        $configuration = &$this->getTemporaryConfiguration($form_state);
        $configuration['fields'][] = FieldConfiguration::createFromConfiguration($this->targetEntityType, $this->targetBundle, $field_configuration);

        break;

      case 'edit':
        $form_state->set('opened_row_id', $row_id);

        break;

      case 'update':
        // Get field normalizer configuration.
        $normalizer_configuration = $form_state->getValue(['normalizer_configuration', 'fields', $row_id, 'configuration']);
        // Update field normalizer configuration.
        $configuration = &$this->getTemporaryConfiguration($form_state);
        $configuration['fields'][$row_id]->setNormalizerConfiguration($normalizer_configuration);

        $form_state->set('opened_row_id', NULL);

        break;

      case 'cancel':
        $form_state->set('opened_row_id', NULL);

        break;
    }

    // Rebuild the form.
    $form_state->setRebuild();
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
