<?php

/**
 * @file
 * Contains \Drupal\auto_nodetitle\AutoTitle.
 */

namespace Drupal\auto_nodetitle;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Utility\Token;

/**
 * Provides the automatic title generation functionality.
 */
class AutoTitle {

  /**
   * Automatic title is disabled.
   */
  const DISABLED = 0;

  /**
   * Automatic title is enabled. Will always be generated.
   */
  const ENABLED = 1;

  /**
   * Automatic title is optional. Will only be generated if no title was
   * given.
   */
  const OPTIONAL = 2;

  /**
   * Config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManager
   */
  protected $entityTypeManager;

  /**
   * Token service.
   *
   * @var \Drupal\Core\Utility\Token
   */
  protected $token;

  /**
   * Auto title configuration per entity.
   *
   * @var array
   */
  protected $config;

  /**
   * Constructs an AutoTitle object.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   Configuration factory.
   * @param \Drupal\Core\Entity\EntityTypeManager $entity_type_manager
   *   Entity type manager
   * @param \Drupal\Core\Utility\Token $token
   *   Token manager.
   */
  public function __construct(ConfigFactoryInterface $config_factory, \Drupal\Core\Entity\EntityTypeManager $entity_type_manager, Token $token) {
    $this->configFactory = $config_factory;
    $this->entityTypeManager = $entity_type_manager;
    $this->token = $token;
  }

  /**
   * Sets the automatically generated title for the entity.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   Content entity.
   *
   * @return string
   *   The applied title. The entity is also updated with this title.
   */
  public function setTitle($entity) {

    $entity_type = $entity->getEntityType()->id();
    $entity->bundle();
    $bundle = $entity->bundle();
    /** @var \Drupal\node\Entity\NodeType $node_type */
    // @todo
    $node_type = $this->entityTypeManager->getStorage('node_type')->load($bundle);

    $pattern = $this->getConfig($entity_type, $bundle)->get('pattern') ?: '';
    if (trim($pattern)) {
      $entity->changed = REQUEST_TIME;
      $title = $this->generateTitle($pattern, $entity);
    }
    elseif ($entity->id()) {
      $title = t('@type @id', array(
        '@type' => $node_type->label(),
        '@id' => $entity->id(),
      ));
    }
    else {
      $title = t('@type', array('@type' => $node_type->label()));
    }

    // Ensure the generated title isn't too long.
    $title = substr($title, 0, 255);
    $entity->title->setValue($title);

    // @todo This sets a public property. This is no good architecture.
    // With that flag we ensure we don't apply the title two times to the same
    // node. See autoTitleNeeded().
    $entity->auto_title_applied = TRUE;

    return $title;
  }

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
  public function hasAutoTitle($entity_type, $bundle) {
    return $this->getConfig($entity_type, $bundle)->get('status') == self::ENABLED;
  }

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
  public function hasOptionalAutoTitle($entity_type, $bundle) {
    return $this->getConfig($entity_type, $bundle)->get('status') == self::OPTIONAL;
  }

  /**
   * Returns whether the automatic title has to be set.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   Content entity.
   *
   * @return bool
   *   Returns true if the title
   */
  public function autoTitleNeeded($entity) {
    $not_applied = empty($entity->auto_title_applied);
    $entity_type = $entity->getEntityType()->id();
    $bundle = $entity->bundle();
    $required = $this->hasAutoTitle($entity_type, $bundle);
    $optional = $this->hasOptionalAutoTitle($entity_type, $bundle) && empty($entity->getTitle());
    return $not_applied && ($required || $optional);
  }

  /**
   * Helper function to generate the title according to the settings.
   *
   * @param string $pattern
   *   Title pattern. May contain tokens.
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   Content entity.
   *
   * @return string
   *   A title string
   */
  protected function generateTitle($pattern, $entity) {
    // Replace tokens.
    $entity_type = $entity->getEntityType()->id();
    $output = $this->token
        ->replace($pattern, array($entity_type => $entity), array(
        'sanitize' => FALSE,
        'clear' => TRUE
      ));

    // Evaluate PHP.
    $entity_type = $entity->getEntityType()->id();
    $bundle = $entity->bundle();
    if ($this->getConfig($entity_type, $bundle)->get('php')) {
      $output = $this->evalTitle($output, $entity);
    }
    // Strip tags.
    $output = preg_replace('/[\t\n\r\0\x0B]/', '', strip_tags($output));

    return $output;
  }

  /**
   * Returns the automatic title configuration of a content entity bundle.
   *
   * @param string $entity_type
   *   The machine readable name of the entity.
   * @param string $bundle
   *   Content entity bundle for which to get the configuration.
   *
   * @return \Drupal\Core\Config\ImmutableConfig
   */
  protected function getConfig($entity_type, $bundle) {
    $key = "$entity_type.$bundle";
    if (!isset($this->config[$key])) {
      $this->config[$key] = $this->configFactory->get('auto_nodetitle.' . $key);
    }

    return $this->config[$key];
  }

  /**
   * Evaluates php code and passes the entity to it.
   *
   * @param $code
   *   PHP code to evaluate.
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   Content entity to pass through to the PHP script.
   *
   * @return string
   *   String to use as title.
   */
  public static function evalTitle($code, $entity) {
    ob_start();
    print eval('?>' . $code);
    $output = ob_get_contents();
    ob_end_clean();

    return $output;
  }

}
