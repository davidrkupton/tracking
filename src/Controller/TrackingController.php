<?php

namespace Drupal\tracking\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\tracking\Reporting;
use Drupal\Component\Render\FormattableMarkup;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class TrackingController extends ControllerBase {

  public function adminOverview(){
    $form['admin'] = array(
      'title' => array(
        '#markup' => '<h2>Tracking Admin</h2>',
	  )
	);
    return $form;
  }

  /**
   * Control the output from route tracking.reportSummary and tracking.reportDetail
   * @param string $mode
   * @param string $type
   * @param string $campaign
   * @param string $source
   * @param int $user
   * @return array
   */
  public function report($mode, $type, $campaign, $source, $user){
	// decide the filtering
	if($mode=='p2') $mode = Reporting\Reporting::TRACKINGREPORTING_DETAIL_LEVEL0;

	//work out any sorting
	if(isset($_REQUEST['sort'])){
	  switch($_REQUEST['order']){
		case 'Source': $col="source"; break;
		case 'User': $col="user_id"; break;
		case 'URL': $col="destinationURL"; break;
		case 'Events': $col="count"; break;
		case 'Campaign': $col="campaign_id"; break;
		case 'Activity': $col="track_type"; break;
		case 'Last date': $col="timestamp"; break;
		case 'Latest Event Date': $col="timestamp"; break;
	  }
	}
	else{$_REQUEST['sort']="ASC";}

	$form['trackingReport']['output']["#attached"] = array('library'=>array('tracking/font-awesome'));

	// create the tracking reporting object
	$trackingReporting = new Reporting\Reporting();

	// create an array with sorting conditions
	$conditions = array(
	  'campaign_id' => $campaign,
	  'track_type' => $type,
	  'source' => $source);

	switch($mode){

	  case Reporting\Reporting::TRACKINGREPORTING_DETAIL_LEVEL1:

		if(!isset($col)) $col="user_id";

		// grab the report data
		$report = $trackingReporting->tracking_reporting_detail(Reporting\Reporting::TRACKINGREPORTING_DETAIL_LEVEL1, $conditions, $col, $_REQUEST['sort']);

		$form['trackingReport']['output']['title'] = array(
		  '#markup' =>"
			<h1>Tracking Campaign Report</h1>
			<h3>Showing " . $conditions['source'] . " " . $conditions['track_type'] . "s for campaign '" . $conditions['campaign_id'] . "'</h3>"
		);

		break;

	  case Reporting\Reporting::TRACKINGREPORTING_DETAIL_LEVEL2:

		$form['trackingReport']['output']['title'] = array(
			'#markup' =>"<h1>Tracking Drill-down Report</h1>"
		);

		switch($type){
		  case 'click':

			if(!isset($col)) $col="timestamp";

			// add the user to the filter array
			$conditions['tt.user_id'] = $user;

			// grab the report data
			$report = $trackingReporting->tracking_reporting_detail(Reporting\Reporting::TRACKINGREPORTING_DETAIL_LEVEL2, $conditions,$col, $_REQUEST['sort']);

			$form['trackingReport']['output']['title'] = array(
			  '#markup' =>"
					<h1>Tracking Campaign Report</h1>
					<h3>Drill-down showing click URL destinations for user <i>'".$report->username."'</i> in campaign <i>'".$conditions['campaign_id']."'</i> </h3>"
			);
			break;
		}

		break;

	  default:
	  case Reporting\Reporting::TRACKINGREPORTING_DETAIL_LEVEL0:

		if(!isset($col)) $col = "campaign_id";

		$report = $trackingReporting->tracking_reporting($col, $_REQUEST['sort']);

		$form['trackingReport']['output']['title'] = array(
		  '#markup' =>"
				<h1>Tracking Summary Report</h1>
				<h3>Shows activity and sources for clicks and opens for various campaigns</h3>"
		);

		break;

	}

	$form['trackingReport']['output']['report'] = array(
	  '#type'=>'table',
	  '#header' => $report->header_rows,
	  '#rows' => $report->data_rows,
	  '#options' => $report->data_rows,
	  '#empty' => "No tracking data captured yet."
	);

	// put some explanation at the bottom of the report
	$form['trackingReport']['output']['footer'] = array(
	  '#markup' => $trackingReporting->reportFooter()
	);
	return $form;
  }

  /**
   * Control the action from route tracking.click
   * Record a click which leads to a redirect
   * NOTE: may have a querystring with parameters:
   * 	(string) url = 	[required] the url to redirect to. Local redirects prefix with '/'
   * 					External redirects use full url starting 'http[s]://'
   *	(string) src = 	[optional] the users email if known e.g. if userid is not
   * @param string $type
   * @param int $userid
   * @param string $campaign
   * @param string $source
   */
  public function captureClick($type, $userid, $campaign, $source){

    // find the destination
    if(isset($_REQUEST['url'])) $url = $_REQUEST['url'];
    if(isset($_REQUEST['URL'])) $url = $_REQUEST['URL'];

    if($url) {
      // cleanup
	  if(stripos($url, "http://") === false && stripos($url, "https://") === false) {
		if(substr($url,0,1)!='/') $url = "/".$url;
		$url = "http://".$_SERVER['HTTP_HOST'].$url;
	  }
      // record the click in the database
	  if(strtolower($type=='click'))
	    Reporting\Tracking::track_click($userid, $campaign, $source, $url);

	  // redirect to the requested url
	  ob_clean();
	  header("Location: ".$url);
	  exit;
	}

  }

  /**
   * Control the action from route tracking.open
   * Record a click which leads to a redirect
   * NOTE: may have a querystring with parameters:
   *	(string) src = 	[optional] the users email if known e.g. if userid is not
   * @param int $userid
   * @param string $campaign
   * @param string $source
   * @param string $image
   */
  public function captureView($userid, $campaign, $source, $image){

    // make sure we have an image file
	if(!$image || !file_exists(Reporting\Tracking::imageurlpath().$image)) $image = "tran.gif";

	// now return the image requested, assuming we have it ...
    if(file_exists(Reporting\Tracking::imageurlpath().$image)) {

	  // record the impression to the database
	  Reporting\Tracking::track_impression($userid, $campaign, $source);

	  ob_clean();
	  header('Content-Type: image/png');
	  header('Pragma: public');   // required
	  header('Expires: 0');       // no cache
	  header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
	  header('Cache-Control: private', FALSE);
	  header('Content-Disposition: attachment; filename="' . $image . '"');
	  header('Content-Transfer-Encoding: binary');
	  header('Content-Length: ' . filesize(Reporting\Tracking::imagefilepath() . $image));   // provide file size

	  @readfile(Reporting\Tracking::imageurlpath() . $image);      // push it out
	}
	else{
      // if the file was not found, then throw an error
      throw new NotFoundHttpException();
	}
	exit;

  }

  public function deleteData($type, $userid, $campaign, $source){

    $conditions = array();
    if(!empty($type) && $type!= 'null') $conditions['track_type'] = $type;
    if(isset($userid) && $userid!= 'null') $conditions['user_id'] = $userid;
    if(!empty($campaign) && $campaign!= 'null') $conditions['campaign_id'] = $campaign;
    if(!empty($source) && $source!= 'null') $conditions['source'] = $source;
    if(!empty($_REQUEST['url'])) $conditions['destinationURL'] = urldecode($_REQUEST['url']);

	// record the impression to the database
	Reporting\Tracking::deleteTracking($conditions);

	//redirect to previous level in the heirachy ...
	if($userid == "null") $url='/admin/reports/clicktracking';
	elseif(isset($conditions['destinationURL'])) $url='/admin/reports/clicktracking/2/'.$type.'/'.$campaign.'/'.$source.'/'.$userid;
	else $url='/admin/reports/clicktracking/1/'.$type.'/'.$campaign.'/'.$source;

	return new RedirectResponse($url);

  }

  public function config($name) {
    $config = parent::config("tracking.settings");
	if(isset($name)) return $config->get($name);
	return $config->getRawData();
  }

  public function helpPage(){

    global $base_url;

	$params = array(
	  "%server" => $base_url,
	  ":server" => $base_url,
	  "%imagefilepath" => \Drupal\tracking\Reporting\Tracking::imagefilepath(),
	);
	$tableHeader = array(
	  array('style' => 'width: 15%; visibility: hidden;;'),
	  array('style' => 'width: 15%; visibility: hidden;;'),
	  array('style' => 'width: 70%; visibility: hidden;;'),
	);

	// make the table of images which are available
	$imageTable = array(
	  '#type'=>'table',
	  '#header' =>  array(
	    ['style'=>'width:20%;visibility: hidden;'],['style'=>'width:20%;visibility: hidden;']
	  ),
	  '#rows' => array(['data'=>'The blah', 'colspan'=>2]),
	);
	$images=array_diff(scandir(Reporting\Tracking::imagefilepath()), array('..', '.'));
	foreach($images as $image){
	  if(preg_match("/\.[jpg|gif|png]/i",$image))
	    $imageTable['#rows'][] = array(
	      '',
		  $image,
		  new FormattableMarkup('<img src="'.\Drupal\tracking\Reporting\Tracking::imageurlpath().$image.'" style="height:15px" />', array())
		);
	}

	// make the help page
	return array(
	  "help_page"=>array(
		'#tree' => true,
		'#type' => 'fieldset',
		'#title' => t('About Tracking Module'),
		'#markup' => 'The tracking module records when a key image has been downloaded from the website and when an image or link has been clicked.',

		'usage' => array(
		  	'part1'=>array(
			  '#markup' => '
        	    <h2>Usage</h2>
				<p>The module can be used to monitor when an image which is embedded in the HTML of a page, iframe or email has been downloaded
				(and presumably viewed) or when an image or link has been clicked.
				This allows the measuring of the effectiveness of campaigns or general efficiency directing a user to view something or achieve a goal.
				The tracking links can be used anywhere and on any site even ones external to tradermade (e.g. place the link in a mailchimp email) 
				</p>'
			),
		),
		'impressions' => array(

			'#type' => 'details',
			'#title' => 'Track Impressions/Views',
			'#description' => 'The module interprets a url posted to the sever which is populated with various parameters, and records those parameters 
				  in the database before returning an image to the user.',

			'part1' => array(
				'#type' => 'fieldgroup',
				'#title' => '&bull; Tracking Code for Impressions',
				'#markup' => '<p>The code to insert to track views is as follows</p>',

			  	'inner' => array(
					'#markup' => t('<b><code><pre>%server/reimage/{userid}/{campaign}/{source}/{image}?src={email}</pre></code></b>', $params),
				),

				'table' => array(
				  '#type' => 'table',
				  '#header' =>  $tableHeader,
				  '#rows' => array(
					[new FormattableMarkup('<b>Where</b>',array()),'',''],
					['','{userid}','The numeric UID for the user.  The value 0 can be used if the user is unknown, and maps therefore to anonymous'],
					['','{campaign}','A string for the campaign so reports can be campaign specific.  Any HTML-safe (escaped) string can be used.'],
					['','{source}','A field to define the location of the link - use a 3 letter convention where web=on website, eml=email, ext=external website, sf=salesforce.  However, any string can be used.'],
					['','{image}',t('The image filename.  The image must already exist in the images subfolder (in this case %imagefilepath).', $params)],
					['','','The imges presently loaded are:.'],
					['','',\Drupal::service('renderer')->render($imageTable)],
					['','{email}',t('[OPTIONAL] The person to be tracked\'s email address.', $params)],
				  ),
				),
				'notes' => array(
				  '#type' => 'fieldset',
				  '#title' => 'Notes:',
				  '#markup'=>t('
					<ol><li>Images in the table above are zoomed to format nicely on this page.  When delivered by the URL they will be their original size.</li>
					<li>You may add any image that you choose to the folder %imagefilepath.</li>
					<li>The tran.gif image is a single pixel transparent gif image, so that nothing will appear on the page being tracked.</li>
					<li>If the person being tracked is not known to be a registered site user, then use userid=0 (anonymous) and set the email. The Tracking module will attempt to find the userid from the email.</li></ol>
					</p>', $params),
				),
			  	'example' => array(
			  	  '#type' => 'fieldset',
			  	  '#title' => 'Example:',
			  	  '#markup' => t('
						<p>
						<pre>Example Impression (image delivery) URL: <br/>	<b><code>%server/reimage/1/registration email/web/tradermade.png</code></b></pre>
					  	<pre>Actual HTML: <br/>	<b><code>&lt;img src="%server/reimage/1/registration email/web/tradermade.png" alt="FXNavigator Research" style="css:value"/></code></b></pre>
					  </p>',$params),
				)

			),
		),
		'clicks' => array(

		  '#type' => 'details',
		  '#title' => 'Track Clicks',
		  '#description' => 'The module interprets a url posted to the sever which is populated with various parameters, and records those parameters 
			in the database before redirecting to the desired url.',

		  'part1' => array(
			'#type' => 'fieldgroup',
			'#title' => '&bull; Tracking Code for Clicks',
			'#markup' => '<p>The code to insert to track link clicks is as follows:</p>',
			'inner' => array(
			  '#markup' => t('<b><code><pre>%server/redirect/{type}/{userid}/{campaign}/{source}?url={url}&src={email}</pre></code></b>', $params),
			),
			'table' => array(
			  '#type' => 'table',
			  '#header' =>  $tableHeader,
			  '#rows' => array(
				[new FormattableMarkup('<b>Where</b>',array()),'',''],
				['','{type}','The string "click" (this is for future expansion into other tracking event types).'],
				['','{userid}','The numeric UID for the user.  The value 0 can be used if the user is unknown, and maps therefore to anonymous'],
				['','{campaign}','A string for the campaign so reports can be campaign specific.  Any HTML-safe (escaped) string can be used.'],
				['','{source}','A field to define the location of the link - use a 3 letter convention where web=on website, eml=email, ext=external website, sf=salesforce.  However, any string can be used.'],
				['','{url}', 'The full url to which the user is to be directed.  If it contains a query, then the string must be urlencoded.'],
				['','{email}',t('[OPTIONAL] The person to be tracked\'s email address.', $params)],
			  ),
			),
		  ),
		  'notes' => array(
			'#type' => 'fieldset',
			'#title' => 'Notes:',
			'#markup'=>t('<p>
					<ol><li>URL/link must be prefixed "http://" or else will be relative to [%server].</li>
					<li>If the person being tracked is not known to be a registered site user, then use userid=0 (anonymous) and set the email. The Tracking module will attempt to find the userid from the email.</li></ol>
					</p>',$params),
		  ),
		  'example' => array(
			'#type' => 'fieldset',
			'#title' => 'Example:',
			'#markup' => t('
						<p>
						<pre>Example Click URL: <br/>	<b><code>%server/redirect/click/1/registration email/web?url=http://www.tradermade.com/article/123456</code></b></pre>
					  	<pre>Actual HTML: <br/>	<b><code>&lt;a href="%server/redirect/click/1/registration email/web?url=http://www.tradermade.com/article/123456">
		Click here for pricing
	&lt;/a></code></b></pre>
					  </p>',$params),
		  ),
		),
		'reporting'=>array(
		  '#type' => 'details',
		  '#title' => 'Analytics / Reporting',
		  '#markup' => t('<p>There are basic reports available from <a href=":server/admin/reports/clicktracking">:server/admin/reports/clicktracking</a>.</p>
			<p>Tracking data is stored in the database in tables tm_tracking and tm_tracking_log.  Additional information, 
			such as ipaddresses and user_agent is also recorded and can be analysed using the views module (see tips below).</p>
			', $params)
		),
		'tips' => array(
		  '#type' => 'details',
		  '#title' => 'Tips and Knowledgebase',
		  '#markup' => t('   
                <p><ol>
                    <li>You can include both an open and click link:
                    <pre>Actual HTML:<br><b><code>	&lt;a href="%server/redirect/click/1/registration email/web?url=http://www.tradermade.com/article/123456">
		&ltimg src="%server/reimage/1/registration email/web/tradermade.png" alt="FXNavigator Research" style="css:value"/>
	&lt;/a></code></b></pre>
					This will show a clickable image which records both when it is shown and also when it is clicked.
                    </li>
                    <li>If the views module is enabled (<a href="/admin/structure/views">admin/structure/views</a>) 
                    then you can use the collected tracking data for custom analysis. </li>
                </ol></p>',$params),
	  	),
	  ),
	);
  }

}