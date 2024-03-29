<?php

namespace Drupal\elasticsearch_helper_content\Plugin\ElasticsearchNormalizer\Field;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Field\FieldItemInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\datetime\Plugin\Field\FieldType\DateTimeItemInterface;
use Drupal\elasticsearch_helper\Elasticsearch\Index\FieldDefinition;

/**
 * The date field normalizer plugin class.
 *
 * @ElasticsearchFieldNormalizer(
 *   id = "date",
 *   label = @Translation("Date"),
 *   field_types = {
 *     "datetime",
 *     "timestamp",
 *     "created",
 *     "changed"
 *   }
 * )
 */
class Date extends FieldNormalizerBase {

  use StringTranslationTrait;

  /**
   * {@inheritdoc}
   *
   * @throws \InvalidArgumentException
   */
  public function getFieldItemValue(EntityInterface $entity, FieldItemInterface $item, array $context = []) {
    $value = NULL;

    if ($item instanceof DateTimeItemInterface) {
      /** @var \DateTime $date */
      if ($date = $item->date) {
        $value = $date->getTimestamp();
      }
    }
    else {
      $value = $item->value;
    }

    return $value;
  }

  /**
   * {@inheritdoc}
   */
  public function getFieldDefinition() {
    $definition = FieldDefinition::create('date');
    // Add format.
    $definition->addOption('format', $this->configuration['format']);

    return $definition;
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'format' => 'epoch_second',
    ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildConfigurationForm($form, $form_state);

    $form['format'] = [
      '#type' => 'select',
      '#title' => t('Format'),
      '#options' => $this->getFormatOptions(),
      '#default_value' => $this->configuration['format'],
    ];

    return $form;
  }

  /**
   * Defines available date formats.
   *
   * Use non-translated labels for formats.
   *
   * @return array
   *   A list of date formats.
   */
  protected function getFormats() {
    return [
      'epoch_second' => $this->t('Seconds since epoch (timestamp)'),
    ];
  }

  /**
   * Returns a list of format options.
   *
   * @return array
   *   A list of formats.
   */
  protected function getFormatOptions() {
    return array_map(function ($format) {
      return (string) $format;
    }, $this->getFormats());
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    parent::submitConfigurationForm($form, $form_state);

    $this->configuration['format'] = $form_state->getValue('format');
  }

  /**
   * {@inheritdoc}
   */
  public function configurationSummary() {
    $summary = parent::configurationSummary();
    $formats = $this->getFormats();

    $summary[] = $this->t('@format', [
      '@format' => $formats[$this->configuration['format']],
    ]);

    return $summary;
  }

}
