<?php

namespace Drupal\elasticsearch_helper_content\Plugin\ElasticsearchNormalizer\Field;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\elasticsearch_helper\Elasticsearch\Index\FieldDefinition;

/**
 * The path related field normalize base class.
 */
abstract class PathBase extends FieldNormalizerBase {

  use StringTranslationTrait;

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
      '#title' => $this->t('Use absolute URL'),
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

  /**
   * {@inheritdoc}
   */
  public function configurationSummary() {
    $summary = [];

    if (!empty($this->configuration['absolute_url'])) {
      $summary[] = $this->t('Use absolute URL');
    }

    return $summary;
  }

}
