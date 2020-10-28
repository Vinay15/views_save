<?php

namespace Drupal\views_save\ListBuilder;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityListBuilder;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * {@inheritdoc}
 */
class ViewFilterListBuilder extends EntityListBuilder {

  /**
   * A storage of the "user" entities.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected $userStorage;

  /**
   * {@inheritdoc}
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function __construct(
    EntityTypeInterface $entity_type,
    EntityTypeManagerInterface $entity_type_manager
  ) {
    parent::__construct($entity_type, $entity_type_manager->getStorage($entity_type->id()));

    $this->userStorage = $entity_type_manager->getStorage('user');
  }

  /**
   * {@inheritdoc}
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public static function createInstance(ContainerInterface $container, EntityTypeInterface $entity_type) {
    return new static(
      $entity_type,
      $container->get('entity_type.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function buildHeader() {
    $header = [];

    $header['label'] = $this->t('Name');
    $header['user'] = $this->t('User');

    return $header + parent::buildHeader();
  }

  /**
   * {@inheritdoc}
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\Core\Entity\EntityMalformedException
   */
  public function buildRow(EntityInterface $entity) {
    /* @var \Drupal\views_save\Entity\ViewFilterInterface $entity */
    /* @var \Drupal\user\UserInterface[] $accounts */
    $accounts = $this->userStorage->loadByProperties(['uuid' => $entity->getUser()]);
    $account = reset($accounts);
    $row = [];

    $row['label'] = [
      'data' => [
        '#type' => 'link',
        '#title' => $entity->label(),
        '#url' => $entity->getFiltersUrl(),
        '#attributes' => [
          'target' => '_blank',
        ],
      ],
    ];

    $row['user'] = [
      'data' => [
        '#type' => 'link',
        '#title' => $account->getAccountName(),
        '#url' => $account->toUrl(),
      ],
    ];

    return $row + parent::buildRow($entity);
  }

}
