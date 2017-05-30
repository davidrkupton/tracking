<?php

namespace Drupal\tracking\Reporting;
use Drupal\tracking\Controller\TrackingController;
use Drupal\Component\Render\FormattableMarkup;

/**
 * Class tracking
 * This class contains functionality for the tracking module.
 */
class Tracking{

    protected $_host ;

  /**
   * Wrapper/helper for interacting with the config object (variables) for this module.
   * @param null string $key
   * @param null string $value
   * @return array|mixed
   */
    static public function config($key=null, $value=null){
      // if no config key is requested then return an array of the config settings
      if(empty($key)) return \Drupal::config('tracking.settings')->getRawData();
      // if a config key is to be set then set it
	  if(!empty($key) && !empty($value)) {
	    $config=\Drupal::service('config.factory')->getEditable('tracking.settings');
	    $config->set($key,$value)->save();
	  }
	  // return the requested key
	  return $config->get($key);
	}

  /**
     * Make a private function so the host is not made until needed
     * @return string
     */
    static private function host(){
        return "//" . $_SERVER['HTTP_HOST'];
    }

  /**
   * Returns the correct local file path to the images folder for this module
   * @return string
   */
    static public function imagefilepath(){
        return $_SERVER['DOCUMENT_ROOT'].'/'.drupal_get_path('module','tracking').'/images/';
    }

  /**
   * Returns the correct url path to the images folder for this module
   * @return string
   */
    static public function imageurlpath(){
        global $base_url;
        return $base_url.'/'.drupal_get_path('module','tracking').'/images/';
    }

  /**
   * Records a tm_tracking defined image impression
   * @param int $user
   * @param string $campaign
   * @param string $source
   */
    static public function track_impression($userid, $campaign, $source){

	   $config = new TrackingController();

	   // only if the module is enabled
	   if($config->config('enabled')) {
		// verify the userid
		if (empty($_REQUEST['src'])) {
		  $_REQUEST['src'] = '';
		}
		self::verifyuid($userid, $_REQUEST['src']);

		$arrVals = array(
		  "@source" => substr(strtolower($source), 0, 3),
		  "@campaign" => $campaign,
		  "@user" => $userid,
		  "@email" => (!empty($_REQUEST['src']) ? $_REQUEST['src'] : NULL),
		  "@url" => NULL,
		  "@ipaddress" => $_SERVER['REMOTE_ADDR'],
		  "@hosttype" => $_SERVER['HTTP_USER_AGENT']
		);

		switch (strtolower($source)) {       //can override parameters if necessary
		  case "sf":
			break;               // email came from Salesforce
		  case "web":
			break;              // email came from website mail functions
		  case "ext":
			break;              // email came from external mail functions (e.g. mailchimp)
		}
		$query = \Drupal::database()->select('tm_tracking', 'tt')
		  ->fields('tt', array('recid', 'count'))
		  ->condition('tt.track_type', 'open')
		  ->condition('tt.source', $arrVals['@source'])
		  ->condition('tt.campaign_id', $arrVals['@campaign'])
		  ->condition('tt.user_id', $arrVals['@user']);

		if ($result = $query->execute()->fetchObject()) {
		  // record found, update the counter
		  $arrVals['@count'] = ($result->count) + 1;
		  $arrVals['@recid'] = $result->recid;
		  $num = \Drupal::database()->update('tm_tracking')
			->fields(array('count' => $arrVals['@count']))
			->condition('recid', $arrVals['@recid'], "=")
			->execute();
		  if ($num > 0) {
			\Drupal::database()->insert('tm_tracking_log')
			  ->fields(array(
				'recid' => $arrVals['@recid'],
				'ipaddress' => $arrVals['@ipaddress'],
				'hosttype' => $arrVals['@hosttype'],
				'email' => $arrVals['@email'],
			  ))
			  ->execute();
		  }
		}
		else {
		  // record not found, create it
		  $arrVals['@recid'] = \Drupal::database()->insert('tm_tracking')
			->fields(array(
			  'track_type' => 'open',
			  'source' => $arrVals['@source'],
			  'campaign_id' => $arrVals['@campaign'],
			  'user_id' => $arrVals['@user'],
			  'destinationURL' => $arrVals['@url'],
			  'count' => 1
			))
			->execute();
		  if (isset($arrVals['@recid']) && is_numeric($arrVals['@recid'])) {
			\Drupal::database()->insert('tm_tracking_log')
			  ->fields(array(
				'recid' => $arrVals['@recid'],
				'ipaddress' => $arrVals['@ipaddress'],
				'hosttype' => $arrVals['@hosttype'],
				'email' => $arrVals['@email'],
			  ))
			  ->execute();
		  }
		}
	  }
    }

