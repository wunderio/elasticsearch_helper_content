<?php

namespace Drupal\elasticsearch_helper_content\Plugin\ElasticsearchNormalizer;

use Drupal\Component\Plugin\ConfigurableInterface;
use Drupal\Component\Plugin\PluginBase;
use Drupal\Component\Utility\NestedArray;
use Drupal\Core\DependencyInjection\DependencySerializationTrait;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Plugin\PluginFormInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Base class for Elasticsearch Content Normalizer plugins.
 */
abstract class ElasticsearchNormalizerBase extends PluginBase implements ContainerFactoryPluginInterface, PluginFormInterface, ConfigurableInterface {

  use DependencySerializationTrait;

  /**
   * The target entity type ID.
   *
   * @var string
   */
  protected $targetEntityType;

  /**
   * The target bundle name.
   *
   * @var string
   */
  protected $targetBundle;

  /**
   * The Elasticsearch content index entity.
   *
   * @var \Drupal\elasticsearch_helper_content\ElasticsearchContentIndexInterface|null
   */
  protected $contentIndexEntity;

  /**
   * {@inheritdoc}
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition) {
    if (!isset($configuration['entity_type'], $configuration['bundle'])) {
      throw new \InvalidArgumentException(t('Entity type or bundle key is not provided in plugin configuration.'));
    }

    $this->targetEntityType = $configuration['entity_type'];
    $this->targetBundle = $configuration['bundle'];
    $this->contentIndexEntity = $configuration['content_index_entity'] ?? NULL;
    unset($configuration['entity_type'], $configuration['bundle'], $configuration['content_index_entity']);

    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->setConfiguration($configuration);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public function getConfiguration() {
    // Return only defined configuration keys.
    return array_intersect_key($this->configuration, $this->defaultConfiguration());
  }

  /**
   * {@inheritdoc}
   */
  public function setConfiguration(array $configuration) {
    $configurations = [$this->defaultConfiguration(), $configuration];
    $this->configuration = NestedArray::mergeDeepArray($configurations, TRUE);
  }

  /**
   * Returns the target entity type ID.
   *
   * @return mixed|string
   *   The entity type ID.
   */
  public function getTargetEntityType() {
    return $this->targetEntityType;
  }

  /**
   * Returns the target bundle.
   *
   * @return mixed|string
   *   The target bundle.
   */
  public function getTargetBundle() {
    return $this->targetBundle;
  }

  /**
   * Returns the content index entity.
   *
   * @return \Drupal\elasticsearch_helper_content\ElasticsearchContentIndexInterface|mixed|null
   *   The Elasticsearch content index entity.
   */
  public function getContentIndexEntity() {
    return $this->contentIndexEntity;
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function validateConfigurationForm(array &$form, FormStateInterface $form_state) {
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
  }

}
