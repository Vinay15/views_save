<?php

namespace Drupal\views_save\Plugin\views\exposed_form;

use Drupal\better_exposed_filters\Plugin\BetterExposedFiltersWidgetManager;
use Drupal\better_exposed_filters\Plugin\views\exposed_form\BetterExposedFilters as BetterExposedFiltersBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\views_save\ViewFiltersMarkup;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

class BetterExposedFilters extends BetterExposedFiltersBase {

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
  public function __construct(array $configuration, $plugin_id, $plugin_definition, BetterExposedFiltersWidgetManager $filter_widget_manager, BetterExposedFiltersWidgetManager $pager_widget_manager, BetterExposedFiltersWidgetManager $sort_widget_manager, RequestStack $request_stack, AccountProxyInterface $current_user, RendererInterface $renderer, ViewFiltersMarkup $view_filters_markup) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $filter_widget_manager, $pager_widget_manager, $sort_widget_manager);

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
      $container->get('plugin.manager.better_exposed_filters_filter_widget'),
      $container->get('plugin.manager.better_exposed_filters_pager_widget'),
      $container->get('plugin.manager.better_exposed_filters_sort_widget'),
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
    $options['bef']['general']['views_save_enable'] = FALSE;
    return $options;
  }

  /**
   * {@inheritdoc}
   */
  public function buildOptionsForm(&$form, FormStateInterface $form_state) {
    parent::buildOptionsForm($form, $form_state);

    // Save shorthand for BEF options.
    $bef_options = $this->options['bef'];

    $form['bef']['general']['views_save_enable'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable views save'),
      '#default_value' => $bef_options['general']['views_save_enable'],
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

    if (!$this->options['bef']['general']['views_save_enable']) {
      return;
    }

    $this->traitExposedFormAlter($form, $form_state);
  }
}
