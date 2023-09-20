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

  /**
   * {@inheritdoc}
   */
  public function getFieldItemValue(EntityInterface $entity, FieldItemInterface $item, array $context = []) {
    return $item->value;
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
      '#weight' => 50,
      '#default_value' => $this->configuration['store_raw'],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    parent::submitConfigurationForm($form, $form_state);

    $this->configuration['store_raw'] = (bool) $form_state->getValue('store_raw');
  }

  /**
   * {@inheritdoc}
   */
  public function configurationSummary() {
    $summary = parent::configurationSummary();

    if (!empty($this->configuration['store_raw'])) {
      $summary[] = $this->t('Store raw value');
    }

    return $summary;
  }

}
