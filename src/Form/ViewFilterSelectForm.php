<?php

namespace Drupal\views_save\Form;

use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\HtmlCommand;
use Drupal\Core\Ajax\OpenModalDialogCommand;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\HtmlEntityFormController;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Routing\RouteMatch;
use Drupal\Core\Routing\RouteProviderInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\modal_form\Element\ModalFormLink;
use Drupal\modal_form\Form\ModalFormAccessInterface;
use Drupal\views\ViewEntityInterface;
use Drupal\views_save\Entity\ViewFilter;
use Symfony\Cmf\Component\Routing\RouteObjectInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\ParameterBag;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * The form to choose the Drupal view and its display to create exposed preset.
 */
class ViewFilterSelectForm extends FormBase implements ModalFormAccessInterface {

  /**
   * The name property.
   */
  const PROP_VIEW_NAME = 'name';

  /**
   * The display property.
   */
  const PROP_VIEW_DISPLAY = 'display';

  /**
   * The route name for creating a saved view.
   */
  const SELECT_VIEW_ROUTE = 'entity.views_save_filter.add_page';

  /**
   * The route name for the saving a view.
   */
  const ENTITY_FORM_ROUTE = 'entity.views_save_filter.add_form';

  /**
   * The ID of the form container HTML element.
   */
  const ENTITY_FORM_CONTAINER_ID = 'view_filter_container';

  /**
   * An instance of the "entity_type.manager" service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;
  /**
   * An instance of the "controller.entity_form" service.
   *
   * @var \Drupal\Core\Controller\FormController
   */
  protected $formController;
  /**
   * The current request.
   *
   * @var \Symfony\Component\HttpFoundation\Request
   */
  protected $currentRequest;
  /**
   * An instance of the "router.route_provider" service.
   *
   * @var \Drupal\Core\Routing\RouteProviderInterface
   */
  protected $routeProvider;
  /**
   * An instance of the "current_user" service.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  protected $currentUser;

  /**
   * {@inheritdoc}
   */
  public function __construct(
    EntityTypeManagerInterface $entity_type_manager,
//    HtmlEntityFormController $form_controller,
    RouteProviderInterface $route_provider,
    RequestStack $request_stack,
    AccountInterface $current_user
  ) {
    $this->entityTypeManager = $entity_type_manager;
//    $this->formController = $form_controller;
    $this->currentRequest = $request_stack->getCurrentRequest();
    $this->routeProvider = $route_provider;
    $this->requestStack = $request_stack;
    $this->currentUser = $current_user;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
//      $container->get('controller.entity_form'),
      $container->get('router.route_provider'),
      $container->get('request_stack'),
      $container->get('current_user')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'view_filter_select';
  }

  /**
   * {@inheritdoc}
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form_id = $this->getFormId();
    $form_data = $this->getData($form_state);
    $view_name = $form_data->getViewName();
    $display_id = $form_data->getDisplayId();
    /* @var \Drupal\views\ViewEntityInterface[] $views_collection */
    $views_collection = $this->entityTypeManager->getStorage('view')->loadMultiple();
    $displays_list = [];
    $views_list = [];

    foreach ($views_collection as $name => $view) {
      $views_list[$name] = $view->label();
    }

    $view_name_validated = $view_name !== '' && isset($views_list[$view_name]);

    if ($view_name_validated) {
      $view = $views_collection[$view_name]->getExecutable();
      $view->initDisplay();

      /* @var \Drupal\views\Plugin\views\display\DisplayPluginBase $display */
      foreach ($view->displayHandlers as $name => $display) {
        if (ViewFilter::isApplicable($display, $this->currentUser)) {
          $options = $views_collection[$view_name]->getDisplay($name);
          $displays_list[$name] = empty($options['display_title']) ? $options['display_options']['title'] : $options['display_title'];
        }
      }

      // The display is selected.
      if (isset($displays_list[$display_id])) {
        $response = new AjaxResponse();

        switch ($this->currentRequest->attributes->get('_route')) {
          // The user has opened the page directly.
          case static::SELECT_VIEW_ROUTE:
            $args = $this->getEntityFormParameters($views_collection[$view_name], $display_id, FALSE, $form_data->getUserInput());
            list(, $entity_form) = call_user_func_array([$this, 'getEntityForm'], $args);

            $response->addCommand(new HtmlCommand('#' . static::ENTITY_FORM_CONTAINER_ID, $entity_form));

            return $response;

          // The form was requested as a modal window.
          case ModalFormLink::CONTROLLER_ROUTE:
            $args = $this->getEntityFormParameters($views_collection[$view_name], $display_id, TRUE, $form_data->getUserInput());
            list($title, $entity_form) = call_user_func_array([$this, 'getEntityForm'], $args);

            $response->addCommand(new OpenModalDialogCommand($title, $entity_form, $form_data->getDialogOptions()));
            // Ensure the library is attached.
            /* @see \Drupal\Core\Render\MainContent\ModalRenderer::renderResponse() */
            $response->addAttachments(['library' => ['core/drupal.dialog.ajax']]);
            break;

          // Almost impossible to reach, following standard scenarios.
          default:
            throw new \LogicException('Uncontrolled flow.');
        }

        // The name of a view and its display have been sent while opened
        // a form.
        $form_state->setResponse($response);
        // These two methods triggers "submitForm()" and forces
        // a response to be returned.
        $form_state->setSubmitted();
        $form_state->setProgrammed();
      }
    }

