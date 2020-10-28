<?php

namespace Drupal\views_save\Form;

/**
 * The data of a form that reflects its state modifications.
 *
 * @see \Drupal\views_save\Form\ViewFilterSelectForm
 */
class ViewFilterFormData {

  /**
   * The name of a Drupal view.
   *
   * @var string
   */
  protected $viewName;
  /**
   * The ID of a display of a Drupal view.
   *
   * @var string
   */
  protected $displayId;
  /**
   * The user input: initial or sequential.
   *
   * @var array
   */
  protected $userInput;
  /**
   * The optional set of parameters for the modal window.
   *
   * NOTE: Will be present only when the form is requested via "Drupal.ajax".
   *
   * @var array
   *
   * @see \Drupal\modal_form\Element\ModalFormLink
   */
  protected $dialogOptions;

  /**
   * {@inheritdoc}
   */
  public function __construct($view_name, $display_id, array $user_input, array $dialog_options = []) {
    $this->viewName = $view_name;
    $this->displayId = $display_id;
    $this->userInput = $user_input;
    $this->dialogOptions = $dialog_options;
  }

  /**
   * {@inheritdoc}
   */
  public function getViewName() {
    return $this->viewName;
  }

  /**
   * {@inheritdoc}
   */
  public function getDisplayId() {
    return $this->displayId;
  }

  /**
   * {@inheritdoc}
   */
  public function getUserInput() {
    return $this->userInput;
  }

  /**
   * {@inheritdoc}
   */
  public function getDialogOptions() {
    return $this->dialogOptions;
  }

}
