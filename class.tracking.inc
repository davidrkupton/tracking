<?php

/**
 * Class tracking
 * This class contains functionality for the tracking module.
 */
class Tracking{

    protected $_host ;

    /**
     * Make a private function so the host is not made until needed
     * @return string
     */
    static private function host(){
        return "//" . $_SERVER['HTTP_HOST'];
    }

    static public function imagefilepath(){
        return $_SERVER['DOCUMENT_ROOT'].'/'.drupal_get_path('module','tracking').'/images/';
    }
    static public function imageurlpath(){
        global $base_url;
        return $base_url.'/'.drupal_get_path('module','tracking').'/images/';
    }

    /**
     * @param string $user
     * @param string $campaign
     * @param string $source
     */
    static public function track_impression($user, $campaign, $source){

        $arrVals = array(
            "@source" => substr(strtolower($source),0,3),
            "@campaign" => $campaign,
            "@user" => $user,
            "@email" => (!empty($_REQUEST['src']) ? $_REQUEST['src'] : NULL),
            "@url" => null,
            "@ipaddress" => $_SERVER['REMOTE_ADDR'],
            "@hosttype" => $_SERVER['HTTP_USER_AGENT']
        );

        switch (strtolower($source)){       //can override parameters if necessary
            case "sf": break;               // email came from Salesforce
            case "web": break;              // email came from website mail functions
            case "ext": break;              // email came from external mail functions (e.g. mailchimp)
        }
        $query = db_select('tm_tracker','tt')
            ->fields('tt', array('recid','count'))
            ->condition('tt.track_type','open')
            ->condition('tt.source',$arrVals['@source'])
            ->condition('tt.campaign_id',$arrVals['@campaign'])
            ->condition('tt.user_id',$arrVals['@user']);

        if($result = $query->execute()->fetchObject()){
            // record found, update the counter
            $arrVals['@count']=($result->count)+1;
            $arrVals['@recid']=$result->recid;
            $num = db_update('tm_tracker')
                ->fields(array('count'=>$arrVals['@count']))
                ->condition('recid',$arrVals['@recid'],"=")
                ->execute();
            if($num>0){
                db_insert('tm_tracker_log')
                    ->fields(array(
                        'recid'         => $arrVals['@recid'],
                        'ipaddress'     =>$arrVals['@ipaddress'],
                        'hosttype'      =>$arrVals['@hosttype'],
                        'email'         =>$arrVals['@email'],
                    ))
                    ->execute();
            }
        } else {
            // record not found, create it
            $arrVals['@recid']=db_insert('tm_tracker')
                ->fields(array(
                    'track_type'        =>'open',
                    'source'            =>$arrVals['@source'],
                    'campaign_id'       =>$arrVals['@campaign'],
                    'user_id'           =>$arrVals['@user'],
                    'destinationURL'    =>$arrVals['@url'],
                    'count'             =>1
                ))
                ->execute();
            if(isset($arrVals['@recid']) && is_numeric($arrVals['@recid'])) db_insert('tm_tracker_log')
                ->fields(array(
                    'recid'         => $arrVals['@recid'],
                    'ipaddress'     =>$arrVals['@ipaddress'],
                    'hosttype'      =>$arrVals['@hosttype'],
                    'email'         =>$arrVals['@email'],
                ))
                ->execute();
        }

    }

    static public function track_click($user, $campaign, $source, $url){

        $arrVals = array(
            "@source" => substr(strtolower($source),0,3),
            "@campaign" => $campaign,
            "@user" => $user,
            "@email" => (!empty($_REQUEST['src']) ? $_REQUEST['src'] : NULL),
            "@url" => $url,
            "@ipaddress" => $_SERVER['REMOTE_ADDR'],
            "@hosttype" => $_SERVER['HTTP_USER_AGENT']
        );

        switch (strtolower($source)){       //can override parameters if necessary
            case "sf": break;               // email came from Salesforce
            case "web": break;              // email came from website mail functions
            case "ext": break;              // email came from external mail functions (e.g. mailchimp)
        }

        $query = db_select('tm_tracker','tt')
            ->fields('tt', array('recid','count'))
            ->condition('tt.track_type','click')
            ->condition('tt.source',$arrVals['@source'])
            ->condition('tt.campaign_id',$arrVals['@campaign'])
            ->condition('tt.destinationURL',$arrVals['@url'])
            ->condition('tt.user_id',$arrVals['@user']);

        if($result=$query->execute()->fetchObject()){
            // record found, update the counter
            $arrVals['@count']=($result->count)+1;
            $arrVals['@recid']=$result->recid;
            $num = db_update('tm_tracker')
                ->fields(array('count'=>$arrVals['@count']))
                ->condition('recid',$arrVals['@recid'],"=")
                ->execute();
            if($num > 0){
                db_insert('tm_tracker_log')
                    ->fields(array(
                        'recid'         => $arrVals['@recid'],
                        'ipaddress'     =>$arrVals['@ipaddress'],
                        'hosttype'      =>$arrVals['@hosttype'],
                        'email'         =>$arrVals['@email'],
                    ))->execute();
                return true;
            }
            // something failed in the update
            return false;
        }
        else {
            // record not found, create it
            $arrVals['@recid'] = db_insert('tm_tracker')
                ->fields(array(
                    'track_type'        =>'click',
                    'source'            =>$arrVals['@source'],
                    'campaign_id'       =>$arrVals['@campaign'],
                    'user_id'           =>$arrVals['@user'],
                    'destinationURL'    =>$arrVals['@url'],
                    'count'             =>1
                ))
                ->execute();
            if(isset($arrVals['@recid']) && is_numeric($arrVals['@recid'])) {
                db_insert('tm_tracker_log')
                    ->fields(array(
                        'recid' => $arrVals['@recid'],
                        'ipaddress' => $arrVals['@ipaddress'],
                        'hosttype' => $arrVals['@hosttype'],
                        'email' => $arrVals['@email'],
                    ))->execute();
                return true;
            }
            // something failed in the insert ...
            return false;
        }
    }

