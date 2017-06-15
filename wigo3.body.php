<?php

class ApiWigo extends ApiQueryBase {
  public function __construct($query, $moduleName) {
    parent::__construct($query,$moduleName,'wigo');
  }
  
  public function execute() {
    $db = $this->getDB();
    $params = $this->extractRequestParams();

    $month = $params['month'];
    if (!is_null($month))
    {
      $month = str_pad($month,2,'0',STR_PAD_LEFT);
    }
    
    $year = $params['year'];
    $limit = $params['limit'];
    $start = $params['start'];
    $prefix = $params['prefix'];

    $this->addTables('wigovote');
    
    //"rev_timestamp like \"{$this->year}{$this->month}%\""
    
    if (!is_null($year) && !is_null($month)) {
      $this->addWhere( 'timestamp' . $this->getDB()->buildLike( "{$year}{$month}", $this->getDB()->anyString() ) );
    } elseif (!is_null($year)) {
      $this->addWhere( 'timestamp' . $this->getDB()->buildLike( "{$year}", $this->getDB()->anyString() ) );
    } elseif (!is_null($month)) {
      $this->addWhere( 'timestamp' . $this->getDB()->buildLike( $this->getDB()->anyChar(), $this->getDB()->anyChar(), $this->getDB()->anyChar(), $this->getDB()->anyChar(),
                                                                    $month, $this->getDB()->anyString() ) );
    }

    $this->addFields( array('id', 'sum(vote) AS sum', 'count(vote) AS count') );
    $this->addOption('GROUP BY', 'id');

    
    $this->addWhereRange('id','newer',$start,null);
    if ( !is_null($prefix) ) {
      $this->addOption( 'ORDER BY', "0 + substring(id," . strlen($prefix) . "+1)" );
    }
    $this->addWhere('id' . $this->getDB()->buildLike( $prefix, $this->getDB()->anyString() ) );
    $this->addWhereRange('vote','newer','-1','+1',false);

    $this->addOption('LIMIT', $limit + 1);
    
    $res = $this->select(__METHOD__);

    $count = 0;
    $result = $this->getResult();
    $data = array();
    
    foreach ( $res as $row ) {
        if ( ++ $count > $limit ) {
            // We've reached the one extra which shows that there are additional pages to be had. Stop here...
            $this->setContinueEnumParameter( 'start', $row->id );
            break;
        }

        $data['id'] = $row->id;
        $data['sum'] = $row->sum;
        $data['count'] = $row->count;
        $fit = $result->addValue( array( 'query', $this->getModuleName() ), null, $data );
        if ( !$fit ) {
            $this->setContinueEnumParameter( 'start', $row->id );
            break;
        }
    }

    $db->freeResult($res);
    
    $result->setIndexedTagName_internal( array( 'query', $this->getModuleName() ), 'entry' );
  }
  
  public function getAllowedParams() {
    return array (
      'month' => null,
      'year' => null,
      'prefix' => null,
      'start' => null,
      'limit' => array (
        ApiBase :: PARAM_DFLT => 10,
        ApiBase :: PARAM_TYPE => 'limit',
        ApiBase :: PARAM_MIN => 1,
        ApiBase :: PARAM_MAX => ApiBase :: LIMIT_BIG1,
        ApiBase :: PARAM_MAX2 => ApiBase :: LIMIT_BIG2
      )
    );
  }
  
  public function getParamDescription() {
    return array (
      'month' => 'Only count votes from this month',
      'year' => 'Only count votes from this year',
      'prefix' => 'Return entries whose ids begin with this value',
      'start' => 'Start from this id',
      'limit' => 'How many total entries to return',
    );
  }
  
  public function getDescription() {
    return 'Get wigo entries';
  }
  
  public function getExamples() {
    return array(
      'api.php?action=query&list=wigo',
      'api.php?action=query&list=wigo&wigoprefix=wigo&wigostart=wigo3500',
    );
  }
  
  public function getVersion() {
    return "1.0";
  }
}

class ApiWigoVotes extends ApiQueryBase {
  public function __construct($query, $moduleName) {
    parent::__construct($query,$moduleName,'wv');
  }
  
