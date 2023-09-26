<?php

namespace Drupal\elasticsearch_helper_content\Plugin\ElasticsearchNormalizer\Field;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Field\FieldItemInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\elasticsearch_helper\Elasticsearch\Index\FieldDefinition;

/**
 * The text field normalizer plugin class.
 *
 * @ElasticsearchFieldNormalizer(
 *   id = "text",
 *   label = @Translation("Text"),
 *   field_types = {
 *     "string",
 *     "text",
 *     "text_long",
 *     "text_with_summary",
 *     "list_string"
 *   }
 * )
 */
class Text extends FieldNormalizerBase {

  use StringTranslationTrait;
  use TextHelper;

  /**
   * {@inheritdoc}
   */
  public function getFieldItemValue(EntityInterface $entity, FieldItemInterface $item, array $context = []) {
    $result = $item->value;

    if ($this->configuration['strip_tags']) {
      $result = $this->stripTags($result);
    }

    return $result;
  }

  /**
   * {@inheritdoc}
   */
  public function getFieldDefinition() {
    $definition = FieldDefinition::create('text');

    // Store raw value as keyword field.
    if ($this->configuration['store_raw']) {
      $definition->addMultiField('raw', FieldDefinition::create('keyword'));
    }

    return $definition;
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'store_raw' => FALSE,
      'strip_tags' => FALSE,
    ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildConfigurationForm($form, $form_state);

    $form['store_raw'] = [
      '#type' => 'checkbox',
      '#title' => t('Store the raw value as a keyword in a multi-field.'),
      '#default_value' => $this->configuration['store_raw'],
    ];
    $form['strip_tags'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Strip HTML tags'),
      '#default_value' => $this->configuration['strip_tags'],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    parent::submitConfigurationForm($form, $form_state);

    $this->configuration['store_raw'] = (bool) $form_state->getValue('store_raw');
    $this->configuration['strip_tags'] = $form_state->getValue('strip_tags');
  }

  /**
   * {@inheritdoc}
   */
  public function configurationSummary() {
    $summary = parent::configurationSummary();

    if (!empty($this->configuration['store_raw'])) {
      $summary[] = $this->t('Store raw value');
    }

    if (!empty($this->configuration['strip_tags'])) {
      $summary[] = $this->t('Strip HTML tags');
    }

    return $summary;
  }

}
