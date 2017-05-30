<?php
/**
 * Created by PhpStorm.
 * User: David
 * Date: 5/25/2017
 * Time: 7:33 PM
 */

namespace Drupal\tracking\Reporting;

/**
 * Class Reporting
 * This class contains functionality to return the data needed to theme a report table in a drupal node.
 */

use Drupal\Component\Render\FormattableMarkup;

class Reporting extends Tracking {

  // define constants for use in controlling the drill-down level of the reports
  const TRACKINGREPORTING_DETAIL_LEVEL0 = 0;
  const TRACKINGREPORTING_DETAIL_LEVEL1 = 1;
  const TRACKINGREPORTING_DETAIL_LEVEL2 = 2;
  static public $report_detail_level = array(
	self::TRACKINGREPORTING_DETAIL_LEVEL0 => 0,
	self::TRACKINGREPORTING_DETAIL_LEVEL1 => 1,
	self::TRACKINGREPORTING_DETAIL_LEVEL2 => 2
  );

  public $header_rows = array();
  public $data_rows= array();
  public $username;

  /**
   * Returns a table-friendly dataset of recorded impressions and click events.
   * @param string $column
   * @param string $order
   * @return $this
   */
  public function tracking_reporting($column = "campaign_id", $order="ASC"){

	if(!isset($column)) $column = "campaign_id";
	if(!isset($order))  $order = "ASC";

	$this->header_rows = array(
	  'campaign_id' => array('data' => t('Campaign'), 'style' => 'width: 30%;','field'=>'2'),
	  'source' => array('data' => t('Source'), 'style' => 'width: 10%;','field'=>'1'),
	  'activity' => array('data' => t('Activity'), 'style' => 'width: 10%;','field'=>'3'),
	  'events' => array('data' => t('Events'), 'style' => 'width: 10%;','field'=>'4'),
	  'last' => array('data' => t('Last date'), 'style' => 'width: 20%;','field'=>'5'),
	  'actions' => array('data' => t(''), 'style' => 'width: 20%;'),
	);

	$query = \Drupal::database()->select('tm_tracking','tt')->fields('tt', array('campaign_id','source','track_type'));
	$query->addExpression('sum(tt.count)','count');
	$query->addExpression('max(tt.timestamp)','timestamp');
	$query->groupBy('tt.campaign_id');
	$query->groupBy('tt.source');
	$query->groupBy('tt.track_type');
	$query->orderBy($column, $order);
	$result = $query->execute();

	foreach ($result as $row){
	  if($row->campaign_id!='MessageName' && $row->campaign_id!='[schedule:msg-name]') {
		/* build the table */
		$dataRow = array();
		$dataRow['campaign_id'] = $row->campaign_id;
		$dataRow['source'] = $row->source;
		$dataRow['activity'] = new FormattableMarkup('<i class="fa '.($row->track_type=='click'?'fa-hand-o-down':'fa-eye').'"></i> '.$row->track_type, array());
		$dataRow['events'] = $row->count . " " . $row->track_type . "s";
		$dataRow['last'] = date("D d-M-Y H:i", strtotime($row->timestamp));
		$dataRow['actions'] = new FormattableMarkup('<a href=":link" title=":tip"><i class="fa fa-times" style="color:red"></i>&nbsp;Delete Campaign :type\'s</a> |&nbsp;<a href=":link2" title=":tip2" style="display:inline-block"><i class="fa fa-search"></i>&nbsp;Drill-down</a>',
		  array(
			':type' => $row->track_type,
			':link' => '/admin/secure/trackingdelete/' . $row->track_type . '/null/' . $row->campaign_id . '/' . $row->source,
			':tip' => 'Delete ALL the tracked '.$row->track_type.'\'s for this campaign ('.$row->campaign_id.')',
			':link2' => '/admin/reports/clicktracking/' . self::TRACKINGREPORTING_DETAIL_LEVEL1 . '/' . $row->track_type . '/' . $row->campaign_id . '/' . $row->source,
			':tip2' => 'Report on '. $row->source.' originated '.$row->track_type.'\'s for this campaign ('.$row->campaign_id.')'
		  )
		);

	  $this->data_rows[] = $dataRow;
	  }
	}
	return $this;
  }

