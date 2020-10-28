<?php

namespace Drupal\views_save\Entity;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\views\Plugin\views\display\DisplayPluginBase;

/**
 * The description of the "view_filter" entity.
 */
interface ViewFilterInterface extends ContentEntityInterface {

  /**
   * Sets a UUID of a user the filter belongs to.
   *
   * @param string $uuid
   *   A UUID of a user.
   */
  public function setUser($uuid);

  /**
   * {@inheritdoc}
   */
  public function getUser();

  /**
   * Sets a list of filters.
   *
   * @param array $filters
   *   A list of filters.
   */
  public function setFilters(array $filters);

  /**
   * {@inheritdoc}
   */
  public function getFilters();

  /**
   * Sets an ID of a Drupal view.
   *
   * @param string $view
   *   An ID of a view.
   */
  public function setView($view);

  /**
   * {@inheritdoc}
   */
  public function getViewId();

  /**
   * Sets an ID of a view's display.
   *
   * @param string $display_id
   *   An ID of a display.
   */
  public function setViewDisplayId($display_id);

  /**
   * {@inheritdoc}
   */
  public function getViewDisplayId();

  /**
   * Checks whether the configured preset of filters already exist for a user.
   *
   * @param array $filters
   *   A list of filters.
   *
   * @return bool
   *   A state of the check.
   */
  public function isFilterExists(array $filters);

  /**
   * Returns an instance of the "view" entity.
   *
   * @return \Drupal\views\ViewEntityInterface|null
   *   An instance of the "view" entity.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function getView();

  /**
   * Returns an executable view.
   *
   * @return \Drupal\views\ViewExecutable
   *   An executable view.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function getViewExecutable();

  /**
   * Returns an absolute URL to a page with a view with applied filters.
   *
   * @return \Drupal\Core\Url
   *   The URL object.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function getFiltersUrl();

  /**
   * Returns the list of filters for a given view display.
   *
   * @param \Drupal\views\Plugin\views\display\DisplayPluginBase $display
   *   The display of an executable view.
   *
   * @return \Drupal\views\Plugin\views\field\FieldPluginBase[]
   *   The list of filters.
   */
  public static function getViewDisplayFilters(DisplayPluginBase $display);

  /**
   * Returns a state whether filters preset can be created for a given display.
   *
   * @param \Drupal\views\Plugin\views\display\DisplayPluginBase $display
   *   The display of an executable view.
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The account of a user to check whether their can use a filter.
   *
   * @return bool
   *   The state.
   */
  public static function isApplicable(DisplayPluginBase $display, AccountInterface $account);

}
