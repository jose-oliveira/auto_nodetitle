<?php

/**
 * @file
 * Contains \Drupal\auto_nodetitle\Plugin\Action\UpdateAutoNodeTitle.
 */

namespace Drupal\auto_nodetitle\Plugin\Action;

use Drupal\auto_nodetitle\AutoTitle;
use Drupal\Core\Action\ActionBase;
use Drupal\Core\Session\AccountInterface;

/**
 * Provides an action that updates nodes with their automatic titles.
 *
 * @Action(
 *   id = "auto_nodetitle_update_action",
 *   label = @Translation("Update automatic nodetitles"),
 *   type = "node"
 * )
 */
class UpdateAutoNodeTitle extends ActionBase {

  // @todo DI for AutoTitle
  
  /**
   * {@inheritdoc}
   */
  public function execute($entity = NULL) {

    if ($entity && $this->auto_title->autoTitleNeeded($entity)) {
      $previous_title = $entity->getTitle();
      $this->auto_title->setTitle($entity);
      // Only save if the title has actually changed.
      if ($entity->getTitle() != $previous_title) {
        $entity->save();
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function access($object, AccountInterface $account = NULL, $return_as_object = FALSE) {
    /** @var \Drupal\node\NodeInterface $object */
    return $object->access('update', $account, $return_as_object);
  }

}