  /**
   * Returns a drill-down table-friendly dataset of recorded impressions and click events
   * @param int $detail_level
   * @param array $conditions
   * @param string $column
   * @param string $order
   * @return $this
   */
  public function tracking_reporting_detail($detail_level = self::TRACKINGREPORTING_DETAIL_LEVEL1, $conditions, $column=NULL, $order="ASC"){

	switch($detail_level){

	  case self::TRACKINGREPORTING_DETAIL_LEVEL1:
		$this->header_rows = array(
		  'source' => array('data' => t('Source'), 'style' => 'width: 10%;','field'=>'1'),
		  'user' => array('data' => t('User'), 'style' => 'width: 40%;','field'=>'4'),
		  'events' => array('data' => t('Events'), 'style' => 'width: 10%;','field'=>'5'),
		  'last' => array('data' => t('Latest Event Date'), 'style' => 'width: 20%;','field'=>'6'),
		  'actions' => array('data' => t(''), 'style' => 'width: 20%;'),
		);
		if(!$column) $column="user_id";
		$query = \Drupal::database()->select('tm_tracking','tt')->fields('tt', array('campaign_id','source','track_type'));
		$query->leftJoin('users','u','tt.user_id=u.uid');
		$query->join('users_field_data','ud','u.uid=ud.uid');
		$query->addField('tt','user_id','userid');
		foreach($conditions as $field=>$value) $query->condition($field,$value,"=");
		$query->addExpression('sum(tt.count)','count');
		$query->addExpression('max(tt.timestamp)','timestamp');
		$query->addExpression('max(ud.name)','user');
		$result = $query->groupBy('tt.source')
		  ->groupBy('tt.user_id')
		  ->orderBy($column, $order)
		  ->execute();

		while ($row=$result->fetchObject()){
		  if($row->user=="") $row->user="Anonymous";
		  if($row->campaign_id!='MessageName') {
			/* build the table */
			$dataRow = array();
			$dataRow['source'] = $row->source;
			$dataRow['user'] = new FormattableMarkup("<a href='/user/" . $row->userid . "/view'>" . $row->user . "</a>", array());
			$dataRow['events'] = $row->count . ' ' . $conditions['track_type'] . "s";
			$dataRow['last'] = date("D d-M-Y H:i", strtotime($row->timestamp));

			$action = '<a href=":link" title=":tip"><i class="fa fa-times" style="color:red"></i>&nbsp;Delete :user\'s :types</a>';
			if($conditions['track_type']=="click") $action .= ' |&nbsp;<a href=":link2" title=":tip2"><i class="fa fa-search"></i>&nbsp;Drill-down</a>';
			$dataRow['actions'] = new FormattableMarkup($action,
			  array(
			    ':type'=>$conditions['track_type'],
			    ':user'=>$row->user,
			    ':tip' => 'Delete all tracked '.$row->source.' '.$conditions['track_type'].'\'s for '.$row->user.' for campaign '.$row->campaign_id,
			    ':link' => '/admin/secure/trackingdelete/'.$conditions['track_type'].'/'.$row->userid.'/'.$row->campaign_id.'/'.$conditions['source'],
				':tip2' => 'Report on destination URL\'s for '.$row->user.' clicks for this campaign' ,
				':link2' => '/admin/reports/clicktracking/' . self::TRACKINGREPORTING_DETAIL_LEVEL2 . '/click/' . $row->campaign_id . '/' . $conditions['source'] . '/' . $row->userid
			  )
			);
			$this->data_rows[] = $dataRow;
		  }
		}

		break;

	  case self::TRACKINGREPORTING_DETAIL_LEVEL2:
		$this->header_rows = array(
		  'source' => array('data' => t('Source'), 'style' => 'width: 10%;','field'=>'1'),
		  'url' => array('data' => t('Destination URL'), 'style' => 'width: 40%;','field'=>'4'),
		  'events' => array('data' => t('Events'), 'style' => 'width: 10%;','field'=>'5'),
		  'last' => array('data' => t('Latest Event Date'), 'style' => 'width: 15%;','field'=>'6'),
		  'actions' => array('data' => t(''), 'style' => 'width: 25%;'),
		);

		$query = \Drupal::database()->select('tm_tracking','tt')->fields('tt', array('campaign_id','source','track_type','destinationURL'));
		$query->leftjoin('users','u','tt.user_id=u.uid');
		$query->join('users_field_data','ud','u.uid=ud.uid');
		$query->addField('tt','user_id','userid');
		$query->addExpression('sum(tt.count)','count');
		$query->addExpression('max(tt.timestamp)','timestamp');
		$query->addExpression('max(ud.name)','user');
		foreach($conditions as $field=>$value) $query->condition($field,$value,"=");
		$result = $query->groupBy('tt.source')
		  ->groupBy('tt.destinationURL')
		  ->orderBy($column, $order)
		  ->execute();

		while ($row=$result->fetchObject()){
		  if($row->user=="") $row->user="Anonymous";
		  if($row->campaign_id!='MessageName') {
			/* build the table */
			$dataRow = array();
			$dataRow['source'] = $row->source;
			$dataRow['url'] = $row->destinationURL;
			$dataRow['events'] = $row->count . ' ' . $conditions['track_type'] . "s";
			$dataRow['last'] = date("D d-M-Y H:i", strtotime($row->timestamp));
			$dataRow['actions'] = new FormattableMarkup('<a href=":link" title=":tip"><i class="fa fa-times" style="color:red"></i>&nbsp;Delete :user\'s :types to this destination</a>',
			  array(
				':type'=>$conditions['track_type'],
				':user'=>$row->user,
				':tip' => 'Delete all tracked '.$row->source.' '.$conditions['track_type'].'\'s for '.$row->user.' for campaign '.$row->campaign_id,
				':link' => '/admin/secure/trackingdelete/'.$conditions['track_type'].'/'.$row->userid.'/'.$row->campaign_id.'/'.$conditions['source'].'?url='.urlencode($row->destinationURL),
			  )
			);
			$this->data_rows[] = $dataRow;
		  }
		  $this->username=($row->user == "" ? "Anonymous":$row->user);
		}

		break;
	}

	return $this;
  }

}