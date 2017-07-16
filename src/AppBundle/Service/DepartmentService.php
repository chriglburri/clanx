<?php
namespace AppBundle\Service;

use Doctrine\ORM\EntityManager;
use AppBundle\Service\Authorization;
use AppBundle\Entity\Event;
use AppBundle\Entity\Department;

class DepartmentService
{
    /**
     * @var Doctrine\ORM\EntityManager
     */
    protected $entityManager;
    /**
     * @var AppBundle\Service\Authorization
     */
    protected $auth;
    /**
     * The repository for the Department entity
     * @var \Doctrine\ORM\EntityRepository
     */
    protected $repo;

    public function __construct(
        Authorization $authorization,
        EntityManager $em
    )
    {
        $this->auth = $authorization;
        $this->entityManager = $em;
        $this->repo = $em->getRepository('AppBundle:Department');
    }

    /**
     * Gets all the departments in the given event of which
     * the logged in user is chief
     * @param  Event $event the Event
     * @return Department[] Returns an array of department entities.
     */
    public function getMyDepartmentsAsChief(Event $event)
    {
        return $this->repo->findBy(
            array(Department::CHIEF_USER => $this->auth->getUser(), Department::EVENT => $event)
        );
    }

    /**
     * Gets all the departments in the given event of which
     * the logged in user is a deputy
     * @param  Event  $event the Event
     * @return Department[] Returns an array of department entities.
     */
    public function getMyDepartmentsAsDeputy(Event $event)
    {
        return $this->repo->findBy(
            array(Department::DEPUTY_USER => $this->auth->getUser(), Department::EVENT => $event)
        );
    }

    /**
     * Creates and returns a copy of all departments of the given event.
     * New departments are never locked.
     * New departments are not assiciated with an event.
     * Call EventService.setRelations() for this.
     * @param  Event  $event
     * @return Department[]
     */
    public function getCopyOfEvent(Event $event)
    {
        $newDepartments = array();
        foreach ($event->getDepartments() as $department) {
            $newDepartment = new Department();
            $newDepartment->setName($department->getName());
            $newDepartment->setRequirement($department->getRequirement());
            $newDepartment->setChiefUser($department->getChiefUser());
            $newDepartment->setDeputyUser($department->getDeputyUser());
            $newDepartment->setLocked(false);
            array_push($newDepartments,$newDepartment);
        }
        return $newDepartments;
    }
}

?>
