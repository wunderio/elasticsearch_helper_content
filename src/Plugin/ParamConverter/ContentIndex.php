<?php

namespace Drupal\elasticsearch_helper_content\Plugin\ParamConverter;

use Drupal\Core\ParamConverter\AdminPathConfigEntityConverter;
use Symfony\Component\Routing\Route;

/**
 * The content index param converter class.
 */
class ContentIndex extends AdminPathConfigEntityConverter {

  /**
   * {@inheritdoc}
   */
  public function convert($value, $definition, $name, array $defaults) {
    $prefix = 'content_index:';

    if (strpos($value, $prefix) === 0) {
      $value = substr($value, strlen($prefix));
    }

    return parent::convert($value, $definition, $name, $defaults);
  }

  /**
   * {@inheritdoc}
   */
  public function applies($definition, $name, Route $route) {
    return $name == 'elasticsearch_content_index';
  }

}
