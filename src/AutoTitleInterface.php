<?php

/**
 * @file
 * Contains Drupal\auto_nodetitle\AutoTitleInterface.
 */

namespace Drupal\auto_nodetitle;

/**
 * Provides an interface for AutoTitle.
 */
interface AutoTitleInterface {

  /**
   * Sets the automatically generated title.
   *
   * @return string
   *   The applied title. The entity is also updated with this title.
   */
  public function setTitle();

  /**
   * Determines if the entity bundle has auto title enabled.
   *
   * @return bool
   *   True if the entity bundle has an automatic title.
   */
  public function hasAutoTitle();

  /**
   * Determines if the entity bundle has an optional auto title.
   *
   * Optional means that if the title is empty, it will be automatically
   * generated.
   *
   * @return bool
   *   True if the entity bundle has an optional automatic title.
   */
  public function hasOptionalAutoTitle();

  /**
   * Returns whether the automatic title has to be set.
   *
   * @return bool
   *   Returns true if the title should be automatically generated.
   */
  public function autoTitleNeeded();
}