    /**
     * Makes a standard comment which can be placed on drupal forms.
     * @return string
     */
    static public function makeFooter($render=true){
        if(!isset($render)) $render = true;
        drupal_add_library('system', 'drupal.collapse');
        $files = array_diff(scandir('sites/all/modules/tm_utilities/includes/tracking/images'), array('..', '.'));
        $output = array('footer' => array(
            '#type' => 'fieldset',
            '#title' => t('Tracking (Usage Notes)'),
            '#description'=>"<p style='font-size:85%;color:#333'>
                <b>Source explanation:</b><br/>
                Source defines where an email (or clickable link) has originated.<br/>
                <span style='margin-left:10px'>'web' means a track from a TraderMade website or TraderMade originated email.</span><br/>
                <span style='margin-left:10px'>'sf' means a track from the tradermade SalesForce app.</span><br/>
                <span style='margin-left:10px'>'ext' means a track from some other external website or externally-originated email campaign.</span><br/><br/>
                <b>To create trackable links:</b><br/>
                Trackable links may be created as follows:<br>
                <span style='margin-left:10px'><b>- Track when something is viewed (opened)</b>: create an image as follows:</span><br>
                <span style='margin-left:20px'>&lt;img src='".self::host()."/mail/open/%1/%2/%3/%4?src=%5'&gt;</span><br>
                <span style='margin-left:20px'>where %1=userid, %2=campaignid, %3=source(ext/web/sf)</span><br/>
                <span style='margin-left:25px'>%4=image (".implode("/",$files).")</span><br/>
                <span style='margin-left:25px'>(optional) %5=recipients email address</span><br/>
                <span style='margin-left:10px'><b>- Track when something is clicked</b>: create a link as follows:</span><br>
                <span style='margin-left:20px'>&lt;a href='".self::host()."/mail/clicker/%1/%2/%3/click?url=%4'&gt;&lt;/a&gt;</span><br>
                <span style='margin-left:20px'>where %1=userid, %2=campaignid, %3=source(ext/web/sf), %4=link to follow</span><br/>
                <span style='margin-left:25px'><b>Note:</b> link must be prefixed 'http://' or else will be relative to [http://".$_SERVER['HTTP_HOST']."]</span>
                </p>",
            '#tree' => true,
            '#collapsible' => false,
            '#collapsed' => false,
            '#attributes' => array (
                'class' => array(
                    'collapsible', 'collapsed'
                )
            )
        ));
        if(!$render) return $output;
        else {
            if(!function_exists("drupal_render"))
                throw new Exception("Drupal environment not initialized.");
            return drupal_render($output);
        }
    }
}

/**
 * Class TrackingReporting
 * This class contains functionality to return the data needed to theme a report table in a drupal node.
 */
class TrackingReporting extends Tracking{

    const TRACKINGREPORTING_DETAIL_LEVEL0 = 0;
    const TRACKINGREPORTING_DETAIL_LEVEL1 = 1;
    const TRACKINGREPORTING_DETAIL_LEVEL2 = 2;
    static public $report_detail_level = array(
        TrackingReporting::TRACKINGREPORTING_DETAIL_LEVEL0 => 0,
        TrackingReporting::TRACKINGREPORTING_DETAIL_LEVEL1 => 1,
        TrackingReporting::TRACKINGREPORTING_DETAIL_LEVEL2 => 2
    );

    public $header_rows = array();
    public $data_rows= array();
    public $username;

