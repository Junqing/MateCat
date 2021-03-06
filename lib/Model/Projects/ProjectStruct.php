<?php

class Projects_ProjectStruct extends DataAccess_AbstractDaoSilentStruct implements DataAccess_IDaoStruct {
    public $id ;
    public $password ;
    public $name ;
    public $id_customer ;
    public $id_organization ;
    public $create_date ;
    public $id_engine_tm ;
    public $id_engine_mt ;
    public $status_analysis ;
    public $fast_analysis_wc ;
    public $standard_analysis_wc ;
    public $remote_ip_address ;
    public $for_debug ;
    public $pretranslate_100 ;
    public $id_qa_model ;
    public $id_assignee ;
    public $id_workspace ;


    /**
     * @return bool  
     */
    public function analysisComplete() {
        return
                $this->status_analysis == Constants_ProjectStatus::STATUS_DONE ||
                $this->status_analysis == Constants_ProjectStatus::STATUS_NOT_TO_ANALYZE ;
    }

    /**
     * @return Jobs_JobStruct[]
     */
    public function getJobs() {
        return $this->cachable(__function__, $this->id, function($id) {
            return Jobs_JobDao::getByProjectId( $id );
        });
    }

    /**
     * @return Projects_MetadataStruct[]
     */
    public function getMetadata() {
        return Projects_MetadataDao::getByProjectId( $this->id );
    }

    /**
     * Proxy to set metadata for the current project 
     */
    public function setMetadata($key, $value) {
        $dao = new Projects_MetadataDao( Database::obtain() );
        return $dao->set( $this->id, $key, $value);
    }

    /**
     *
     * @return array
     */
    public function getMetadataAsKeyValue() {
        $collection = $this->getMetadata();
        $data  = array();
        foreach ($collection as $record ) {
            $data[ $record->key ] = $record->value;
        }
        return $data;
    }


    /**
     * @param $key
     *
     * @return mixed
     */
    public function getMetadataValue($key) {
        $meta = $this->getMetadataAsKeyValue();
        if ( array_key_exists($key, $meta) ) {
            return $meta[$key];
        }
    }

    /**
     * @return null|\Organizations\OrganizationStruct
     */
    public function getOrganization() {
        if ( is_null( $this->id_organization ) ) {
            return null ;
        }
        $dao = new \Organizations\OrganizationDao() ;
        return $dao->findById( $this->id_organization ) ;
    }

    /**
     * @param $feature_code
     *
     * @return bool
     *
     * @deprecated feature enabled for a created project should be decided based on project metadata
     */
    public function isFeatureEnabled( $feature_code ) {
        return in_array($feature_code, $this->getFeatures()->getCodes() );
    }

    /**
     * @return FeatureSet
     */
    public function getFeatures() {
        return $this->cachable(__METHOD__, $this, function( Projects_ProjectStruct $project ) {
            $featureSet = new FeatureSet() ;
            $featureSet->loadForProject( $project );
            return $featureSet ;
        });
    }

    /**
     * @return Chunks_ChunkStruct[]
     */
    public function getChunks() {
      $dao = new Chunks_ChunkDao( Database::obtain() );
      return $dao->getByProjectID( $this->id );
    }

    public function isMarkedComplete() {
      return Chunks_ChunkCompletionEventDao::isProjectCompleted( $this );
    }

    /**
     * @return mixed|string
     */
    public function getWordCountType() {
        return $this->cachable(__METHOD__, $this->getMetadataValue('word_count_type'), function($type) {
            if ( $type == null ) {
                return Projects_MetadataDao::WORD_COUNT_EQUIVALENT;
            } else {
                return $type;
            }
        });
    }

    /**
     * @return \LQA\ModelStruct
     *
     */
    public function getLqaModel() {
        return $this->cachable(__METHOD__, $this->id_qa_model, function($id_qa_model) {
            return \LQA\ModelDao::findById( $id_qa_model ) ;
        });
    }

    /**
     * Most of the times only one zip per project is uploaded.
     * This method is a shortcut to access the zip file path.
     *
     * @return string the original zip path.
     *
     */
    public function getFirstOriginalZipPath() {
        $fs = new FilesStorage();
        $jobs = $this->getJobs();
        $files = Files_FileDao::getByJobId($jobs[0]->id);

        $zipName = explode( ZipArchiveExtended::INTERNAL_SEPARATOR, $files[0]->filename );
        $zipName = $zipName[0];

        $originalZipPath = $fs->getOriginalZipPath( $this->create_date, $this->id, $zipName );
        return $originalZipPath ;
    }

}
