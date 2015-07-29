<?php

class Comments_CommentDao extends DataAccess_AbstractDao {

  const TABLE = "comments";
  const STRUCT_TYPE = "Comments_CommentStruct";

  const TYPE_COMMENT = 1 ;
  const TYPE_RESOLVE = 2 ;

  public function saveComment( $input ) {
      if ($input->message_type == null) {
          $input->message_type = self::TYPE_COMMENT ;
      }

      $input->create_date = date( 'Y-m-d H:i:s' ) ;

      $obj = $this->sanitize( $input );

      $this->validateForCommentAndResolve($obj);

      $query = " INSERT INTO comments " .
          " ( " .
          " id_job, id_segment, create_date, email, full_name, uid, " .
          " source_page, message_type, message ) " .
          " VALUES " .
          " ( "  .
          implode(", ", array(
               $obj->id_job,
               $obj->id_segment,
               $obj->create_date,
               $obj->email,
               $obj->full_name,
               $obj->uid,
               $obj->source_page,
               $obj->message_type,
               $obj->message
          ) ) . " ) ";


      $this->con->query( $query );
      $this->_checkForErrors();

      return $input ;
  }

  public function resolveThread( $input ) {
      $input->message_type = self::TYPE_RESOLVE ;
      $input->resolve_date = date('Y-m-d H:i:s');

      $this->con->begin();

      try {
          $this->saveComment( $input );

          $obj = $this->sanitize( $input );

          $update = "UPDATE comments SET resolve_date = $obj->resolve_date " .
              " WHERE id_segment = $obj->id_segment " .
              " AND id_job = $obj->id_job " .
              " AND resolve_date IS NULL " ;

          Log::doLog( $update );

          $this->con->query( $update );
          $this->_checkForErrors();

          $this->con->commit();
      } catch ( Exception $e ) {
          $err = $this->con->get_error();
          Log::doLog( "Error: " . var_export( $err, true ) );
          $this->con->rollback();
      }

      $input->thread_id = $input->getThreadId() ;

      return $input ;
  }

  public function getThreadContributorUids( $input ) {
      $obj = $this->sanitize( $input );

      $query = "SELECT DISTINCT(uid) FROM " . self::TABLE  .
          " WHERE id_job = $obj->id_job " .
          " AND id_segment = $obj->id_segment " .
          " AND uid IS NOT NULL " ;

      if ( $input->uid ) {
          $query .= " AND uid <> $obj->uid " ;
      }

      Log::doLog( $query );

      $this->con->query( $query );

      $arr_result = $this->_fetch_array( $query );

      $this->_checkForErrors();
      return $arr_result ;
  }

  public function getOpenCommentsInJob( $input ) {
      $obj = $this->sanitize( $input );

      $query = $this->finderQuery() .
          " WHERE id_job = $obj->id_job " .
          // " AND resolve_date IS NULL " .
          // " AND ( id_segment < $obj->first_segment OR id_segment > $obj->last_segment ) " .
          " ORDER BY id_segment ASC, create_date ASC ";

      $this->con->query( $query );

      $arr_result = $this->_fetch_array( $query );

      $this->_checkForErrors();
      return $this->_buildResult( $arr_result );
  }

  // public function getCommentsBySegmentsRange( $input ) {
  //     $obj = $this->sanitize( $input );

  //     $query = $this->finderQuery() .
  //         " WHERE id_job = $obj->id_job " .
  //         " AND id_segment >= $obj->first_segment AND id_segment <= $obj->last_segment " .
  //         " ORDER BY id_segment ASC, create_date ASC ";

  //     $this->con->query( $query );

  //     $arr_result = $this->_fetch_array( $query );

  //     $this->_checkForErrors();
  //     return $this->_buildResult( $arr_result );
  // }

  private function finderQuery() {
      return "SELECT " .
          " id_job, id_segment, create_date, full_name, resolve_date, " .
          " source_page, message_type, message, email, " .
          " IF ( resolve_date IS NULL, NULL, MD5( CONCAT( id_job, '-', id_segment, '-', resolve_date ) ) ) AS thread_id " .
          " FROM " . self::TABLE ;
  }


  private function validateForCommentAndResolve($obj) {
      if ( empty($obj->message) && $obj->message_type == self::TYPE_COMMENT ) {
          throw new Exception( "Comment message can't be blank." );
      }
      if ( empty($obj->full_name) ) {
          throw new Exception( "Full name can't be blank." );
      }
  }

  protected function _buildResult( $array_result ) {
      $result = array();

      foreach ( $array_result as $item ) {
          $build_arr = array(
              'id_job'         => $item[ 'id_job' ],
              'id_segment'     => $item[ 'id_segment' ],
              'create_date'    => $item[ 'create_date' ],
              'full_name'      => $item[ 'full_name' ],
              'thread_id'      => $item[ 'thread_id' ],
              'email'          => $item[ 'email' ],
              'message_type'   => $item[ 'message_type' ],
              'message'        => $item[ 'message' ],
              'formatted_date' => self::formattedDate($item[ 'create_date' ])
          );

          $result[] = $build_arr ;
      }

      return $result ;
  }

  static function formattedDate($time) {
      return strftime('%l:%M %p %e %b %Y UTC', strtotime( $time ) ) ;
  }

  public function sanitize( $input ) {
      $cloned = clone $input;
      parent::_sanitizeInput( $input, self::STRUCT_TYPE );

      $cloned->id_job       = self::intWithNull( $input->id_job );
      $cloned->id_segment   = self::intWithNull( $input->id_segment );
      $cloned->uid          = self::intWithNull( $input->uid );
      $cloned->source_page    = self::intWithNull( $input->source_page );
      $cloned->message_type = self::intWithNull( $input->message_type );

      $cloned->first_segment = self::intWithNull( $input->first_segment );
      $cloned->last_segment  = self::intWithNull( $input->last_segment );

      $cloned->message      = self::escapeWithNull( trim( $input->message ) );
      $cloned->email        = self::escapeWithNull( $input->email );
      $cloned->full_name    = self::escapeWithNull( $input->full_name );
      $cloned->create_date  = self::escapeWithNull( $input->create_date );
      $cloned->resolve_date  = self::escapeWithNull( $input->resolve_date );

      return $cloned ;
  }

  private static function escapeWithNull($value) {
      $conn = self::getConnection();
      if ( $value !== null ) {
          return " '{$conn->escape( $value )}' " ;
      }
      else {
          return "NULL"  ;
      }
  }

  private static function intWithNull( $value ) {
      if ( $value === null ) {
          return "NULL"  ;
      } else {
          return (int) $value ;
      }
  }

  private static function getConnection() {
      // TODO: find connection configuration here, because comments
      // may reside on a different database
      //
      return Database::obtain();
  }


}
