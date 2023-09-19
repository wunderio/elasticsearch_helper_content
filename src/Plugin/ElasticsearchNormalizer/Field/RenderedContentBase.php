<?php

namespace Drupal\elasticsearch_helper_content\Plugin\ElasticsearchNormalizer\Field;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\Session\AccountSwitcherInterface;
use Drupal\Core\Session\AnonymousUserSession;
use Drupal\Core\Theme\ThemeInitializationInterface;
use Drupal\Core\Theme\ThemeManagerInterface;
use Drupal\elasticsearch_helper\Elasticsearch\Index\FieldDefinition;
use Drupal\elasticsearch_helper_content\ElasticsearchNormalizerHelper;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * The base class for entity and field renderer classes.
 */
abstract class RenderedContentBase extends FieldNormalizerBase {

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
   * The configuration factory instance.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * The theme manager instance.
   *
   * @var \Drupal\Core\Theme\ThemeManagerInterface
   */
  protected $themeManager;

  /**
   * The theme initialization service instance.
   *
   * @var \Drupal\Core\Theme\ThemeInitializationInterface
   */
  protected $themeInitialization;

  /**
   * Rendered entity "field normalizer" class constructor.
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
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The configuration factory instance.
   * @param \Drupal\Core\Theme\ThemeManagerInterface $theme_manager
   *   The theme manager instance.
   * @param \Drupal\Core\Theme\ThemeInitializationInterface $theme_initialization
   *   The theme initialization instance.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, ElasticsearchNormalizerHelper $normalizer_helper, RendererInterface $renderer, AccountSwitcherInterface $account_switcher, ConfigFactoryInterface $config_factory, ThemeManagerInterface $theme_manager, ThemeInitializationInterface $theme_initialization) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);

    $this->normalizerHelper = $normalizer_helper;
    $this->renderer = $renderer;
    $this->accountSwitcher = $account_switcher;
    $this->configFactory = $config_factory;
    $this->themeManager = $theme_manager;
    $this->themeInitialization = $theme_initialization;
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
      $container->get('account_switcher'),
      $container->get('config.factory'),
      $container->get('theme.manager'),
      $container->get('theme.initialization')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function normalize($entity, $field, array $context = []) {
    $result = '';

    // Remember current active theme.
    $current_active_theme = $this->themeManager->getActiveTheme();

    try {
      // Switch the account to anonymous.
      $this->accountSwitcher->switchTo(new AnonymousUserSession());

      // Load the theme object for the default theme.
      $default_theme = $this->themeInitialization->initTheme($this->getRenderTheme());
      // Switch the theme.
      $this->themeManager->setActiveTheme($default_theme);

      // Let the subclasses normalize the content.
      $result = $this->doNormalize($entity, $field, $context);
    }
    catch (\Exception $e) {
      watchdog_exception('elasticsearch_helper_content', $e);
    }
    finally {
      // Restore the user.
      $this->accountSwitcher->switchBack();

      // Revert the active theme.
      $this->themeManager->setActiveTheme($current_active_theme);
    }

    return $result;
  }

  /**
   * Returns the rendered output.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The index-able entity.
   * @param \Drupal\Core\Field\FieldItemListInterface|null $field
   *   The field item list instance.
   * @param array $context
   *   The context array.
   *
   * @return string
   *   The rendered output.
   */
  protected function doNormalize($entity, $field, array $context = []) {
    return '';
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
    $form = parent::buildConfigurationForm($form, $form_state);

    // Get entity view displays.
    $entity_view_displays = $this->normalizerHelper->getEntityViewDisplayOptions($this->targetEntityType, $this->targetBundle);

    $form['view_mode'] = [
      '#type' => 'select',
      '#title' => t('View mode'),
      '#options' => $entity_view_displays,
      '#default_value' => $this->configuration['view_mode'],
    ];
    $form['strip_tags'] = [
      '#type' => 'checkbox',
      '#title' => t('Strip HTML tags'),
      '#default_value' => $this->configuration['strip_tags'],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    parent::submitConfigurationForm($form, $form_state);

    $this->configuration['view_mode'] = $form_state->getValue('view_mode');
    $this->configuration['strip_tags'] = $form_state->getValue('strip_tags');
  }

  /**
   * Determine the name of the theme that should be used for rendering.
   *
   * @return string
   *   The theme name.
   */
  public function getRenderTheme() {
    return $this->configFactory->get('system.theme')->get('default');
  }

}
