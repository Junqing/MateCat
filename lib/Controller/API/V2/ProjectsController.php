<?php
/**
 * Created by PhpStorm.
 * User: fregini
 * Date: 13/02/2017
 * Time: 10:06
 */

namespace API\V2;


use API\App\AbstractStatefulKleinController;

use API\V2\Json\Project;
use API\V2\Validators\OrganizationAccessValidator;
use API\V2\Validators\OrganizationProjectValidator;

class ProjectsController extends KleinController {

    protected $project;

    public function update() {

        $acceptedFields = array( 'id_assignee', 'name', 'id_workspace' );

        $projectModel   = new \ProjectModel( $this->project );
        $projectModel->setUser( $this->user ) ;

        $putParams = $this->getPutParams();

        foreach ( $acceptedFields as $field ) {
            if ( array_key_exists($field, $putParams ) ) {
                $projectModel->prepareUpdate( $field, $putParams[ $field ] );
            }
        }

        $updatedStruct = $projectModel->update();
        $formatted     = new Project();
        $this->response->json( array( 'project' => $formatted->renderItem( $updatedStruct ) ) );

    }

    protected function afterConstruct() {
        parent::afterConstruct();
        $this->project = \Projects_ProjectDao::findById( $this->request->id_project );
        $this->appendValidator( new OrganizationAccessValidator( $this ) );
        $this->appendValidator( ( new OrganizationProjectValidator( $this ) )->setProject( $this->project ) );
    }

}