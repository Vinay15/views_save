<?php

namespace Drupal\views_save\Form;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Entity\ContentEntityDeleteForm;
use Drupal\Core\Entity\EntityManagerInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\EventSubscriber\AjaxResponseSubscriber;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Form\ConfirmFormHelper;
use Drupal\views_save\ViewFiltersMarkup;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * {@inheritdoc}
 *
 * @method \Drupal\views_save\Entity\ViewFilterInterface getEntity()
 *
 * @link https://www.drupal.org/project/drupal/issues/2253257
 */
class ViewFilterDeleteForm extends ContentEntityDeleteForm {

  /**
   * The current request.
   *
   * @var \Symfony\Component\HttpFoundation\Request
   */
  protected $currentRequest;
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
    EntityManagerInterface $entity_manager,
    EntityTypeBundleInfoInterface $entity_type_bundle_info = NULL,
    TimeInterface $time = NULL,
    RequestStack $request_stack,
    ViewFiltersMarkup $view_filters_markup
  ) {
    parent::__construct($entity_manager, $entity_type_bundle_info, $time);

    $this->requestStack = $request_stack;
    $this->currentRequest = $this->requestStack->getCurrentRequest();
    $this->viewFiltersMarkup = $view_filters_markup->setCaller(static::class);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity.manager'),
      $container->get('entity_type.bundle.info'),
      $container->get('datetime.time'),
      $container->get('request_stack'),
      $container->get('views_save.view_filters_markup')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function actions(array $form, FormStateInterface $form_state) {
    if (!isset($form_state->getUserInput()['confirm'])) {
      $this->viewFiltersMarkup->setAjaxResponse($this->currentRequest->request->has(AjaxResponseSubscriber::AJAX_REQUEST_PARAMETER));
    }

    return [
      'submit' => $this->getSubmitButton(),
      'cancel' => $this->getCancelButton(),
    ];
  }

  /**
   * {@inheritdoc}
   */
  protected function getSubmitButton() {
    $button = [
      '#type' => 'submit',
      '#value' => $this->getConfirmText(),
    ];

    if ($this->viewFiltersMarkup->isAjaxResponse()) {
      $button['#type'] = 'button';
      $button['#ajax']['callback'] = '::submitForm';
    }

    return $button;
  }

  /**
   * {@inheritdoc}
   */
  protected function getCancelButton() {
    $button = ConfirmFormHelper::buildCancelLink($this, $this->currentRequest);
    $button['#attributes']['class'][] = 'dialog-cancel';

    return $button;
  }

  /**
   * {@inheritdoc}
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    if ($this->viewFiltersMarkup->isAjaxResponse()) {
      $entity = $this->getEntity();

      $entity->delete();
      $this->logDeletionMessage();

      return $this->viewFiltersMarkup->attachMarkup(new AjaxResponse(), $entity->getViewId(), $entity->getViewDisplayId(), $this->getDeletionMessage());
    }

    parent::submitForm($form, $form_state);

    return NULL;
  }

}