    $form['#prefix'] = "<div id='$form_id'>";
    $form['#suffix'] = '</div>';

    foreach ([
      static::PROP_VIEW_NAME => [
        '#title' => $this->t('Choose the view'),
        '#options' => $views_list,
        '#default_value' => $view_name,
      ],
      static::PROP_VIEW_DISPLAY => [
        '#title' => $this->t('Choose the display'),
        '#access' => $view_name_validated,
        '#options' => $displays_list,
        // This is needed in order to not fail into "Illegal choice" error
        // while changing the depending parent value.
        // Flow:
        // - select "name";
        // - select "display";
        // - select another "name";
        // - the previous value of "display" will remain;
        // - the error will be shown without "'#validated' => TRUE".
        /* @see \Drupal\Core\Form\FormValidator::performRequiredValidation() */
        '#validated' => TRUE,
        '#empty_value' => '',
        '#default_value' => $display_id,
      ],
    ] as $name => $info) {
      $form[$name] = $info + [
        '#type' => 'select',
        '#required' => TRUE,
        '#ajax' => [
          'wrapper' => $form_id,
          'callback' => '::rebuildForm',
        ],
      ];
    }

    $form['view_filter'] = [
      '#type' => 'container',
      '#attributes' => [
        'id' => static::ENTITY_FORM_CONTAINER_ID,
      ],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function rebuildForm(array $form, FormStateInterface $form_state) {
    $form_state->setRebuild();

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
  }

  /**
   * Returns the list of route's raw variables and parameters.
   *
   * @param \Drupal\views\ViewEntityInterface $view
   *   The selected Drupal view.
   * @param string $display_id
   *   The ID of a view's display.
   * @param bool $in_modal
   *   A state whether the form is to be opened in a modal window.
   * @param array $input
   *   The list of values to pass to the "view_filter" form (user input).
   *
   * @return array
   *   - array [0]: The "_raw_variables" of a route attributes.
   *   - array [1]: The "options.parameters" of a route.
   */
  protected function getEntityFormParameters(ViewEntityInterface $view, $display_id, $in_modal, array $input) {
    $parameters = [
      'view' => $view,
      'input' => $input,
      'in_modal' => $in_modal,
      'display_id' => $display_id,
    ];

    return [
      // The raw variables must contain scalar values only.
      ['view' => $view->id()] + $parameters,
      $parameters,
    ];
  }

  /**
   * Returns a complete entity form to the current request attributes.
   *
   * @param array $raw_variables
   *   The list of raw variables that have been converted to the parameters.
   * @param array $parameters
   *   The list of route/form parameters.
   *
   * @return array
   *   - string [0]: The title of entity form.
   *   - array [1]: The complete entity form.
   */
  protected function getEntityForm(array $raw_variables, array $parameters) {
    $request = clone $this->currentRequest;
    $route = $this->routeProvider
      ->getRouteByName(static::ENTITY_FORM_ROUTE)
      ->setOption('parameters', $parameters);

    $request->attributes->add($parameters);
    $request->attributes->set(RouteObjectInterface::ROUTE_NAME, static::ENTITY_FORM_ROUTE);
    $request->attributes->set(RouteObjectInterface::ROUTE_OBJECT, $route);
    $request->attributes->set('_raw_variables', new ParameterBag($raw_variables));

    return [
      (string) $route->getDefault('_title'),
//      $this->formController->getContentResult($request, RouteMatch::createFromRequest($request)),
      \Drupal::service('controller.entity_form')->getContentResult($request, RouteMatch::createFromRequest($request)),
    ];
  }

  /**
   * Returns the data the form being requested/rebuilt with.
   *
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The state of form.
   *
   * @return \Drupal\views_save\Form\ViewFilterFormData
   *   The data of the form.
   */
  protected function getData(FormStateInterface $form_state) {
    $input = $form_state->getUserInput();

    if (isset($input['_triggering_element_name']) && $input['_triggering_element_name'] === static::PROP_VIEW_NAME) {
      unset($input[static::PROP_VIEW_DISPLAY]);

      $form_state->unsetValue(static::PROP_VIEW_DISPLAY);
      $form_state->setUserInput($input);
    }

    $property = function ($property) use ($input, $form_state) {
      // Check whether the "$property" has been sent as
      // a "GET['queryParameters'][$property]".
      if (empty($input['queryParameters'][$property])) {
        // Try reading from the state of a form. This is when
        // a user clicked out through the form.
        $input['queryParameters'][$property] = $form_state->getValue($property) ?: (isset($input[$property]) ? $input[$property] : '');
      }

      // The resulting value must be a string. Here we avoid any false-like
      // values using the "empty()".
      return empty($input['queryParameters'][$property]) ? '' : trim($input['queryParameters'][$property]);
    };

    // Parse the current query.
    if (isset($input['currentQuery'])) {
      parse_str(ltrim($input['currentQuery'], '?'), $input['currentQuery']);
    }
    // The current query is missing - use an input.
    else {
      $input['currentQuery'] = $input;
    }

    return new ViewFilterFormData(
      $property(static::PROP_VIEW_NAME),
      $property(static::PROP_VIEW_DISPLAY),
      $input['currentQuery'],
      // The optional "GET['dialogOptions']".
      !empty($input['dialogOptions']) ? $input['dialogOptions'] : []
    );
  }

  /**
   * {@inheritdoc}
   */
  public function isAccessible(AccountInterface $account): bool {
    return TRUE;
  }

}
