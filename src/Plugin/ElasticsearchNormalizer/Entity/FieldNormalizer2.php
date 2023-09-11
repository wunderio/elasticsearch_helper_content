<?php

namespace Drupal\elasticsearch_helper_content\Plugin\ElasticsearchNormalizer\Entity;

use Drupal\Component\Utility\Html;
use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Form\SubformState;
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

    // Loop over fields.
    $configuration = $this->getTemporaryConfiguration($form_state);
    /** @var \Drupal\elasticsearch_helper_content\Plugin\ElasticsearchNormalizer\Entity\FieldConfiguration[] $fields */
    $fields = $configuration['fields'];

    foreach ($fields as $field_delta => $field_configuration) {
      $selected_field_delta = [$field_delta];
      $form_field_row = &$form['fields'][$field_delta];

      $field_name = $field_configuration->getFieldName();

      // Get field normalizer definitions.
      $field_normalizer_definitions = $field_configuration->getAvailableFieldNormalizerDefinitions();

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
        '#default_value' => $field_configuration->getNormalizer(),
        '#access' => !empty($field_normalizer_definitions),
        '#selected_field_delta' => $selected_field_delta,
        '#ajax' => $ajax_attribute,
        '#op' => 'select_normalizer',
        '#submit' => [[$this, 'multistepSubmit']],
        '#parents' => ['normalizer_configuration', 'fields', $field_delta, 'normalizer'],
      ];
      $form_field_row['settings'] = [];
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
        '#required' => TRUE,
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
        '#required' => TRUE,
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

  protected function setTemporaryConfiguration(array $configuration, FormStateInterface $form_state) {
    if ($form_state->isRebuilding()) {
      $configuration = $this->getTemporaryConfiguration($form_state);
    }

    try {
      $fields = $configuration['fields'];

      foreach ($fields as $delta => $field_configuration) {
        if (is_array($field_configuration)) {
          $configuration['fields'][$delta] = FieldConfiguration::createFromConfiguration($this->targetEntityType, $this->targetBundle, $field_configuration);
        }
      }
    }
    catch (\Exception $e) {
      watchdog_exception('elasticsearch_helper_content', $e);
    }

    $form_state->set('configuration', $configuration);
  }

  protected function getTemporaryConfiguration(FormStateInterface $form_state) {
    $configuration = $form_state->get('configuration') ?? [];

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

        $field_configuration = [];

        // Get add-field submitted values.
        $add_field_values = $form_state->getValue(['normalizer_configuration', 'add_field']);

        if ($add_field_values['entity_field_name'] != '_custom') {
          $field_configuration['entity_field_name'] = $add_field_values['entity_field_name'];
        }

        $field_configuration['label'] = $add_field_values['label'];
        $field_configuration['field_name'] = $add_field_values['field_name'];

        // Store fields in form state to have then included in the entity.
        //        $fields = &$form_state->getValue(['normalizer_configuration', 'fields']);
        //        $fields = $fields ?: [];
        //        $fields[] = $field_configuration;

        $configuration = $this->getTemporaryConfiguration($form_state);
        $configuration['fields'][] = FieldConfiguration::createFromConfiguration($this->targetEntityType, $this->targetBundle, $field_configuration);
        $this->setTemporaryConfiguration($configuration, $form_state);

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
