<?php

namespace Drupal\views_save\Form;

use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\RedirectCommand;
use Drupal\Core\Entity\EntityForm;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Element;
use Drupal\Core\Routing\UrlGeneratorInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\views\ViewEntityInterface;
use Drupal\views_save\ViewFiltersMarkup;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * The form to manage exposed filters presets.
 *
 * @see \Drupal\views_save\Entity\ViewFilter
 *
 * @property \Drupal\views_save\Entity\ViewFilterInterface $entity
 */
class ViewFilterForm extends EntityForm {

  /**
   * The view.
   *
   * @var \Drupal\views\ViewEntityInterface
   */
  protected $view;
  /**
   * The ID of a view.
   *
   * @var string
   */
  protected $viewId;
  /**
   * The ID of a display.
   *
   * @var string
   */
  protected $displayId;
  /**
   * The indicator of whether the exposed form has elements.
   *
   * @var bool
   */
  protected $isEmpty;
  /**
   * An instance of the "current_user" service.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  protected $currentUser;
  /**
   * An instance of the "MODULE.view_filters_markup" service.
   *
   * @var \Drupal\views_save\ViewFiltersMarkup
   */
  protected $viewFiltersMarkup;

  /**
   * {@inheritdoc}
   */
  public function __construct(
    UrlGeneratorInterface $url_generator,
    AccountProxyInterface $current_user,
    RequestStack $request_stack,
    EntityTypeManagerInterface $entity_type_manager,
    ViewFiltersMarkup $view_filters_markup
  ) {
    $this->urlGenerator = $url_generator;
    $this->currentUser = $current_user;
    $this->requestStack = $request_stack;
    $this->entityTypeManager = $entity_type_manager;
    $this->viewFiltersMarkup = $view_filters_markup->setCaller(static::class);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('url_generator'),
      $container->get('current_user'),
      $container->get('request_stack'),
      $container->get('entity_type.manager'),
      $container->get('views_save.view_filters_markup')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'view_filter';
  }

  /**
   * {@inheritdoc}
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function buildForm(array $form, FormStateInterface $form_state, ViewEntityInterface $view = NULL, $display_id = NULL, $in_modal = NULL, array $input = NULL) {
    if ($view === NULL || $display_id === NULL) {
      throw new \RuntimeException('The actual view object and its display ID must be passed.');
    }

    if ($in_modal !== NULL) {
      $this->viewFiltersMarkup->setAjaxResponse($in_modal);
    }

    if ($input !== NULL) {
      $form_state->setUserInput($input);
    }

    $this->view = $view;
    $this->viewId = $this->view->id();
    $this->displayId = $display_id;

    $form['#attributes']['id'] = $this->getFormId();

    $this->entity->setView($this->viewId);
    $this->entity->setViewDisplayId($this->displayId);
    $this->entity->setUser(
      $this->entityTypeManager
        ->getStorage('user')
        ->load($this->currentUser->id())
        ->uuid()
    );

    $form['#action'] = $this->urlGenerator->generate("entity.views_save_filter.{$this->operation}_form", [
      'display_id' => $this->displayId,
      $this->view->getEntityTypeId() => $this->viewId,
      $this->entity->getEntityTypeId() => $this->entity->id(),
    ]);

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function form(array $form, FormStateInterface $form_state) {
    $executable = $this->entity->getViewExecutable();
    $display = $executable->getDisplay();
    $filters = $this->entity->getViewDisplayFilters($display);

    if ($this->entity->isNew()) {
      $user_input = array_intersect_key($form_state->getUserInput(), $filters);
      $is_new = TRUE;
    }
    else {
      $user_input = $this->entity->getFilters();
      $is_new = FALSE;

      $form['title'] = [
        '#tag' => 'h2',
        '#type' => 'html_tag',
        '#value' => $this->t('Filter for the @view (@display)', [
          '@view' => $this->view->label(),
          '@display' => $display->display['display_title'],
        ]),
      ];
    }

    $form_state->setTemporaryValue('filters', array_keys($filters));
    // Update filters immediately to reflect the configuration when
    // generating a URL.
    $this->entity->setFilters($user_input);
    $executable->setExposedInput($user_input);
    $executable->build();

    /* @var array[] $elements */
    $elements = (array) $executable->exposed_widgets;

    unset(
      $elements['reset'],
      $elements['submit'],
      $elements['actions'],
      $elements['form_id'],
      $elements['form_token'],
      $elements['form_build_id']
    );

    $this->isEmpty = empty($elements);

    $form['label'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Label'),
      '#access' => !$this->isEmpty,
      '#required' => TRUE,
      '#maxlength' => 255,
      '#default_value' => $this->entity->label(),
      '#description' => $is_new ? '' : $this->t('Check this filter <a href=":url" target="_blank">in action</a> (opens in a new window/tab).', [
        ':url' => $this->entity->getFiltersUrl()->toString(),
      ]),
    ];

    $form['filters'] = [
      // It's not possible to make it "#tree => TRUE" since the elements
      // are gotten from already completed from, so their "#parents" and
      // "#array_parents" already in place and will infringe forming the
      // nested tree.
      '#type' => 'container',
      '#attributes' => [
        // @todo Work further on this.
        'hidden' => $this->viewFiltersMarkup->isAjaxResponse(),
      ],
    ];

    foreach (Element::children($elements) as $name) {
      $form['filters'][$name] = $elements[$name];
    }

    foreach ([
      'view' => $this->viewId,
      'display' => $this->displayId,
    ] as $name => $value) {
      $form[$name] = [
        '#type' => 'value',
        '#value' => $value,
      ];
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function actions(array $form, FormStateInterface $form_state) {
    $actions = parent::actions($form, $form_state);

    $actions['submit']['#ajax'] = [
      'wrapper' => $this->getFormId(),
    ];

    if ($this->isEmpty) {
      $actions['submit']['#value'] = $this->t('The display has no exposed widgets, therefore a preset cannot be configured.');
      $actions['submit']['#disabled'] = TRUE;
    }

    return $actions;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    parent::validateForm($form, $form_state);

    $filters = [];
    $user_input = $form_state->getUserInput();

    // Form the nested structure manually.
    foreach ($form_state->getTemporaryValue('filters') as $key) {
      if (isset($user_input[$key])) {
        $filters[$key] = $user_input[$key];
      }

      $form_state->unsetValue($key);
    }

    try {
      if ($this->entity->isNew() && $this->entity->isFilterExists($filters)) {
        throw new \RuntimeException($this->t('A filter with those parameters already exists.'));
      }

      $form_state->setValue('filters', $filters);
    }
    catch (\Exception $e) {
      $form_state->setError($form['label'], $e->getMessage());
    }
  }

  public function submitForm(array &$form, FormStateInterface $form_state) {
    parent::submitForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\Core\Entity\EntityMalformedException
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function save(array $form, FormStateInterface $form_state) {
    $this->entity->save();

    $response = new AjaxResponse();

    /* @see \Drupal\views_save\Form\ViewFilterSelectForm::buildForm() */
    if ($this->viewFiltersMarkup->isAjaxResponse()) {
      $this->viewFiltersMarkup->attachMarkup($response, $this->viewId, $this->displayId, $this->t('The filter has been saved.'));
    }
    else {
      $response->addCommand(new RedirectCommand($this->entity->toUrl('canonical')->toString()));
    }

    $form_state->setResponse($response);
  }

}
