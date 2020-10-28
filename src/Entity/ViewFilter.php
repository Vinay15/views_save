<?php

namespace Drupal\views_save\Entity;

use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityChangedTrait;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\Session\AccountInterface;
use Drupal\views\Plugin\views\display\DisplayPluginBase;

/**
 * Defines the view filter configuration entity.
 *
 * @ContentEntityType(
 *   id = "views_save",
 *   base_table = "views_save",
 *   label = @Translation("View filter"),
 *   label_collection = @Translation("Views Filters"),
 *   label_singular = @Translation("view filter"),
 *   label_plural = @Translation("view filters"),
 *   label_count = @PluralTranslation(
 *     singular = "@count views filter",
 *     plural = "@count views filters"
 *   ),
 *   handlers = {
 *     "form" = {
 *       "add" = "Drupal\views_save\Form\ViewFilterForm",
 *       "edit" = "Drupal\views_save\Form\ViewFilterForm",
 *       "delete" = "Drupal\views_save\Form\ViewFilterDeleteForm",
 *     },
 *     "access" = "Drupal\views_save\AccessControl\ViewFilterAccessControl",
 *     "storage" = "Drupal\views_save\EntityStorage\ViewFilterEntityStorage",
 *     "list_builder" = "Drupal\views_save\ListBuilder\ViewFilterListBuilder",
 *   },
 *   links = {
 *     "canonical" = "/admin/structure/views-filters",
 *     "add-page" = "/admin/structure/views-filters/new",
 *     "add-form" = "/admin/structure/views-filters/{view}/{display_id}/add",
 *     "edit-form" = "/admin/structure/views-filters/{view}/{display_id}/{views_save}/edit",
 *     "delete-form" = "/admin/structure/views-filters/{view}/{display_id}/{views_save}/delete",
 *   },
 *   entity_keys = {
 *     "id" = "id",
 *     "label" = "label",
 *   },
 * )
 */
class ViewFilter extends ContentEntityBase implements ViewFilterInterface {

  use EntityChangedTrait;

  /**
   * The ID of an entity.
   *
   * @var string
   *
   * @Required
   */
  protected $id;
  /**
   * The label of an entity.
   *
   * @var string
   *
   * @Required
   */
  protected $label;
  /**
   * The UUID of a user this entity belongs to.
   *
   * @var string
   *
   * @Required
   */
  protected $user;
  /**
   * The md5 hash-sum of the filters.
   *
   * @var string
   *
   * @Required
   */
  protected $hash;
  /**
   * The name of a Drupal view.
   *
   * @var string
   *
   * @Required
   */
  protected $view;
  /**
   * The ID of a view's display.
   *
   * @var string
   *
   * @Required
   */
  protected $display;
  /**
   * The associative array of saved filters.
   *
   * @var array
   *
   * @Required
   */
  protected $filters = [];
  /**
   * The view entity.
   *
   * @var \Drupal\views\ViewEntityInterface
   */
  protected $viewEntity;
  /**
   * The view executable.
   *
   * @var \Drupal\views\ViewExecutable
   */
  protected $viewExecutable;

  /**
   * {@inheritdoc}
   */
  public function getUser() {
    return $this->user;
  }

  /**
   * {@inheritdoc}
   */
  public function setUser($uuid) {
    $this->set('user', $this->user = $uuid);
  }

  /**
   * {@inheritdoc}
   */
  public function getFilters() {
    if (is_string($this->filters)) {
      $this->filters = unserialize($this->filters);
    }

    return $this->filters;
  }

  /**
   * {@inheritdoc}
   */
  public function setFilters(array $filters) {
    $this->set('filters', $this->filters = $filters);
  }

  /**
   * {@inheritdoc}
   */
  public function getViewId() {
    return $this->view;
  }

  /**
   * {@inheritdoc}
   */
  public function setView($view) {
    $this->set('view', $this->view = $view);
  }

  /**
   * {@inheritdoc}
   */
  public function getViewDisplayId() {
    return $this->display;
  }

  /**
   * {@inheritdoc}
   */
  public function setViewDisplayId($display_id) {
    $this->set('display', $this->display = $display_id);
  }

  /**
   * {@inheritdoc}
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function isFilterExists(array $filters) {
    return $this->hasItems([
      'hash' => $this->getFiltersHash($filters),
    ]);
  }

  /**
   * {@inheritdoc}
   */
  public function save() {
    foreach (['label', 'view', 'display', 'filters'] as $property) {
      if ($this->get($property)->isEmpty()) {
        throw new \LogicException(sprintf('The "%s" property cannot be empty.', $property));
      }
    }

    $this->set('hash', $this->getFiltersHash($this->filters));

    return parent::save();
  }

  /**
   * {@inheritdoc}
   */
  public function getView() {
    if ($this->viewEntity === NULL) {
      $this->viewEntity = $this
        ->entityTypeManager()
        ->getStorage('view')
        ->load($this->view);
    }

    return $this->viewEntity;
  }

