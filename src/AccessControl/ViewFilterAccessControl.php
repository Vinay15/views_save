<?php

namespace Drupal\views_save\AccessControl;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Entity\EntityAccessControlHandler;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Session\AccountInterface;

/**
 * {@inheritdoc}
 */
class ViewFilterAccessControl extends EntityAccessControlHandler {

  /**
   * An instance of the "MODULE.entity_id_uuid_exchanger" service.
   *
   * @var \Drupal\views_save\EntityIdForUuidExchanger
   */
  protected $entityIdForUuidExchanger;

  /**
   * {@inheritdoc}
   */
  public function __construct(EntityTypeInterface $entity_type) {
    parent::__construct($entity_type);

    $this->entityIdForUuidExchanger = \Drupal::service('views_save.entity_id_uuid_exchanger');
  }

  /**
   * {@inheritdoc}
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function access(EntityInterface $entity, $operation, AccountInterface $account = NULL, $return_as_object = FALSE) {
    /* @var \Drupal\views_save\Entity\ViewFilterInterface $entity */
    $account = $this->prepareUser($account);

    if (
      $account->hasPermission('administer all views filters') ||
      (
        $entity->getUser() === $this->entityIdForUuidExchanger->exchange('user', $account->id()) &&
        $account->hasPermission('use own views filters')
      )
    ) {
      return $return_as_object ? AccessResult::allowed() : TRUE;
    }

    return $return_as_object ? AccessResult::forbidden() : FALSE;
  }

}
