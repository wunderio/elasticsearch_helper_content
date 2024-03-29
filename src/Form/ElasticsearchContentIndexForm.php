<?php

namespace Drupal\elasticsearch_helper_content\Form;

use Drupal\Component\Utility\Html;
use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Entity\ContentEntityTypeInterface;
use Drupal\Core\Entity\EntityForm;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Form\SubformState;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Url;
use Drupal\elasticsearch_helper\Plugin\ElasticsearchIndexManager;
use Drupal\elasticsearch_helper_content\ElasticsearchEntityNormalizerManagerInterface;
use Drupal\elasticsearch_helper_content\Entity\ElasticsearchContentIndex;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Form handler for the Elasticsearch content index add and edit forms.
 */
class ElasticsearchContentIndexForm extends EntityForm {

  /**
   * The entity being used by this form.
   *
   * @var \Drupal\elasticsearch_helper_content\ElasticsearchContentIndexInterface
   */
  protected $entity;

  /**
   * The entity type bundle information service instance.
   *
   * @var \Drupal\Core\Entity\EntityTypeBundleInfoInterface
   */
  protected $entityTypeBundleInfo;

  /**
   * The Elasticsearch entity normalizer manager instance.
   *
   * @var \Drupal\elasticsearch_helper_content\ElasticsearchEntityNormalizerManagerInterface
   */
  protected $elasticsearchEntityNormalizerManager;

  /**
   * The Elasticsearch index plugin manager instance.
   *
   * @var \Drupal\elasticsearch_helper\Plugin\ElasticsearchIndexManager
   */
  protected $elasticsearchIndexManager;