  /**
   * {@inheritdoc}
   */
  public function getViewExecutable() {
    if ($this->viewExecutable === NULL) {
      $this->viewExecutable = $this
        ->getView()
        ->getExecutable();

      $this->viewExecutable->setDisplay($this->display);
    }

    return $this->viewExecutable;
  }

  /**
   * {@inheritdoc}
   */
  public function getFiltersUrl() {
    $url = $this
      ->getViewExecutable()
      ->getUrl()
      ->setOption('query', $this->getFilters())
      ->setAbsolute();
    \Drupal::moduleHandler()->alter('views_save_url', $url, $this);
    return $url;
  }

  /**
   * {@inheritdoc}
   */
  protected function urlRouteParameters($rel) {
    $parameters = parent::urlRouteParameters($rel);

    $parameters['view'] = $this->view;
    $parameters['display_id'] = $this->display;

    return $parameters;
  }

  /**
   * Returns a personalized ID of a filter.
   *
   * @param array $filters
   *   The list of filter parameters.
   *
   * @return string
   *   The ID of a filter.
   */
  protected function getFiltersHash(array $filters) {
    if (empty($this->user) || empty($this->view) || empty($this->display)) {
      throw new \LogicException(sprintf('The view name, ID of its display and UUID of a user must be set.'));
    }

    \Drupal::moduleHandler()->alter('views_save_hash', $filters, $this);
    ksort($filters);
    array_walk($filters, function(&$value) {
      if (is_array($value)) {
        sort($value);
      }
    });

    return md5(serialize($filters) . $this->user . $this->view . $this->display);
  }

  /**
   * Returns a state whether entities available for given conditions.
   *
   * @param array $conditions
   *   The list of conditions for the query.
   *
   * @return bool
   *   A state whether entities available for given conditions.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  protected function hasItems(array $conditions) {
    if (empty($this->user)) {
      throw new \LogicException('The UUID of a user, the filter belongs to, must be set.');
    }

    $query = $this
      ->entityTypeManager()
      ->getStorage($this->entityTypeId)
      ->getQuery()
      ->count();

    $conditions['user'] = $this->user;

    foreach ($conditions as $field => $value) {
      $query->condition($field, $value);
    }

    return (bool) $query->execute();
  }

  /**
   * {@inheritdoc}
   */
  public static function getViewDisplayFilters(DisplayPluginBase $display) {
    /* @var \Drupal\views\Plugin\views\field\FieldPluginBase[] $filters */
    $filters = $display->getHandlers('filter');

    foreach ($filters as $internal_id => $filter) {
      // A GET parameter in a browser is an identifier - renamed ID of a field.
      if (!empty($filter->options['expose']['identifier']) && $internal_id !== $filter->options['expose']['identifier']) {
        $filters[$filter->options['expose']['identifier']] = $filter;
        unset($filters[$internal_id]);
      }
    }

    return $filters;
  }

  /**
   * {@inheritdoc}
   */
  public static function isApplicable(DisplayPluginBase $display, AccountInterface $account) {
    return
      // We do not support exposed forms in a block.
      empty($display->options['exposed_block']) &&
      // A user must have permissions.
      $account->hasPermission('use own views filters') &&
      (
        // The presence of filters says the display has an exposed form.
        !empty($display->display['display_options']['filters']) ||
        // The filters are inherited.
        $display->isDefaulted('filters')
      );
  }

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type) {
    $fields = parent::baseFieldDefinitions($entity_type);

    $fields[$entity_type->getKey('label')] = BaseFieldDefinition::create('string')
      ->setLabel('Label')
      ->setDescription('The label of a filter.')
      ->setRequired(TRUE)
      ->setSetting('max_length', 255);

    $fields['user'] = BaseFieldDefinition::create('string')
      ->setLabel('User UUID')
      ->setDescription('The UUID of a user the filter created for.')
      ->setReadOnly(TRUE)
      ->setSetting('max_length', 36);

    $fields['hash'] = BaseFieldDefinition::create('string')
      ->setLabel('Filter hash')
      ->setDescription('The md5 hash-sum of the filters.')
      ->setRequired(TRUE)
      ->setSetting('max_length', 32)
      ->setSetting('text_processing', FALSE);

    $fields['view'] = BaseFieldDefinition::create('string')
      ->setLabel('View name')
      ->setDescription('The name of a Drupal view.')
      ->setRequired(TRUE);

    $fields['display'] = BaseFieldDefinition::create('string')
      ->setLabel('View display')
      ->setDescription('The ID of a view display.')
      ->setRequired(TRUE);

    $fields['filters'] = BaseFieldDefinition::create('map')
      ->setLabel('Filters')
      ->setDescription('The list of a view filters.')
      ->setRequired(TRUE);

    return $fields;
  }

}
