<?php

namespace Drupal\elasticsearch_helper_content\Plugin\ElasticsearchNormalizer\Field;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Field\FieldItemInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\elasticsearch_helper\Elasticsearch\Index\FieldDefinition;
use Drupal\elasticsearch_helper_content\ElasticsearchFieldNormalizerBase;

/**
 * The path field normalizer plugin class.
 *
 * @ElasticsearchFieldNormalizer(
 *   id = "path",
 *   label = @Translation("Path"),
 *   field_types = {
 *     "path"
 *   }
 * )
 */
class Path extends ElasticsearchFieldNormalizerBase {

  /**
   * {@inheritdoc}
   */
  public function getFieldItemValue(EntityInterface $entity, FieldItemInterface $item, array $context = []) {
    if ($url = $this->getUrl($entity, $item, $context)) {
      return $url->toString();
    }

    return NULL;
  }

  /**
   * Returns Url object.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The index-able entity.
   * @param \Drupal\Core\Field\FieldItemInterface $item
   *   The field item instance.
   * @param array $context
   *   The context array.
   *
   * @return \Drupal\Core\Url|null
   *   The Url instance or null.
   */
  protected function getUrl(EntityInterface $entity, FieldItemInterface $item, array $context = []) {
    try {
      /** @var \Drupal\Core\Url $url */
      $url = $entity->toUrl('canonical');

      if (!empty($this->configuration['absolute_url'])) {
        $url->setAbsolute();
      }

      return $url;
    }
    catch (\Exception $e) {
      watchdog_exception('elasticsearch_helper_content', $e);
    }

    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function getFieldDefinition() {
    return FieldDefinition::create('keyword');
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'absolute_url' => FALSE,
    ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildConfigurationForm($form, $form_state);

    $form['absolute_url'] = [
      '#type' => 'checkbox',
      '#title' => t('Use absolute URL'),
      '#default_value' => $this->configuration['absolute_url'],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    parent::submitConfigurationForm($form, $form_state);

    $this->configuration['absolute_url'] = (bool) $form_state->getValue('absolute_url');
  }

}
