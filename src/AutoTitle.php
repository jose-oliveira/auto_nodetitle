<?php

/**
 * @file
 * Contains \Drupal\auto_nodetitle\AutoTitle.
 */

namespace Drupal\auto_nodetitle;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Utility\Token;

/**
 * Provides the automatic title generation.
 */
class AutoTitle implements AutoTitleInterface {
  use StringTranslationTrait;

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
   * The content entity.
   *
   * @var ContentEntityInterface
   */
  protected $entity;

  /**
   * The type of the entity.
   *
   * @var string
   */
  protected $entity_type;

  /**
   * The bundle of the entity.
   *
   * @var string
   */
  protected $entity_bundle;

  /**
   * Indicates if the automatic title has been applied.
   *
   * @var bool
   */
  protected $auto_title_applied = FALSE;

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
   * Auto title configuration.
   *
   * @var \Drupal\Core\Config\ImmutableConfig
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
  public function __construct(ContentEntityInterface $entity, ConfigFactoryInterface $config_factory, \Drupal\Core\Entity\EntityTypeManager $entity_type_manager, Token $token) {
    $this->entity = $entity;
    $this->entity_type = $entity->getEntityType()->id();
    $this->entity_bundle = $entity->bundle();

    $this->configFactory = $config_factory;
    $this->entityTypeManager = $entity_type_manager;
    $this->token = $token;
  }

  /**
   * Checks if the entity has a title.
   *
   * @return bool
   *   True if the entity has a title; i.e. a label property.
   */
  public function hasTitle() {
    /** @var \Drupal\Core\Entity\EntityTypeInterface $definition */
    $definition = $this->entityTypeManager->getDefinition($this->entity->getEntityTypeId());
    return $definition->hasKey('label');
  }

  /**
   * {@inheritdoc}
   */
  public function setTitle() {

    if (!$this->hasTitle()) {
      throw new \Exception('This entity has no title.');
    }

    $pattern = $this->getConfig('pattern') ?: '';
    $pattern = trim($pattern);

    if ($pattern) {
      $title = $this->generateTitle($pattern, $this->entity);
    }
    else {
      $title = $this->getAlternativeTitle();
    }

    $title = substr($title, 0, 255);
    $title_name = $this->getTitleName();
    $this->entity->$title_name->setValue($title);

    $this->auto_title_applied = TRUE;
    return $title;
  }

  /**
   * {@inheritdoc}
   */
  public function hasAutoTitle() {
    return $this->getConfig('status') == self::ENABLED;
  }

  /**
   * {@inheritdoc}
   */
  public function hasOptionalAutoTitle() {
    return $this->getConfig('status') == self::OPTIONAL;
  }

  /**
   * {@inheritdoc}
   */
  public function autoTitleNeeded() {
    $not_applied = empty($this->auto_title_applied);
    $required = $this->hasAutoTitle();
    $optional = $this->hasOptionalAutoTitle() && empty($this->entity->label());
    return $not_applied && ($required || $optional);
  }

  /**
   * Gets the field name of the entity title (label).
   *
   * @return string
   *   The entity title field name. Empty if the entity has no title.
   */
  protected function getTitleName() {
    $label_field = '';

    if ($this->hasTitle()) {
      $definition = $this->entityTypeManager->getDefinition($this->entity->getEntityTypeId());
      $label_field = $definition->getKey('label');
    }

    return $label_field;
  }

  /**
   * Gets the entity bundle label or the entity label.
   *
   * @return string
   *   The bundle label.
   */
  protected function getBundleLabel() {
    $entity_type = $this->entity->getEntityTypeId();
    $bundle = $this->entity->bundle();

    // Use the the human readable name of the bundle type. If this entity has no
    // bundle, we use the name of the content entity type.
    if ($bundle != $entity_type) {
      $bundle_entity_type = $this->entityTypeManager
        ->getDefinition($entity_type)
        ->getBundleEntityType();
      $label = $this->entityTypeManager
        ->getStorage($bundle_entity_type)
        ->load($bundle)
        ->label();
    }
    else {
      $label = $this->entityTypeManager
        ->getDefinition($entity_type)
        ->getLabel();
    }

    return $label;
  }

  /**
   * Generates the title according to the settings.
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
    $entity_type = $entity->getEntityType()->id();
    $output = $this->token
        ->replace($pattern, array($entity_type => $entity), array(
        'sanitize' => FALSE,
        'clear' => TRUE
      ));

    // Evaluate PHP.
    if ($this->getConfig('php')) {
      $output = $this->evalTitle($output, $this->entity);
    }
    // Strip tags.
    $output = preg_replace('/[\t\n\r\0\x0B]/', '', strip_tags($output));

    return $output;
  }

  /**
   * Returns automatic title configuration of the content entity bundle.
   *
   * @param string $value
   *   The configuration value to get.
   *
   * @return \Drupal\Core\Config\ImmutableConfig
   */
  protected function getConfig($value) {
    if (!isset($this->config)) {
      $key = $this->entity_type . '.' . $this->entity_bundle;
      $this->config = $this->configFactory->get('auto_nodetitle.' . $key);
    }

    return $this->config->get($value);
  }

  /**
   * Gets an alternative entity title.
   *
   * @return string
   *   Translated title string.
   */
  protected function getAlternativeTitle() {
    $content_type = $this->getBundleLabel();

    if ($this->entity->id()) {
      $title = $this->t('@type @id', array(
        '@type' => $content_type,
        '@id' => $this->entity->id(),
      ));
    }
    else {
      $title = $content_type;
    }

    return $title;
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
  protected function evalTitle($code, $entity) {
    ob_start();
    print eval('?>' . $code);
    $output = ob_get_contents();
    ob_end_clean();

    return $output;
  }

}
