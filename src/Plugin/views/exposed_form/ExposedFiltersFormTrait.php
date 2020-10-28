<?php

namespace Drupal\views_save\Plugin\views\exposed_form;

use Drupal\Component\Serialization\Json;
use Drupal\Core\Form\FormStateInterface;
use Drupal\modal_form\Element\ModalFormLink;
use Drupal\views_save\Entity\ViewFilter;
use Drupal\views_save\Form\ViewFilterSelectForm;

trait ExposedFiltersFormTrait {

  /**
   * {@inheritdoc}
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Exception
   */
  public function exposedFormAlter(&$form, FormStateInterface $form_state) {
    $route_name = $this->currentRequest->get('_route');
    $view_name = $this->view->id();
    $markup = $this->viewFiltersMarkup->getMarkup($view_name, $this->view->current_display);

    $form['#prefix'] = '<div class="view-filters--container">' . $this->renderer->renderPlain($markup);
    $form['#suffix'] = '</div>';

    if ($route_name === ModalFormLink::CONTROLLER_ROUTE || strpos($route_name, 'entity.views_save.') === 0) {
      // Hide the "Apply" button. Removal is not allowed. Otherwise, the
      // values that are currently selected may be incorrectly treated.
      $form['actions']['submit']['#printed'] = TRUE;
    }
    elseif (empty($_GET['hide-save-filter-button'])) {
      // Show the link to save current selection as a preset.
      $form['actions']['view_filter_save'] = [
        '#type' => 'modal_form_link',
        '#class' => ViewFilterSelectForm::class,
        '#title' => $this->t('Save filter'),
        '#access' => $this->currentUser->isAuthenticated(),
        '#printed' => !$this->isFiltersApplied(),
        '#attributes' => [
          'class' => ['save-filter'],
          'data-query-parameters' => Json::encode([
            'name' => $view_name,
            'display' => $this->view->current_display,
          ]),
          'data-dialog-options' => Json::encode([
            'dialogClass' => 'modal--views-save',
            'width' => '500px',
          ]),
        ],
      ];
    }
  }

  /**
   * Returns a state whether the filters were applied.
   *
   * @return bool
   *   The state.
   */
  protected function isFiltersApplied() {
    $input = $this->view->getExposedInput();

    if (empty($input) || !ViewFilter::isApplicable($this->view->display_handler, $this->currentUser)) {
      return FALSE;
    }

    return !empty(array_intersect_key(ViewFilter::getViewDisplayFilters($this->view->display_handler), $input));
  }
}