    /**
     * Grabs top-level report data from the tm_tracker table in the database, and stores in 2 arrays.
     * @param string $column
     * @param string $order
     * @return $this
     */
    public function tracking_reporting($column = "campaign_id", $order="ASC"){

        if(!isset($column)) $column = "campaign_id";
        if(!isset($order))  $order = "ASC";

        $this->header_rows = array(
            array('data' => t('Campaign'), 'style' => 'width: 30%;','field'=>'2'),
            array('data' => t('Source'), 'style' => 'width: 10%;','field'=>'1'),
            array('data' => t('Activity'), 'style' => 'width: 10%;','field'=>'3'),
            array('data' => t('Events'), 'style' => 'width: 10%;','field'=>'4'),
            array('data' => t('Last date'), 'style' => 'width: 20%;','field'=>'5'),
            array('data' => t(''), 'style' => 'width: 20%;'),
        );

        $query = db_select('tm_tracker','tt')->fields('tt', array('campaign_id','source','track_type'));
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
                $dataRow[] = $row->campaign_id;
                $dataRow[] = $row->source;
                $dataRow[] = $row->track_type;
                $dataRow[] = $row->count . " " . $row->track_type . "s";
                $dataRow[] = date("D d-M-Y H:i", strtotime($row->timestamp));
                if($row->track_type=='click')
                    $dataRow[] = '<a href="/admin/reports/clicktracking/' . self::TRACKINGREPORTING_DETAIL_LEVEL1 . '/' . $row->track_type . '/' . $row->campaign_id . '/' . $row->source . '">details</a>';
                else $dataRow[] = '';
                $this->data_rows[] = $dataRow;
            }
        }
        return $this;
    }

    /**
     * @param int $detail_level
     * @param array $conditions
     * @param string $column
     * @param string $order
     * @return $this
     */
    public function tracking_reporting_detail($detail_level = self::TRACKINGREPORTING_DETAIL_LEVEL1, $conditions, $column=NULL, $order="ASC"){

        switch($detail_level){

            case TrackingReporting::TRACKINGREPORTING_DETAIL_LEVEL1:
                $this->header_rows = array(
                    array('data' => t('Source'), 'style' => 'width: 10%;','field'=>'1'),
                    array('data' => t('User'), 'style' => 'width: 40%;','field'=>'4'),
                    array('data' => t('Events'), 'style' => 'width: 10%;','field'=>'5'),
                    array('data' => t('Latest Event Date'), 'style' => 'width: 30%;','field'=>'6'),
                    array('data' => t(''), 'style' => 'width: 10%;'),
                );
                if(!$column) $column="user_id";
                $query = db_select('tm_tracker','tt')->fields('tt', array('campaign_id','source','track_type'));
                $query->leftJoin('users','u','tt.user_id=u.uid');
                $query->addField('tt','user_id','userid');
                $query->addField('u','name','user');
                foreach($conditions as $field=>$value) $query->condition($field,$value,"=");
                $query->addExpression('sum(tt.count)','count');
                $query->addExpression('max(tt.timestamp)','timestamp');
                $result = $query->groupBy('tt.source')
                    ->groupBy('tt.user_id')
                    ->orderBy($column, $order)
                    ->execute();

                while ($row=$result->fetchObject()){
                    if($row->campaign_id!='MessageName') {
                        /* build the table */
                        $dataRow = array();
                        $dataRow[] = $row->source;
                        $dataRow[] = ($row->user == "" ? "Anonymous" : "<a href='/user/" . $row->userid . "/view'>" . $row->user . "</a>");
                        $dataRow[] = $row->count . ' ' . $conditions['track_type'] . "s";
                        $dataRow[] = date("D d-M-Y H:i", strtotime($row->timestamp));
                        $dataRow[] = '<a href="/admin/reports/clicktracking/' . self::TRACKINGREPORTING_DETAIL_LEVEL2 . '/click/' . $row->userid . '/' . $row->campaign_id . '/'.$conditions['source'].'">details</a>';
                        $this->data_rows[] = $dataRow;
                    }
                }

                break;

            case TrackingReporting::TRACKINGREPORTING_DETAIL_LEVEL2:
                $this->header_rows = array(
                    array('data' => t('Source'), 'style' => 'width: 5%;','field'=>'1'),
                    array('data' => t('URL'), 'style' => 'width: 40%;','field'=>'4'),
                    array('data' => t('Events'), 'style' => 'width: 10%;','field'=>'5'),
                    array('data' => t('Latest Event Date'), 'style' => 'width: 15%;','field'=>'6'),
                );

                $query = db_select('tm_tracker','tt')->fields('tt', array('campaign_id','source','track_type','destinationURL'));
                $query->leftjoin('users','u','tt.user_id=u.uid');
                $query->addField('tt','user_id','userid');
                $query->addField('u','name','user');
                $query->addExpression('sum(tt.count)','count');
                $query->addExpression('max(tt.timestamp)','timestamp');
                foreach($conditions as $field=>$value) $query->condition($field,$value,"=");
                $result = $query->groupBy('tt.source')
                    ->groupBy('tt.destinationURL')
                    ->orderBy($column, $order)
                    ->execute();

                while ($row=$result->fetchObject()){
                    if($row->campaign_id!='MessageName') {
                        /* build the table */
                        $dataRow = array();
                        $dataRow[] = $row->source;
                        $dataRow[] = $row->destinationURL;
                        $dataRow[] = $row->count . ' ' . $conditions['track_type'] . "s";
                        $dataRow[] = date("D d-M-Y H:i", strtotime($row->timestamp));
                        $this->data_rows[] = $dataRow;
                    }
                    $this->username=($row->user == "" ? "Anonymous":$row->user);
                }

                break;
        }

        return $this;
    }

}