  /**
   * Records a tm_tracking defined page redirect (i.e. a click)
   * @param int $user
   * @param string $campaign
   * @param string $source
   * @param string $url
   * @return bool
   */
    static public function track_click($userid, $campaign, $source, $url){

	  $config = new TrackingController();

	  // only if the module is enabled
	  if($config->config('enabled')) {
		// verify the userid
		if (empty($_REQUEST['src'])) {
		  $_REQUEST['src'] = '';
		}
		self::verifyuid($userid, $_REQUEST['src']);

		$arrVals = array(
		  "@source" => substr(strtolower($source), 0, 3),
		  "@campaign" => $campaign,
		  "@user" => $userid,
		  "@email" => (!empty($_REQUEST['src']) ? $_REQUEST['src'] : NULL),
		  "@url" => $url,
		  "@ipaddress" => $_SERVER['REMOTE_ADDR'],
		  "@hosttype" => $_SERVER['HTTP_USER_AGENT']
		);

		switch (strtolower($source)) {       //can override parameters if necessary
		  case "sf":
			break;               // email came from Salesforce
		  case "web":
			break;              // email came from website mail functions
		  case "ext":
			break;              // email came from external mail functions (e.g. mailchimp)
		}

		$query = \Drupal::database()->select('tm_tracking', 'tt')
		  ->fields('tt', array('recid', 'count'))
		  ->condition('tt.track_type', 'click')
		  ->condition('tt.source', $arrVals['@source'])
		  ->condition('tt.campaign_id', $arrVals['@campaign'])
		  ->condition('tt.destinationURL', $arrVals['@url'])
		  ->condition('tt.user_id', $arrVals['@user']);

		if ($result = $query->execute()->fetchObject()) {
		  // record found, update the counter
		  $arrVals['@count'] = ($result->count) + 1;
		  $arrVals['@recid'] = $result->recid;
		  $num = \Drupal::database()->update('tm_tracking')
			->fields(array('count' => $arrVals['@count']))
			->condition('recid', $arrVals['@recid'], "=")
			->execute();
		  if ($num > 0) {
			\Drupal::database()->insert('tm_tracking_log')
			  ->fields(array(
				'recid' => $arrVals['@recid'],
				'ipaddress' => $arrVals['@ipaddress'],
				'hosttype' => $arrVals['@hosttype'],
				'email' => $arrVals['@email'],
			  ))->execute();
			return TRUE;
		  }
		  // something failed in the update
		  return FALSE;
		}
		else {
		  // record not found, create it
		  $arrVals['@recid'] = \Drupal::database()->insert('tm_tracking')
			->fields(array(
			  'track_type' => 'click',
			  'source' => $arrVals['@source'],
			  'campaign_id' => $arrVals['@campaign'],
			  'user_id' => $arrVals['@user'],
			  'destinationURL' => $arrVals['@url'],
			  'count' => 1
			))
			->execute();
		  if (isset($arrVals['@recid']) && is_numeric($arrVals['@recid'])) {
			\Drupal::database()->insert('tm_tracking_log')
			  ->fields(array(
				'recid' => $arrVals['@recid'],
				'ipaddress' => $arrVals['@ipaddress'],
				'hosttype' => $arrVals['@hosttype'],
				'email' => $arrVals['@email'],
			  ))->execute();
			return TRUE;
		  }
		  // something failed in the insert ...
		  return FALSE;
		}
	  }
    }

    /**
     * Makes a standard comment which can be placed on drupal forms.
     * @return string
     */
    static public function reportFooter($render=true){
        if(!isset($render)) $render = true;
       // drupal_add_library('system', 'drupal.collapse');
        $files = array_diff(scandir(self::imagefilepath()), array('..', '.'));
        $output = array('footer' => array(
			'#tree' => true,
			'#type' => 'details',
			'#title' => t('Tracking Module Usage Notes'),
			'#description'=>'The tracking module records various image impressions and link clicks.',
			'#open' => false,

			'description' => array(
			  	'#type'=>'details',
				'#title'=>'This Report: Source column explanation:',
				'#description'=>'Source defines where an email (or clickable link) has originated.',
			  	'table'=> array(
			  		'#type'=>'table',
					'#rows'=> array(
						['web','means a track from a TraderMade website or TraderMade originated email.'],
						['sf', 'means a track from the tradermade SalesForce app.'],
						['ext','means a track from some other external website or externally-originated email campaign.']
					),
				),
			),
			'quickguide' => array(
			  '#type'=>'details',
			  '#open' => false,
			  '#title'=>'Quick Guide for Creating Tracking Links and Images',
			  '#description'=>'Trackable links may be created as follows',

			  '#markup' => "<i>(full guide may be found <a href='/admin/tracking/help'>here</a>)</i>",
			  'reimage' => array(
				  '#type'=>'details',
				  '#title'=>new FormattableMarkup('Track when something is viewed (opened) <i class="fa fa-eye" style="font-size: 125%"></i>',array()),
				  '#description'=>'Create an image which records an impression when it is viewed.',
				  '#markup' => t('<b><code><pre>&lt;img src=":host/reimage/{userid}/{campaign}/{source}/{image}?src={email}"</pre></code></b>',array(':host'=>self::host())),
				  'table' => array(
					  '#type'=>'table',
					  '#rows'=> array(
						  [new FormattableMarkup('<b>Where</b>',array()),'{userid} = userid(number)'],
						  ['','{campaign} = campaign(text)'],
						  ['','{source} = source(ext/web/sf)'],
						  ['','{image} = image(text)'],
						  ['','(one of: '.implode("/",$files).')'],
						  ['','{email} = [optional] recipients email address(text)']
					  )
				  )
			  ),
			  'redirect' => array(
				  '#type'=>'details',
				  '#title'=>new FormattableMarkup('Track when something is clicked <i class="fa fa-hand-o-down" style="font-size: 125%"></i>',array()),
				  '#description'=>'Create a tackable link and use it anywhere.',
				  '#markup' => t('<b><code><pre>&lt;a href=:host/redirect/{type}/{userid}/{campaign}/{source}?url={url}&src={email}&gt;&lt;/a&gt;</pre></code></b>',array(':host'=>self::host())),
				  'table'=>array(
					  '#type' => 'table',
					  '#rows' => array(
						  [new FormattableMarkup('<b>Where</b>',array()),'{type} = "click"'],
						  ['','{userid} = userid(number)'],
						  ['','{campaign} = campaign(text)'],
						  ['','{source} = source(ext/web/sf)'],
						  ['','{url} = link to follow'],
						  ['','{email} = [optional] recipients email address(text)'],
						  [new FormattableMarkup('<b>Note:</b>',array()),'link must be prefixed "http://" or else will be relative to \["http://'.$_SERVER['HTTP_HOST'].'/"]']
					  ),
				  ),
			  ),
			),
        ));
        if(!$render) return $output;
		return \Drupal::service('renderer')->render($output);
    }

