<?php
/**
 * @file
 * Contains \Drupal\auto_nodetitle\EntityDecoratorInterface.
 */

namespace Drupal\auto_nodetitle;

use Drupal\Core\Entity\ContentEntityInterface;

/**
 * Provides an interface for EntityDecorator.
 */
interface EntityDecoratorInterface {

  /**
   * Auto title entity decorator.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *
   * @return \Drupal\auto_nodetitle\AutoTitle|\Drupal\Core\Entity\ContentEntityInterface
   */
  public function decorate(ContentEntityInterface $entity);
}
