<?php



namespace Features\QaCheckBlacklist\Worker ;

use Features\QaCheckBlacklist;
use TaskRunner\Commons\AbstractElement ;
use TaskRunner\Commons\AbstractWorker ;
use TaskRunner\Commons\QueueElement ;

use TaskRunner\Exceptions\EndQueueException ;

use Translations\WarningModel ;
use Translations\WarningStruct ;
use Features\QaCheckBlacklist\BlacklistFromZip ;

class BlacklistWorker extends AbstractWorker {

    /** @var $queueElement QueueElement */
    protected $queueElement ;

    protected $matches ;

    public function process( AbstractElement $queueElement ) {

        $this->_checkDatabaseConnection();

        $this->queueElement  = $queueElement ;

        $this->_checkForReQueueEnd( $this->queueElement );

        if ( $queueElement->params->recheck_translation && $this->_translationChanged()  ) {
            return true;
        }

        $this->_updateWarnings();
    }


    protected function _translationChanged() {
        $translation = \Translations_SegmentTranslationDao::findBySegmentAndJob(
                $this->queueElement->params['id_segment'],
                $this->queueElement->params['id_job']
        );

        return $translation->translation != $this->queueElement->params['translation'] ;
    }

    protected function _updateWarnings( ) {
        $params = $this->queueElement->params ;

        $dao = new \Projects_MetadataDao() ;
        $has_blacklist = $dao->get( $params['id_project'],  'has_blacklist' ) ;

        if ( ! $has_blacklist ) {
            return ;
        }

        $job = \Jobs_JobDao::getById( $params['id_job'] );

        $blacklist = new BlacklistFromZip( $job->getProject()->getFirstOriginalZipPath(),  $job->id ) ;

        $this->matches = $blacklist->getMatches( $params['translation'] ) ;

        $this->_updateWarningsOnSegmentId( $params['id_job'], $params['id_segment'] ) ;

        if ( !empty( $this->queueElement->params['propagated_ids']) ) {
            $this->_propagateWarnings( ) ;
        }
    }

    protected function _propagateWarnings()  {
        foreach( $this->queueElement->params['propagated_ids'] as $id_segment ) {
            $this->_updateWarningsOnSegmentId($this->queueElement->params['id_job'], $id_segment ) ;
        }
    }

    protected function _updateWarningsOnSegmentId( $id_job, $id_segment ) {
        $warningModel = new WarningModel(
                $id_job, $id_segment
        );
        $warningModel->start();
        $warningModel->resetScope( QaCheckBlacklist::BLACKLIST_SCOPE );

        foreach( $this->matches as $match => $count ) {
            for ($k = 0 ; $k < $count; $k++) {
                $warning = new WarningStruct(array(
                        'id_job'     => $id_job,
                        'id_segment' => $id_segment,
                        'severity'   => WarningModel::WARNING,
                        'scope'      => QaCheckBlacklist::BLACKLIST_SCOPE,
                        'data'       => json_encode( array( 'match' => $match ) )
                ));
                $warningModel->addWarning( $warning );
            }
        }

        $warningModel->save() ;
    }

    protected function _checkForReQueueEnd( QueueElement $queueElement ){
        if ( isset( $queueElement->reQueueNum ) && $queueElement->reQueueNum >= 100 ) {
            $this->_doLog( "--- (Worker " . $this->_workerPid . ") : Frame Re-queue max value reached, acknowledge and skip." );
            throw new EndQueueException( "--- (Worker " . $this->_workerPid . ") :  Frame Re-queue max value reached, acknowledge and skip.", self::ERR_REQUEUE_END );

        } elseif ( isset( $queueElement->reQueueNum ) ) {
            $this->_doLog( "--- (Worker " . $this->_workerPid . ") :  Frame re-queued {$queueElement->reQueueNum} times." );
        }
    }
}