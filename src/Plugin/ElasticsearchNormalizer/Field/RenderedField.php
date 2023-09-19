<?php

namespace Drupal\elasticsearch_helper_content\Plugin\ElasticsearchNormalizer\Field;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\Session\AccountSwitcherInterface;
use Drupal\Core\Session\AnonymousUserSession;
use Drupal\elasticsearch_helper\Elasticsearch\Index\FieldDefinition;
use Drupal\elasticsearch_helper_content\ElasticsearchFieldNormalizerBase;
use Drupal\elasticsearch_helper_content\ElasticsearchNormalizerHelper;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * The "rendered field" field normalizer plugin class.
 *
 * This field normalizer is able to render any entity field.
 *
 * @ElasticsearchFieldNormalizer(
 *   id = "rendered_field",
 *   label = @Translation("Rendered field"),
 *   field_types = {
 *     "list"
 *   },
 *   weight = 100
 * )
 */
class RenderedField extends ElasticsearchFieldNormalizerBase {

  /**
   * The Elasticsearch normalizer helper instance.
   *
   * @var \Drupal\elasticsearch_helper_content\ElasticsearchNormalizerHelper
   */
  protected $normalizerHelper;

  /**
   * The renderer service instance.
   *
   * @var \Drupal\Core\Render\Renderer
   */
  protected $renderer;

  /**
   * The account switcher instance.
   *
   * @var \Drupal\Core\Session\AccountSwitcherInterface
   */
  protected $accountSwitcher;

  /**
   * Rendered field "field normalizer" class constructor.
   *
   * @param array $configuration
   *   The plugin configuration array.
   * @param string $plugin_id
   *   The plugin ID.
   * @param array $plugin_definition
   *   The plugin definition.
   * @param \Drupal\elasticsearch_helper_content\ElasticsearchNormalizerHelper $normalizer_helper
   *   The Elasticsearch normalizer helper instance.
   * @param \Drupal\Core\Render\RendererInterface $renderer
   *   The renderer service instance.
   * @param \Drupal\Core\Session\AccountSwitcherInterface $account_switcher
   *   The account switcher interface.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, ElasticsearchNormalizerHelper $normalizer_helper, RendererInterface $renderer, AccountSwitcherInterface $account_switcher) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);

    $this->normalizerHelper = $normalizer_helper;
    $this->renderer = $renderer;
    $this->accountSwitcher = $account_switcher;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('elasticsearch_helper_content.normalizer_helper'),
      $container->get('renderer'),
      $container->get('account_switcher')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function normalize($entity, $field, array $context = []) {
    $result = '';
    // Switch the account to anonymous.
    $this->accountSwitcher->switchTo(new AnonymousUserSession());

    try {
      $result = $this->getEmptyFieldValue($entity, $field, $context);

      if ($field && !$field->isEmpty()) {
        $build = $field->view($this->configuration['view_mode']);
        $result = $this->renderer->renderPlain($build);

        if ($this->configuration['strip_tags']) {
          $result = strip_tags($result);
        }
      }
    }
    catch (\Exception $e) {
      watchdog_exception('elasticsearch_helper_content', $e);
    }
    finally {
      // Restore the user.
      $this->accountSwitcher->switchBack();
    }

    return $result;
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
      'view_mode' => 'default',
      'strip_tags' => FALSE,
    ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $entity_view_displays = $this->normalizerHelper->getEntityViewDisplayOptions($this->targetEntityType, $this->targetBundle);

    return [
      'view_mode' => [
        '#type' => 'select',
        '#title' => t('View mode'),
        '#options' => $entity_view_displays,
        '#default_value' => $this->configuration['view_mode'],
      ],
      'strip_tags' => [
        '#type' => 'checkbox',
        '#title' => t('Strip HTML tags'),
        '#default_value' => $this->configuration['strip_tags'],
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    $this->configuration['view_mode'] = $form_state->getValue('view_mode');
    $this->configuration['strip_tags'] = $form_state->getValue('strip_tags');
  }

}