  public function execute() {
    $db = $this->getDB();
    $params = $this->extractRequestParams();

    $month = $params['month'];
    if (!is_null($month))
    {
      $month = str_pad($month,2,'0',STR_PAD_LEFT);
    }
    
    $year = $params['year'];
    $limit = $params['limit'];
    $start =  $params['start'];
    $end =  $params['end'];
    $id = $params['id'];
    $prefix = $params['prefix'];
    $dir = $params['dir'];
    $min = $params['min'];
    $max = $params['max'];

    $this->addTables('wigovote');
    
    // Handle continue parameter
    if ( !is_null( $params['continue'] ) ) {
      $continue = explode( '|', $params['continue'] );
      if ( count( $continue ) != 2 ) {
        $this->dieUsage( 'Invalid continue param. You should pass the original ' .
                         'value returned by the previous query', '_badcontinue' );
      }
      $encId = $this->getDB()->strencode( $continue[0] );
      $encVoter = $this->getDB()->strencode( $continue[2] );
      $encTS = wfTimestamp( TS_MW, $continue[1] );
      $op = ( $dir == 'older' ? '<' : '>' );
      $this->addWhere(
        "timestamp $op '$encTS' OR " .
        "(timestamp = '$encTS' AND " .
          "(id $op= '$encId'" .
          ")" .
        ")"
      );
    }


    //"rev_timestamp like \"{$this->year}{$this->month}%\""
    
    if (!is_null($year) && !is_null($month)) {
      $this->addWhere( 'timestamp' . $this->getDB()->buildLike( "{$year}{$month}", $this->getDB()->anyString() ) );
    } elseif (!is_null($year)) {
      $this->addWhere( 'timestamp' . $this->getDB()->buildLike( "{$year}", $this->getDB()->anyString() ) );
    } elseif (!is_null($month)) {
      $this->addWhere( 'timestamp' . $this->getDB()->buildLike( $this->getDB()->anyChar(), $this->getDB()->anyChar(), $this->getDB()->anyChar(), $this->getDB()->anyChar(),
                                                                    $month, $this->getDB()->anyString() ) );
    }

    $this->addFields( array('id', 'vote', 'timestamp') );
    
    $this->addWhereRange('timestamp',$dir,$start,$end);
    if ( !is_null($prefix) ) {
      $this->addWhere('id' . $this->getDB()->buildLike( $prefix, $this->getDB()->anyString() ) );
      $this->addWhereRange( 'id', $dir, null, null );
    } elseif ( !is_null($id) ) {
      $this->addWhere('id' . $this->getDB()->buildLike( $id ) );
    }
    
    $this->addWhereRange('vote','newer',$min,$max,false);

    $this->addOption('LIMIT', $limit + 1);
    
    $res = $this->select(__METHOD__);

    $count = 0;
    $result = $this->getResult();
    $data = array();
    
    foreach ( $res as $row ) {
        if ( ++ $count > $limit ) {
            // We've reached the one extra which shows that there are additional pages to be had. Stop here...
            $this->setContinueEnumParameter( 'continue', $this->continueStr( $row ) );
            break;
        }

        $data['id'] = $row->id;
        $data['timestamp'] = wfTimestamp( TS_ISO_8601, $row->timestamp );
        $data['value'] = $row->vote;
        $fit = $result->addValue( array( 'query', $this->getModuleName() ), null, $data );
        if ( !$fit ) {
            $this->setContinueEnumParameter( 'continue', $this->continueStr( $row ) );
            break;
        }
    }

    $db->freeResult($res);
    
    $result->setIndexedTagName_internal( array( 'query', $this->getModuleName() ), 'vote' );
  }

  private function continueStr( $row ) {
    return $row->id . '|' . wfTimestamp( TS_ISO_8601, $row->timestamp );
  }
  
  public function getAllowedParams() {
    return array (
      'month' => null,
      'year' => null,
      'id' => null,
      'prefix' => null,
      'continue' => null,
      'start' => array (
        ApiBase::PARAM_TYPE => 'timestamp'
      ),
      'end' => array (
        ApiBase::PARAM_TYPE => 'timestamp'
      ),
      'min' => null,
      'max' => null,
      'limit' => array (
        ApiBase :: PARAM_DFLT => 10,
        ApiBase :: PARAM_TYPE => 'limit',
        ApiBase :: PARAM_MIN => 1,
        ApiBase :: PARAM_MAX => ApiBase :: LIMIT_BIG1,
        ApiBase :: PARAM_MAX2 => ApiBase :: LIMIT_BIG2
      ),
      'dir' => array(
        ApiBase::PARAM_DFLT => 'older',
        ApiBase::PARAM_TYPE => array(
          'newer',
          'older'
        )
      ),
    );
  }
  
  public function getParamDescription() {
    return array (
      'month' => 'Only count votes from this month',
      'year' => 'Only count votes from this year',
      'id' => 'Return votes for this entry',
      'prefix' => 'Return entries whose ids begin with this value. Overrides ' . $this->getModulePrefix() . 'id',
      'start' => 'The start timestamp to return from',
      'end' => 'The end timestamp to return to',
      'continue' => 'When more results are available, use this to continue',
      'limit' => 'How many total entries to return',
      'dir' => 'The direction to search (older or newer)',
      'min' => 'Only return votes greater or equal to this',
      'max' => 'Only return votes lower or equal to this',
    );
  }
  
  public function getDescription() {
    return 'Get wigo votes';
  }
  
  public function getExamples() {
    return array(
      'api.php?action=query&list=wigovotes',
      'api.php?action=query&list=wigovotes&wvprefix=wigo&wvmin=-1&wvmax=1',
      'api.php?action=query&list=wigovotes&wvid=wigo3334&wvstart=2010-04-13T11:07:38Z&wvlimit=10&wvdir=newer'
    );
  }
  
  public function getVersion() {
    return "1.0";
  }
}
 
