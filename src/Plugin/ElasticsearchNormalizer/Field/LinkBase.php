<?php

namespace Drupal\elasticsearch_helper_content\Plugin\ElasticsearchNormalizer\Field;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Url;

/**
 * Link field normalizer base class.
 */
abstract class LinkBase extends FieldNormalizerBase {

  use StringTranslationTrait;

  /**
   * Returns either the original URI or the relative/absolute URL.
   *
   * @param string $uri
   *   The URI.
   *
   * @return string
   *   The URI or the relative/absolute URL.
   */
  protected function getUri($uri) {
    switch ($this->configuration['uri_format']) {
      case 'relative_url':
        $uri = Url::fromUri($uri)->toString();
        break;

      case 'absolute_url':
        $uri = Url::fromUri($uri)->setAbsolute()->toString();
        break;
    }

    return $uri;
  }

  /**
   * Returns a list of URI format options.
   *
   * @return array
   *   A list of options.
   */
  protected function getUriFormatOptions() {
    return [
      'uri' => $this->t('Default (URI)'),
      'relative_url' => $this->t('Relative URL'),
      'absolute_url' => $this->t('Absolute URL'),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'uri_format' => 'uri',
    ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildConfigurationForm($form, $form_state);

    $form['uri_format'] = [
      '#type' => 'select',
      '#title' => t('URI format'),
      '#options' => $this->getUriFormatOptions(),
      '#default_value' => $this->configuration['uri_format'],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    parent::submitConfigurationForm($form, $form_state);

    $this->configuration['uri_format'] = $form_state->getValue('uri_format');
  }

  /**
   * {@inheritdoc}
   */
  public function configurationSummary() {
    $summary = parent::configurationSummary();

    // Get URI format options.
    $uri_format_options = $this->getUriFormatOptions();

    if (isset($uri_format_options[$this->configuration['uri_format']])) {
      $summary[] = $this->t('URI format: @format', [
        '@format' => $uri_format_options[$this->configuration['uri_format']],
      ]);
    }

    return $summary;
  }

}
