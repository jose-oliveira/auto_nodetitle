<?php

/**
 * @file
 * Contains \Drupal\auto_nodetitle\EntityDecorator.
 */

namespace Drupal\auto_nodetitle;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Utility\Token;

/**
 * Provides an content entity decorator for automatic title generation.
 */
class EntityDecorator implements EntityDecoratorInterface {

  /**
   * The content entity that is decorated.
   *
   * @var ContentEntityInterface
   */
  protected $entity;

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
   * Auto title configuration for the entity.
   *
   * @var array
   */
  protected $config;

  /**
   * Constructs an EntityDecorator object.
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
   * {@inheritdoc}
   */
  public function decorate(ContentEntityInterface $entity) {
    $this->entity = new AutoTitle($entity, $this->configFactory, $this->entityTypeManager, $this->token);
    return $this->entity;
  }
}