  /**
   * The Elasticsearch content index form class constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager instance.
   * @param \Drupal\Core\Entity\EntityTypeBundleInfoInterface $entity_type_bundle_info
   *   The entity type bundle information service instance.
   * @param \Drupal\elasticsearch_helper_content\ElasticsearchEntityNormalizerManagerInterface $elasticsearch_entity_normalizer_manager
   *   The Elasticsearch entity normalizer manager instance.
   * @param \Drupal\elasticsearch_helper\Plugin\ElasticsearchIndexManager $elasticsearch_index_manager
   *   The Elasticsearch index plugin manager instance.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, EntityTypeBundleInfoInterface $entity_type_bundle_info, ElasticsearchEntityNormalizerManagerInterface $elasticsearch_entity_normalizer_manager, ElasticsearchIndexManager $elasticsearch_index_manager) {
    $this->setEntityTypeManager($entity_type_manager);
    $this->entityTypeBundleInfo = $entity_type_bundle_info;
    $this->elasticsearchEntityNormalizerManager = $elasticsearch_entity_normalizer_manager;
    $this->elasticsearchIndexManager = $elasticsearch_index_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('entity_type.bundle.info'),
      $container->get('plugin.manager.elasticsearch_entity_normalizer'),
      $container->get('plugin.manager.elasticsearch_index.processor')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state) {
    $form = parent::form($form, $form_state);

    $form_id = Html::getId('elasticsearch-content-index-form');
    $form['#attributes']['id'] = $form_id;

    $ajax_attribute = [
      'callback' => [$this, 'reloadForm'],
      'wrapper' => $form_id,
    ];

    // Get content index entity.
    $index = $this->getEntity();

    // Get target entity type.
    $target_entity_type = $index->getTargetEntityType();

    $form['label'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Index label'),
      '#description' => $this->t('The administrative title of the content index.'),
      '#maxlength' => 255,
      '#default_value' => $index->label(),
      '#required' => TRUE,
      '#weight' => 10,
    ];

    $form['id'] = [
      '#type' => 'machine_name',
      '#default_value' => $index->id(),
      '#machine_name' => [
        'exists' => '\Drupal\elasticsearch_helper_content\Entity\ElasticsearchContentIndex::load',
      ],
      '#disabled' => !$index->isNew(),
      '#weight' => 20,
    ];

    // Get bundle info.
    $bundles_info = $this->entityTypeBundleInfo->getAllBundleInfo();

    // Get all content type entity types with at least one bundle.
    $entity_types = $this->entityTypeManager->getDefinitions();
    $entity_types = array_filter($entity_types, function ($entity_type) use ($bundles_info) {
      return $entity_type instanceof ContentEntityTypeInterface && isset($bundles_info[$entity_type->id()]);
    });

    // Prepare entity type labels.
    $entity_type_options = array_map(function ($entity_type) {
      /** @var \Drupal\Core\Entity\EntityTypeInterface $entity_type */
      return $entity_type->getLabel();
    }, $entity_types);

    $form['entity_type'] = [
      '#type' => 'select',
      '#title' => $this->t('Entity type'),
      '#description' => $this->t('Select the entity type. Cannot be changed afterwards.'),
      '#options' => $entity_type_options,
      '#default_value' => $target_entity_type,
      '#required' => TRUE,
      '#ajax' => $ajax_attribute,
      '#weight' => 30,
      '#disabled' => !$index->isNew(),
    ];

    $bundle_options = array_map(function ($bundle) {
      return $bundle['label'];
    }, $bundles_info[$target_entity_type] ?? []);

    // Explicitly set target bundle to enable normalizer options when
    // entity type is selected.
    if (!($target_bundle = $index->getTargetBundle())) {
      $target_bundle = key($bundle_options);
      $index->setTargetBundle($target_bundle);
    }

    $form['bundle'] = [
      '#type' => 'select',
      '#title' => $this->t('Bundle'),
      '#description' => $this->t('Select the bundle. Cannot be changed afterwards.'),
      '#options' => $bundle_options,
      '#default_value' => $target_bundle,
      '#required' => TRUE,
      '#ajax' => $ajax_attribute,
      '#weight' => 40,
      '#disabled' => !$index->isNew(),
    ];

    $index_name_description = $this->t('Index name must contain only lowercase letters, numbers, hyphens and underscores.');

    $form['index_name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Index name'),
      '#description' => $index_name_description,
      '#maxlength' => 255,
      '#default_value' => $index->getIndexName(),
      '#required' => TRUE,
      '#weight' => 50,
      '#states' => [
        'visible' => [
          ':input[name="entity_type"]' => ['filled' => TRUE],
        ],
      ],
    ];

    $form['multilingual'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Multilingual'),
      '#description' => t('Check if this index should support multiple languages.'),
      '#default_value' => $index->isMultilingual(),
      '#access' => $this->entityTypeTranslatable($target_entity_type),
      '#weight' => 60,
    ];

    $form['index_unpublished'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Index unpublished content'),
      '#description' => t('Check if this index should contain unpublished content.'),
      '#default_value' => $index->indexUnpublishedContent(),
      '#access' => $this->entityTypePublishAware($target_entity_type),
      '#weight' => 70,
    ];

    // Get entity normalizer definitions.
    $entity_normalizer_definitions = $this->elasticsearchEntityNormalizerManager->getDefinitions();

    // Get entity normalizer.
    $normalizer = $index->getNormalizer();

    $form['normalizer'] = [
      '#type' => 'select',
      '#title' => $this->t('Normalizer'),
      '#description' => $this->t('Select the entity normalizer.'),
      '#options' => array_map(function ($definition) {
        return $definition['label'];
      }, $entity_normalizer_definitions),
      '#default_value' => $normalizer,
      '#ajax' => $ajax_attribute,
      '#op' => 'select_normalizer',
      '#weight' => 80,
    ];

    if ($normalizer) {
      try {
        $entity_normalizer = $index->getNormalizerInstance();

        $configuration_form = [];
        $subform_state = SubformState::createForSubform($configuration_form, $form, $form_state);
        $configuration_form = $entity_normalizer->buildConfigurationForm($configuration_form, $subform_state);

        if ($configuration_form) {
          $form['normalizer_configuration'] = [
            '#type' => 'details',
            '#open' => TRUE,
            '#title' => $this->t('Normalizer settings'),
            '#weight' => 90,
          ];

          $form['normalizer_configuration']['configuration'] = $configuration_form;
          $form['normalizer_configuration']['configuration']['#parents'] = ['normalizer_configuration'];
        }
      }
      catch (\Exception $e) {
        $form['normalizer_configuration_error'] = [
          '#prefix' => '<div class="messages messages--error">',
          '#markup' => $this->t('An error occurred while rendering normalizer configuration form.'),
          '#suffix' => '</div>',
          '#weight' => 90,
        ];
        watchdog_exception('elasticsearch_helper_content', $e);
      }
    }

    return $form;
  }

  /**
   * Returns TRUE if entity type is translatable.
   *
   * @param string $entity_type_id
   *   The entity type ID.
   *
   * @return bool
   *   Returns TRUE if entity type is translatable.
   */
  protected function entityTypeTranslatable($entity_type_id) {
    // Get entity type instance.
    $entity_type = $this->entityTypeManager->getDefinition($entity_type_id, FALSE);

    return $entity_type && $entity_type->isTranslatable();
  }

  /**
   * Returns TRUE if entity type supports published/unpublished status.
   *
   * @param string $entity_type_id
   *   The entity type ID.
   *
   * @return bool
   *   Returns TRUE if entity type is publishing status aware.
   */
  protected function entityTypePublishAware($entity_type_id) {
    // Get entity type instance.
    $entity_type = $this->entityTypeManager->getDefinition($entity_type_id, FALSE);

    return $entity_type && $entity_type->hasKey('published');
  }

  /**
   * Reloads the form and returns it.
   *
   * @param array $form
   *   The form render array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state instance.
   *
   * @return array
   *   The form render array.
   */
  public function reloadForm(&$form, FormStateInterface $form_state) {
    $form_state->setRebuild(TRUE);

    return $form;
  }

  /**
   * Returns content index entity.
   *
   * @return \Drupal\elasticsearch_helper_content\ElasticsearchContentIndexInterface
   *   Returns Elasticsearch content index entity.
   */
  public function getEntity() {
    return $this->entity;
  }

  /**
   * Returns TRUE if index name already exists.
   *
   * @param string $index_name
   *   The index name.
   * @param string $entity_type
   *   The entity type.
   *
   * @return \Drupal\elasticsearch_helper_content\Entity\ElasticsearchContentIndex[]
   *   A list of content index entity IDs containing the given index name.
   */
  public function indexNameExists($index_name, $entity_type) {
    try {
      $storage = $this->entityTypeManager->getStorage('elasticsearch_content_index');
      $result = $storage->getQuery()
        ->condition('index_name', $index_name)
        ->condition('entity_type', $entity_type, '<>')
        ->execute();

      /** @var \Drupal\elasticsearch_helper_content\Entity\ElasticsearchContentIndex[] $result */
      $result = $storage->loadMultiple($result);

      return $result;
    }
    catch (\Exception $e) {
      $result = FALSE;
    }

    return $result;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    parent::validateForm($form, $form_state);

    // Validate index name.
    $index_name = $form_state->getValue('index_name');
    $entity_type = $form_state->getValue('entity_type');

    if (!preg_match('/^([a-z0-9_-]+)$/', $index_name)) {
      $form_state->setErrorByName('index_name', $this->t('Index name must contain only lowercase letters, numbers, hyphens and underscores.'));
    }

    if ($entities_using_index_name = $this->indexNameExists($index_name, $entity_type)) {
      $plugins_using_index_name = [];
      $entity_type_using_index_name = NULL;

      foreach ($entities_using_index_name as $index_plugin) {
        $plugins_using_index_name[] = $index_plugin->id();

        if (is_null($entity_type_using_index_name)) {
          $entity_type_using_index_name = $index_plugin->getTargetEntityType();
        }
      }

      $form_state->setErrorByName('index_name', $this->t('Index names cannot be shared across multiple entity types. The index name "@index_name" is used for "@entity_type" entity type in the following content index plugins: %content_indices', [
        '@index_name' => $index_name,
        '@entity_type' => $entity_type_using_index_name,
        '%content_indices' => implode(', ', $plugins_using_index_name),
      ]));
    }

    // Get normalizer instance.
    $index = $this->getEntity();
    $normalizer_instance = $index->getNormalizerInstance();

    // Validate normalizer form.
    $subform_parents = ['normalizer_configuration', 'configuration'];

    if ($subform = &NestedArray::getValue($form, $subform_parents)) {
      $subform_state = SubformState::createForSubform($subform, $form, $form_state);
      $normalizer_instance->validateConfigurationForm($subform, $subform_state);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    parent::submitForm($form, $form_state);

    $index = $this->getEntity();
    $target_entity_type = $index->getTargetEntityType();

    // Set multilingual flag to FALSE if entity type is not translatable.
    if (!$this->entityTypeTranslatable($target_entity_type)) {
      $index->set('multilingual', FALSE);
    }

    // Set "index unpublished" value.
    if (!$this->entityTypePublishAware($target_entity_type)) {
      $index_unpublished = ElasticsearchContentIndex::INDEX_UNPUBLISHED_NA;
    }
    else {
      $index_unpublished = $form_state->getValue('index_unpublished') ? ElasticsearchContentIndex::INDEX_UNPUBLISHED : ElasticsearchContentIndex::INDEX_UNPUBLISHED_IGNORE;
    }
    $index->set('index_unpublished', $index_unpublished);

    // Get normalizer instance.
    $normalizer_instance = $index->getNormalizerInstance();

    // Submit normalizer form.
    $subform_parents = ['normalizer_configuration', 'configuration'];
    $subform = &NestedArray::getValue($form, $subform_parents, $subform_exists);
    // Create a subform array if one cannot be found.
    $subform = $subform_exists ? $subform : [];
    $subform_state = SubformState::createForSubform($subform, $form, $form_state);
    $normalizer_instance->submitConfigurationForm($subform, $subform_state);

    // Set normalizer configuration.
    $index->setNormalizerConfiguration($normalizer_instance->getConfiguration());
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    $index = $this->getEntity();
    $status = $index->save();

    if ($status) {
      $this->messenger()->addMessage($this->t('Saved the %label index.', [
        '%label' => $index->label(),
      ]));
    }
    else {
      $this->messenger()->addMessage($this->t('The %label index was not saved.', [
        '%label' => $index->label(),
      ]), MessengerInterface::TYPE_ERROR);
    }

    $url = Url::fromRoute('elasticsearch_helper_index_management.index.list');
    $form_state->setRedirectUrl($url);

    // Clear cached Elasticsearch index plugin definitions.
    $this->elasticsearchIndexManager->clearCachedDefinitions();
  }

}
