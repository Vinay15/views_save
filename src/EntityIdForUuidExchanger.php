<?php

namespace Drupal\views_save;

use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;

/**
 * The service to exchange an ID of an entity for its UUID.
 */
class EntityIdForUuidExchanger {

  /**
   * An instance of the "database" service.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $connection;
  /**
   * An instance of the "entity_type.manager" service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * {@inheritdoc}
   */
  public function __construct(
    Connection $connection,
    EntityTypeManagerInterface $entity_type_manager
  ) {
    $this->connection = $connection;
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * Returns the UUID exchanged for the entity's ID.
   *
   * @param \Drupal\Core\Entity\EntityTypeInterface|string $entity_type
   *   The type of an entity.
   * @param string|int $entity_id
   *   The ID of an entity.
   *
   * @return string|false
   *   The UUID of an entity or FALSE if it doesn't exist.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function exchange($entity_type, $entity_id) {
    if (is_string($entity_type)) {
      $entity_type = $this->entityTypeManager->getDefinition($entity_type);
    }

    $uuid_key = $entity_type->getKey('uuid');

    if ($uuid_key === FALSE) {
      throw new \LogicException('The entity does not have the UUID.');
    }

    return (string) $this->connection
      ->select($entity_type->getBaseTable(), 'base')
      ->fields('base', [$uuid_key])
      ->condition($entity_type->getKey('id'), $entity_id)
      ->execute()
      ->fetchField();
  }

}
