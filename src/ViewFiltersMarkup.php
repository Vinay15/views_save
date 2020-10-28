<?php

namespace Drupal\views_save;

use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\CloseModalDialogCommand;
use Drupal\Core\Ajax\InsertCommand;
use Drupal\Core\Ajax\PrependCommand;
use Drupal\Core\Ajax\RemoveCommand;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\Url;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * The service that renders the filters for a view with an exposed form.
 */
class ViewFiltersMarkup {

  /**
   * The ID for the status messages container.
   */
  const STATUS_MESSAGES_CONTAINER_ID = 'views_save_messages';

  /**
   * An instance of the "messenger" service.
   *
   * @var \Drupal\Core\Messenger\MessengerInterface
   */
  protected $messenger;
  /**
   * An instance of the "current_user" service.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $currentUser;
  /**
   * An instance of the "request_stack" service.
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  protected $requestStack;
  /**
   * An instance of the "MODULE.entity_id_uuid_exchanger" service.
   *
   * @var \Drupal\views_save\EntityIdForUuidExchanger
   */
  protected $entityIdForUuidExchanger;
  /**
   * A storage of the "views_save_filter" entities.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $viewFilterStorage;
  /**
   * The FQN of a form class that calls this service.
   *
   * @var string
   */
  protected $formClass;
  /**
   * The CSS selector of a block to prepend status messages to.
   *
   * @var string
   */
  protected $mainContentBlockCssSelector;

  /**
   * {@inheritdoc}
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function __construct(
    MessengerInterface $messenger,
    AccountProxyInterface $current_user,
    RequestStack $request_stack,
    EntityTypeManagerInterface $entity_type_manager,
    EntityIdForUuidExchanger $entity_id_for_uuid_exchanger,
    $main_content_block_css_selector
  ) {
    $this->messenger = $messenger;
    $this->currentUser = $current_user;
    $this->requestStack = $request_stack;
    $this->viewFilterStorage = $entity_type_manager->getStorage('views_save_filter');
    $this->entityIdForUuidExchanger = $entity_id_for_uuid_exchanger;
    $this->mainContentBlockCssSelector = $main_content_block_css_selector;
  }

  /**
   * Sets a FQN of a form class that calls this service.
   *
   * @param string $form_class
   *   A fully-qualified namespace of a form class that returns the markup.
   *
   * @return static
   */
  public function setCaller($form_class) {
    $this->formClass = $form_class;

    return $this;
  }

  /**
   * Sets a state whether the form should generate an AJAX response.
   *
   * @param bool $state
   *   A state.
   */
  public function setAjaxResponse($state) {
    $_SESSION[$this->formClass]['in_modal'] = $state;
  }

  /**
   * {@inheritdoc}
   */
  public function isAjaxResponse() {
    return !empty($_SESSION[$this->formClass]['in_modal']);
  }

  /**
   * Attaches rendered filters to an AJAX response.
   *
   * @param \Drupal\Core\Ajax\AjaxResponse $response
   *   The response to modify.
   * @param string $view_id
   *   The name of a Drupal view.
   * @param string $display_id
   *   The ID of a view's display.
   * @param string|\Drupal\Component\Render\MarkupInterface $message
   *   The message to display.
   *
   * @return \Drupal\Core\Ajax\AjaxResponse
   *   The inbound response.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Exception
   */
  public function attachMarkup(AjaxResponse $response, $view_id, $display_id, $message) {
    $this->viewFilterStorage->resetCache();
    $this->messenger->addStatus($message);

    $response->addCommand(new InsertCommand("#view_filters__$view_id", $this->getMarkup($view_id, $display_id)));
    $response->addCommand(new CloseModalDialogCommand());

    if ($this->isAjaxResponse()) {
      // Remove previously added messages.
      $response->addCommand(new RemoveCommand('#' . static::STATUS_MESSAGES_CONTAINER_ID));
      // Render new messages.
      $response->addCommand(new PrependCommand($this->mainContentBlockCssSelector, [
        '#prefix' => '<div id="' . static::STATUS_MESSAGES_CONTAINER_ID . '">',
        '#suffix' => '</div>',
        'status_messages' => [
          '#type' => 'status_messages',
        ],
      ]));
    }

    unset($_SESSION[$this->formClass]['in_modal']);

    return $response;
  }

  /**
   * Returns a renderable array of filters for a Drupal view.
   *
   * @param string $view_id
   *   The name of a Drupal view.
   * @param string $display_id
   *   The ID of a view's display.
   *
   * @return array
   *   A renderable array of filters.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Exception
   */
  public function getMarkup($view_id, $display_id) {
    $list = [
      '#theme' => 'views_save__filters_list',
      '#view_id' => $view_id,
      '#display_id' => $display_id,
      '#filters' => [],
      '#attached' => [
        'library' => ['core/drupal.ajax'],
      ],
    ];

    $request_query = static::getQueryString($this->requestStack->getCurrentRequest()->getRequestUri());

    foreach ($this->getFilters($view_id, $display_id) as $filter_id => $filter) {
      $url = $filter->getFiltersUrl();

      $list['#filters'][$filter_id] = [
        'url' => $url->setOption('query', $url->getOption('query') + ['hide-save-filter-button' => TRUE]),
        'title' => $filter->label(),
        'is_active' => static::getQueryString($url->toUriString()) === $request_query,
        'delete' => new Url('entity.views_save_filter.delete_form', [
          'view' => $view_id,
          'display_id' => $display_id,
          'views_save_filter' => $filter_id,
        ]),
      ];
    }

    return $list;
  }

  /**
   * Returns a list of configured filters for a Drupal view.
   *
   * @param string $view_id
   *   The name of a Drupal view.
   * @param string $display_id
   *   The ID of a view's display.
   *
   * @return \Drupal\views_save\Entity\ViewFilterInterface[]
   *   The list of filters.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  protected function getFilters($view_id, $display_id) {
    return $this->viewFilterStorage->loadByProperties([
      'user' => $this->entityIdForUuidExchanger->exchange('user', $this->currentUser->id()),
      'view' => $view_id,
      'display' => $display_id,
    ]);
  }

  /**
   * Returns a query string from the URI.
   *
   * @param string $uri
   *   The URI.
   *
   * @return string
   *   The query string.
   */
  protected static function getQueryString($uri) {
    $parts = explode('?', $uri);
    return !empty($parts[1]) ? $parts[1] : '';
  }

}
