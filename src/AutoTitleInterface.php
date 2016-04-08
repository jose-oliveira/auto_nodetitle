<?php

/**
 * @file
 * Contains Drupal\auto_nodetitle\AutoTitleInterface.
 */

namespace Drupal\auto_nodetitle;

/**
 * Provides an interface for an automatic title service.
 */
interface AutoTitleInterface {

  /**
   * Sets the automatically generated title for the entity.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   Content entity.
   *
   * @return string
   *   The applied title. The entity is also updated with this title.
   */
  public function setTitle($entity);

  /**
   * Determines if the entity bundle has auto title enabled.
   *
   * @param string $entity_type
   *   The machine readable name of the entity.
   * @param string $bundle
   *   Bundle machine name.
   *
   * @return bool
   *   True if the entity bundle has an automatic title.
   */
  public function hasAutoTitle($entity_type, $bundle);

  /**
   * Determines if the entity bundle has an optional auto title.
   *
   * Optional means that if the title is empty, it will be automatically
   * generated.
   *
   * @param string $entity_type
   *   The machine readable name of the entity.
   * @param string $bundle
   *   Bundle machine name.
   *
   * @return bool
   *   True if the entity bundle has an optional automatic title.
   */
  public function hasOptionalAutoTitle($entity_type, $bundle);

  /**
   * Returns whether the automatic title has to be set.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   Content entity.
   *
   * @return bool
   *   Returns true if the title
   */
  public function autoTitleNeeded($entity);
}