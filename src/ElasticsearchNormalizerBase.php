<?php

namespace Drupal\elasticsearch_helper_content;

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
   * {@inheritdoc}
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition) {
    if (!isset($configuration['entity_type'], $configuration['bundle'])) {
      throw new \InvalidArgumentException(t('Entity type or bundle key is not provided in plugin configuration.'));
    }

    $this->targetEntityType = $configuration['entity_type'];
    $this->targetBundle = $configuration['bundle'];
    unset($configuration['entity_type'], $configuration['bundle']);

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
