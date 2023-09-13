<?php

namespace Drupal\elasticsearch_helper_content;

use Drupal\Core\Entity\ContentEntityInterface;

/**
 * Entity renderer interface.
 */
interface EntityRendererInterface {

  /**
   * Renders the entity and returns it as plain text.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The render-able entity.
   * @param string $view_mode
   *   The view mode name.
   *
   * @return string
   *   The rendered output as a string stripped of HTML tags.
   */
  public function renderEntityPlainText(ContentEntityInterface $entity, $view_mode);

  /**
   * Renders a content to a string using given view mode.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The render-able entity.
   * @param string $view_mode
   *   The view mode name.
   *
   * @return string
   *   The rendered output.
   */
  public function renderEntity(ContentEntityInterface $entity, $view_mode);

}
