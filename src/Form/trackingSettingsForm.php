<?php
/**
 * Created by PhpStorm.
 * User: David
 * Date: 5/27/2017
 * Time: 10:04 AM
 */
namespace Drupal\tracking\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\tracking\Reporting\Tracking;

/**
 * Configure example settings for this site.
 */
class trackingSettingsForm extends ConfigFormBase {

  public function getFormId() {
	return 'tracking_admin_settings';
  }

  protected function getEditableConfigNames() {
	return ["tracking.settings"];
  }

  public function buildForm(array $form, FormStateInterface $form_state) {

    $config = $this->config('tracking.settings');

    $form['tracking'] = array(
		'data' =>array(
		  '#type' => 'details',
		  '#title' => 'Delete Data',
		  '#open' => true,

		  'btn' => array(
			'#type' => 'submit',
			'#value' => 'Delete all Tracking Data',
			'#submit' => array('tracking_admin_submit')
		  ),
		),
		'settings' =>array(
		  '#type' => 'details',
		  '#title' => 'Settings',
		  '#open' => true,

		  'chk_enabled' => array(
			'#type' => 'checkbox',
			'#title' => 'Tracking Enabled',
			'#description' => '<p>When checked, impressions and clicks will be saved to the database. <br>
				  <i><b>Note:</b> When deslected, images and redirects will continue, but the events will not be saved to the database.</i></p>',
			'#default_value' => $config->get('enabled'),
		  ),
		),
		'footer' => Tracking::reportFooter(false),
  	);
	return parent::buildForm($form, $form_state);
  }

  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->config('tracking.settings')
	  ->set('enabled', $form_state->getValue('chk_enabled'))
	  ->save();
	parent::submitForm($form, $form_state);
  }

}