  /**
   * Verifies and updates the userid and the email for the user
   * @param int $userid
   * @param string $email
   * @return bool
   */
    static private function verifyuid(&$userid, &$email){

      	$account = null;

      	// if there is a uid, then check its valid, if not set to anonymous
		if(!empty($userid)){
		  	$account = \Drupal\user\Entity\User::load($userid);
		  	if(!$account) $userid = 0;
		}

		// if there is not userid but there is an email, attempt to find the user from the email
		//  if cant find a user then set to 0 [= anonymous]
	  	if(empty($userid) && !empty($email)){
			$account = user_load_by_mail($email);
			if($account) $userid = $account->id();
			else $userid = 0;
	  	}

	  	// finally complete the circle by setting the email
	  	if(empty($email) && !empty($userid)){
	  	  if(!$account) $account = \Drupal\user\Entity\User::load($userid);
	  	  $email = $account->getEmail();
		}

		return ($userid==0);		// return false if user is annymous or true if not.
	}

  /**
   * Delete records from the database, removes tm_tracker and associated tm_tracker_log entries
   * $conditions must be a list of field names from the tm_tracking table.
   * if an empty array or null is passed, then all records will be deleted from both tables.
   * @param array $conditions
   * @return bool
   */
	static public function deleteTracking($conditions){

	  // set up to delete from both tables simultaneously
	  $query = "FROM tm_tracking t INNER JOIN tm_tracking_log l ON t.recid = l.recid ";

	  // process conditions (if any)
	  if(!empty($conditions)){

	    $cond_join = t("WHERE");		//firstime will be Where, each subsequent time will be and...

			foreach($conditions as $field => $condition){
			  	// force the condition t be on the tm_tracking table if not specified
			  	if(stripos($field,".")===false) $field="t.".$field;
			  	$op="=";
			  	// check if the string contains "{" and if so switch it out (drupal uses {} to mark table names...
			  	if(strpos($condition,'{') >=0) {
				  $op = "like";
				  $condition = str_replace(array('{', '}'), '%', $condition);
				}
			  	$query .= t(":join :field :op ':value' ", array(":join"=>$cond_join, ":op"=>$op, ":field"=>$field, ":value"=>$condition));
			  	$cond_join = "AND";
			}
	  }
	  try{
		// first, find any orphans in the _log table and delete those
		\Drupal::database()->query('DELETE l FROM tm_tracking t RIGHT JOIN tm_tracking_log l ON t.recid = l.recid WHERE ISNULL(t.recid);')->execute();
	    // next, remember the recid's we have modified
		$affectedRecords = \Drupal::database()->query('SELECT DISTINCT t.recid '.$query)->fetchAllKeyed(0,0);
		if(!empty($affectedRecords)) {
		  // then, execute the requested delete
		  \Drupal::database()->query('DELETE t, l ' . $query)->execute();
		  // finally recalc the stats
		  $query = 'UPDATE tm_tracking outs
					SET outs.count = (SELECT count(ins.recid) cnt
										FROM tm_tracking_log ins
                        				WHERE ins.recid = outs.recid
										GROUP BY ins.recid)  
    				WHERE outs.recid in (' . implode(',', $affectedRecords) . ')';
		  \Drupal::database()->query($query)->execute();
		  drupal_set_message(t(':count :txt deleted.', array(':count'=>count($affectedRecords), ':txt'=>(count($affectedRecords)!=1?'records were':'record was'))), 'status');
		}
		else{
		  drupal_set_message('No records found to delete.', 'warning');
		}
	  }
	  catch(\PDOException $e){
	    return false;
	  }
      return true;
	}
}