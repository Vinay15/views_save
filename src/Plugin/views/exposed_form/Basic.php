<?php

namespace Drupal\views_save\Plugin\views\exposed_form;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\views\Plugin\views\exposed_form\Basic as BasicBase;
use Drupal\views_save\ViewFiltersMarkup;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

class Basic extends BasicBase {

  use ExposedFiltersFormTrait {
    exposedFormAlter as traitExposedFormAlter;
  }

  /**
   * The current request.
   *
   * @var \Symfony\Component\HttpFoundation\Request
   */
  protected $currentRequest;

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
  public function __construct(array $configuration, $plugin_id, $plugin_definition, RequestStack $request_stack, AccountProxyInterface $current_user, RendererInterface $renderer, ViewFiltersMarkup $view_filters_markup) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);

    $this->currentRequest = $request_stack->getCurrentRequest();
    $this->currentUser = $current_user;
    $this->renderer = $renderer;
    $this->viewFiltersMarkup = $view_filters_markup;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('request_stack'),
      $container->get('current_user'),
      $container->get('renderer'),
      $container->get('views_save.view_filters_markup')
    );
  }

  /**
   * {@inheritdoc}
   */
  protected function defineOptions() {
    $options = parent::defineOptions();
    $options['views_save_enable'] = FALSE;
    return $options;
  }

  /**
   * {@inheritdoc}
   */
  public function buildOptionsForm(&$form, FormStateInterface $form_state) {
    parent::buildOptionsForm($form, $form_state);

    $form['views_save_enable'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable views save'),
      '#default_value' => $this->options['views_save_enable'],
    ];
  }

  /**
   * {@inheritdoc}
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Exception
   */
  public function exposedFormAlter(&$form, FormStateInterface $form_state) {
    parent::exposedFormAlter($form, $form_state);

    if (!$this->options['views_save_enable']) {
      return;
    }

    $this->traitExposedFormAlter($form, $form_state);
  }
}
