<?php

namespace Drupal\views_save\EntityStorage;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityManagerInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\Sql\SqlContentEntityStorage;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\views_save\Entity\ViewFilterInterface;
use Drupal\views_save\EntityIdForUuidExchanger;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * {@inheritdoc}
 */
class ViewFilterEntityStorage extends SqlContentEntityStorage {

  /**
   * An instance of the "current_user" service.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  protected $currentUser;
  /**
   * An instance of the "MODULE.entity_id_uuid_exchanger" service.
   *
   * @var \Drupal\views_save\EntityIdForUuidExchanger
   */
  protected $entityIdForUuidExchanger;

  /**
   * {@inheritdoc}
   */
  public function __construct(
    EntityTypeInterface $entity_type,
    Connection $database,
    EntityManagerInterface $entity_manager,
    CacheBackendInterface $cache,
    LanguageManagerInterface $language_manager,
    AccountProxyInterface $current_user,
    EntityIdForUuidExchanger $entity_id_for_uuid_exchanger
  ) {
    parent::__construct($entity_type, $database, $entity_manager, $cache, $language_manager);

    $this->currentUser = $current_user;
    $this->entityIdForUuidExchanger = $entity_id_for_uuid_exchanger;
  }

  /**
   * {@inheritdoc}
   */
  public static function createInstance(ContainerInterface $container, EntityTypeInterface $entity_type) {
    return new static(
      $entity_type,
      $container->get('database'),
      $container->get('entity.manager'),
      $container->get('cache.entity'),
      $container->get('language_manager'),
      $container->get('current_user'),
      $container->get('views_save.entity_id_uuid_exchanger')
    );
  }

  /**
   * {@inheritdoc}
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  protected function buildQuery($ids, $revision_ids = FALSE) {
    return parent
      ::buildQuery($ids, $revision_ids)
        ->condition('user', $this->entityIdForUuidExchanger->exchange('user', $this->currentUser->id()));
  }

  /**
   * {@inheritdoc}
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function doPostSave(EntityInterface $entity, $update) {
    parent::doPostSave($entity, $update);
    /* @var \Drupal\views_save\Entity\ViewFilterInterface $entity */
    static::invalidateViewDisplayCache($entity);
  }

  /**
   * {@inheritdoc}
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function delete(array $entities) {
    parent::delete($entities);

    foreach ($entities as $entity) {
      /* @var \Drupal\views_save\Entity\ViewFilterInterface $entity */
      static::invalidateViewDisplayCache($entity);
    }
  }

  /**
   * Invalidates the cache of a view display.
   *
   * @param \Drupal\views_save\Entity\ViewFilterInterface $view_filter
   *   The entity to handle.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  protected static function invalidateViewDisplayCache(ViewFilterInterface $view_filter) {
    /* @var \Drupal\views\Plugin\views\cache\CachePluginBase $cache_plugin */
    $cache_plugin = $view_filter->getViewExecutable()
      ->getDisplay()
      ->getPlugin('cache');
    if ($cache_plugin) {
      $cache_plugin->cacheFlush();
    }
  }

}
