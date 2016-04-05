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
   * Automatic node title is disabled.
   */
  const DISABLED = 0;

  /**
   * Automatic node title is enabled. Will always be generated.
   */
  const ENABLED = 1;

  /**
   * Automatic node title is optional. Will only be generated if no title was
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
   * Auto node title configuration per content type.
   *
   * @var array
   */
  protected $config;

  /**
   * Constructs an AutoNodeTitle object.
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
   * Sets the automatically generated nodetitle for the node.
   *
   * @param \Drupal\node\Entity\Node $node
   *   Node object.
   *
   * @return string
   *   The applied node title. The node object is also updated with this title.
   */
  public function setTitle($node) {
    
    $type = $node->getType();
    /** @var \Drupal\node\Entity\NodeType $node_type */
    $node_type = $this->entityTypeManager->getStorage('node_type')->load($type);

    $pattern = $this->getConfig($type)->get('pattern') ?: '';
    if (trim($pattern)) {
      $node->changed = REQUEST_TIME;
      $title = $this->generateTitle($pattern, $node);
    }
    elseif ($node->id()) {
      $title = t('@type @node-id', array(
        '@type' => $node_type->label(),
        '@node-id' => $node->id(),
      ));
    }
    else {
      $title = t('@type', array('@type' => $node_type->label()));
    }

    // Ensure the generated title isn't too long.
    $title = substr($title, 0, 255);
    $node->title->setValue($title);

    // @todo This sets a public property. This is no good architecture.
    // With that flag we ensure we don't apply the title two times to the same
    // node. See autoTitleNeeded().
    $node->auto_nodetitle_applied = TRUE;

    return $title;
  }

  /**
   * Determines if the node type has auto node title enabled.
   *
   * @param string $type
   *   Node type.
   *
   * @return bool
   *   True if the node type has an automatic node title.
   */
  public function hasAutoTitle($type) {
    return $this->getConfig($type)->get('status') == self::ENABLED;
  }

  /**
   * Determines if the node type has an optional auto node title.
   *
   * Optional means that if the node title is empty, it will be automatically
   * filled.
   *
   * @param string $type
   *   Node type.
   *
   * @return bool
   *   True if the node type has an optional automatic node title.
   */
  public function hasOptionalAutoTitle($type) {
    return $this->getConfig($type)->get('status') == self::OPTIONAL;
  }

  /**
   * Returns whether the automatic title has to be set.
   *
   * @param \Drupal\node\Entity\Node $node
   *   Node object.
   *
   * @return bool
   *   Returns true if the title
   */
  public function autoTitleNeeded($node) {
    $not_applied = empty($node->auto_nodetitle_applied);
    $type = $node->getType();
    $required = $this->hasAutoTitle($type);
    $optional = $this->hasOptionalAutoTitle($type) && empty($node->getTitle());
    return $not_applied && ($required || $optional);
  }

  /**
   * Helper function to generate the title according to the settings.
   *
   * @param string $pattern
   *   Node title pattern. May contain tokens.
   * @param \Drupal\node\Entity\Node $node
   *   Node object.
   *
   * @return string
   *   A title string
   */
  protected function generateTitle($pattern, $node) {
    // Replace tokens.
    $output = $this->token
        ->replace($pattern, array('node' => $node), array(
        'sanitize' => FALSE,
        'clear' => TRUE
      ));

    // Evalute PHP.
    if ($this->getConfig($node->getType())->get('php')) {
      $output = $this->evalTitle($output, $node);
    }
    // Strip tags.
    $output = preg_replace('/[\t\n\r\0\x0B]/', '', strip_tags($output));

    return $output;
  }

  /**
   * Returns the auto node title configuration of a content type.
   *
   * @param string $type
   *   Content type for which to get the configuration. 
   *
   * @return \Drupal\Core\Config\ImmutableConfig
   */
  protected function getConfig($type) {
    if (!isset($this->config[$type])) {
      $this->config[$type] = $this->configFactory->get('auto_nodetitle.node.' . $type);
    }

    return $this->config[$type];
  }

  /**
   * Evaluates php code and passes $node to it.
   *
   * @param $code
   *   PHP code.
   * @param \Drupal\node\Entity\Node $node
   *   Node object.
   *
   * @return string
   *   String to use as node title.
   */
  public static function evalTitle($code, $node) {
    ob_start();
    print eval('?>' . $code);
    $output = ob_get_contents();
    ob_end_clean();

    return $output;
  }

}
