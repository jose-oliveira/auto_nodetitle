<?php

/**
 * @file
 * Contains \Drupal\auto_nodetitle\Plugin\Action\UpdateAutoNodeTitle.
 */

namespace Drupal\auto_nodetitle\Plugin\Action;

use Drupal\auto_nodetitle\TitleGenerator;
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

  // @todo DI for TitleGenerator
  
  /**
   * {@inheritdoc}
   */
  public function execute($entity = NULL) {

    if ($entity && $this->title_generator->auto_nodetitle_is_needed($entity)) {
      $previous_title = $entity->getTitle();
      $this->title_generator->setTitle($entity);
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
