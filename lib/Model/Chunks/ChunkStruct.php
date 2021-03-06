<?php

class Chunks_ChunkStruct extends Jobs_JobStruct {

    /** @return Segments_SegmentStruct[]
     *
     */
    public function getSegments() {
        $dao = new Segments_SegmentDao( Database::obtain() );

        return $dao->getByChunkId( $this->id, $this->password );
    }

    public function isMarkedComplete( $params ) {
        $params = \Utils::ensure_keys( $params, array( 'is_review' ) );

        return Chunks_ChunkCompletionEventDao::isCompleted( $this, array( 'is_review' => $params[ 'is_review' ] ) );
    }

    /**
     * @return Translations_SegmentTranslationStruct[]
     */
    public function getTranslations() {
        $dao = new Translations_SegmentTranslationDao( Database::obtain() );

        return $dao->getByJobId( $this->id );
    }

    /**
     * @return Jobs_JobStruct
     */
    public function getJob() {
        // I'm doing this to keep the concepts of Chunk and Job as
        // separated as possible even though they share the same
        // database table.
        return new Jobs_JobStruct( $this->attributes() );
    }

    /**
     * getOpenThreadsCount
     *
     */
    public function getOpenThreadsCount() {
        $dao = new Comments_CommentDao() ;
        $openThreads = $dao->getOpenThreadsForProjects( array(
                $this->id_project
        ) ) ;

        $result = array_values( array_filter($openThreads, function($item) {
            return $this->id == $item['id_job'] && $this->password == $item['password'];
        }));

        if ( count( $result ) > 0 ) {
            return $result[0]['count'];
        } else {
            return 0;
        }
    }

    public function getQualityOverall() {
        $job_array = array(
                'new_words'         => $this->new_words,
                'draft_words'       => $this->draft_words,
                'translated_words'  => $this->translated_words,
                'approved_words'    => $this->approved_words,
                'rejected_words'    => $this->rejected_words,
                'status_analysis'   => $this->getProject()->status_analysis,
                'jid'               => $this->id,
                'jpassword'         => $this->password,
                'features'          => $this->getProject()->getMetadataValue('features')
        );

        return CatUtils::getQualityOverallFromJobArray( $job_array ) ;
    }

    public function getErrorsCount() {
        $dao = new \Translations\WarningDao() ;
        return $dao->getErrorsByChunk( $this );
    }
}
