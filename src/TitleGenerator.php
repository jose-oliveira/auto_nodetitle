<?php

/**
 * @file
 * Contains \Drupal\auto_nodetitle\TitleGenerator.
 */

namespace Drupal\auto_nodetitle;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Utility\Token;

/**
 * Provides the title generation functionality.
 */
class TitleGenerator {

  /**
   * @todo
   */
  const DISABLED = 0;
  const ENABLED = 1;
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
   * Constructs an AutoNodeTitle object.
   *
   */
  public function __construct(ConfigFactoryInterface $config_factory, \Drupal\Core\Entity\EntityTypeManager $entity_type_manager, Token $token) {
    $this->configFactory = $config_factory;
    $this->entityTypeManager = $entity_type_manager;
    $this->token = $token;
  }

  /**
   * Sets the automatically generated nodetitle for the node
   *
   * @param \Drupal\node\Entity\Node $node
   *
   * @return string
   */
  public function setTitle($node) {

    /** @var  $types */
    $types = $this->entityTypeManager->getStorage('node_type')->loadMultiple();
    $type = $node->getType();
    $title = $node->getTitle();
    $pattern = $this->configFactory
      ->get('auto_nodetitle.node.' . $type)
      ->get('pattern') ?: '';
    if (trim($pattern)) {
      $node->changed = REQUEST_TIME;
      $title = $this->generateTitle($pattern, $node);
    }
    elseif ($node->nid) {
      $title = t('@type @node-id', array(
        '@type' => $types[$type]->get('name'),
        '@node-id' => $node->id(),
      ));
    }
    else {
      $title = t('@type', array('@type' => $types[$type]->get('name')));
    }
    // Ensure the generated title isn't too long.
    $title = substr($title, 0, 255);
    $node->set('title', $title);
    // @todo This sets a public property. This is no good architecture.
    // With that flag we ensure we don't apply the title two times to the same
    // node. See auto_nodetitle_is_needed().
    $node->auto_nodetitle_applied = TRUE;

    return $title;
  }

  public function hasAutoTitle($type) {
    return $this->getTitleStatus($type) == self::ENABLED;
  }

  public function hasOptionalAutoTitle($type) {
    return $this->getTitleStatus($type) == self::OPTIONAL;
  }

  /**
   * Returns whether the auto nodetitle has to be set.
   *
   * @param $node
   *
   * @return bool
   */
  public function auto_nodetitle_is_needed($node) {
    $not_applied = empty($node->auto_nodetitle_applied);
    $setting = $this->getTitleStatus($node->getType());
    $title = $node->getTitle();
    $check_optional = $setting && !($setting == self::OPTIONAL && !empty($title));
    return $not_applied && $check_optional;
  }

  /**
   * Helper function to generate the title according to the settings.
   *
   * @param $pattern
   * @param \Drupal\node\Entity\Node $node
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
    if ($this->configFactory->get('auto_nodetitle.node.' . $node->getType())->get('php')) {
      $output = $this->evalTitle($output, $node);
    }
    // Strip tags.
    $output = preg_replace('/[\t\n\r\0\x0B]/', '', strip_tags($output));
    return $output;
  }

  /**
   * Gets the auto node title setting associated with the given content type.
   *
   * @param $type
   *
   * @return int
   */
  public function getTitleStatus($type) {
    // @todo Store type setting in property?
    return $this->configFactory
      ->get('auto_nodetitle.node.' . $type)
      ->get('status') ?: self::DISABLED;
  }

  /**
   * Evaluates php code and passes $node to it.
   *
   * @param $code
   * @param \Drupal\node\Entity\Node $node
   *
   * @return mixed
   */
  public static function evalTitle($code, $node) {
    ob_start();
    print eval('?>' . $code);
    $output = ob_get_contents();
    ob_end_clean();
    return $output;
  }

}
