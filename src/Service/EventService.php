<?php
namespace App\Service;

use Doctrine\ORM\EntityManager;
use Doctrine\ORM\QueryBuilder;
use App\Entity\Event;
use App\Entity\Commitment ;
use App\Entity\Answer ;
use App\ViewModel\Commitment\CommitmentViewModel;
use App\ViewModel\Commitment\YesNoQuestionViewModel;
use App\ViewModel\Commitment\TextQuestionViewModel;
use App\ViewModel\Commitment\SelectionQuestionViewModel;
use App\ViewModel\Event\EventStatisticsViewModel;
use App\ViewModel\Event\EventShowViewModel;

class EventService implements IEventService
{
    /**
     * @var Doctrine\ORM\EntityManager
     */
    protected $entityManager;

    /**
     * @var App\Service\IAuthorizationService
     */
    protected $authorization;

    /**
     * The repository for the Event entity
     * @var \Doctrine\ORM\EntityRepository
     */
    protected $repo;

    /**
     * @var App\Service\IDepartmentService
     */
    protected $departmentService;

    /**
     * @var App\Service\ICommitmentService
     */
    protected $commitmentService;

    /**
     * @var IQuestionService
     */
    protected $questionService;

    public function __construct(
        EntityManager $em,
        IAuthorizationService $auth,
        IDepartmentService $deptService,
        ICommitmentService $cmmtService,
        IQuestionService $questionService
    )
    {
        $this->entityManager = $em;
        $this->authorization = $auth;
        $this->repo = $em->getRepository(Event::class);
        $this->departmentService = $deptService;
        $this->commitmentService = $cmmtService;
        $this->questionService = $questionService;
    }

    /**
     * Gets all the upcoming events which the current user may see,
     * ordered ascending (next event on top).
     * If the event is on the current date, it counts as 'upcoming'.
     * @return App\Entity\Event[] returns an array of Event objects.
     */
    public function getUpcoming()
    {
        $qb = $this->repo->createQueryBuilder('e');

        $assocExpression = $this->getAssociationExpression($qb, 'e');
        $visibleExpression = $this->getVisibleExpression($qb, 'e');
        $dateExpression = $qb->expr()->gte('e.date', ':today');
        $qb->setParameter('today',new \DateTime("now"));

        $query = $qb
                ->where($dateExpression)
                ->andWhere($assocExpression)
                ->andWhere($visibleExpression)
                ->orderBy('e.date', 'ASC')
                ->getQuery();

        return $query->getResult();
    }
    /**
     * Gets all the passed events which the current user may see,
     * ordered descending (youngest event on top).
     * events occuring on the current date do **not** count as 'passed'.
     * @return App\Entity\Event[] returns an array of Event objects.
     */
    public function getPassed()
    {
        $qb = $this->repo->createQueryBuilder('e');

        $assocExpression = $this->getAssociationExpression($qb, 'e');
        $visibleExpression = $this->getVisibleExpression($qb, 'e');
        $dateExpression = $qb->expr()->lt('e.date', ':today');
        $qb->setParameter('today',new \DateTime("now"));

        $query = $qb
                ->where($dateExpression)
                ->andWhere($assocExpression)
                ->andWhere($visibleExpression)
                ->orderBy('e.date', 'DESC')
                ->getQuery();

        return $query->getResult();
    }

    /**
     * Collects all necessary data to fill in the "show" view of the Event page
     * @param  Event  $event
     * @return EventShowViewModel
     */
    public function getDetailViewModel(Event $event)
    {
        $myDepartmentsAsChief = $this->departmentService->getMyDepartmentsAsChief($event);
        $myDepartmentsAsDeputy = $this->departmentService->getMyDepartmentsAsDeputy($event);

        $enrolledCount = $this->CountVolunteersFor($event);
        $myCommitments = $this->commitmentService->getCurrentUsersCommitmentsFor($event);
        if (!$myCommitments) {
            $mayEnroll = $this->authorization->mayEnroll($event);
        } else {
            $mayEnroll = false;
        }


        $mayMail = $this->authorization->maySendEventMassMail();
        $mayInvite = $this->authorization->maySendInvitation($event);
        $mayEdit = $this->authorization->mayEditEvent();
        $deleteAuth = $this->authorization->mayDelete($event);

        $mayDownload = $this->authorization->mayDownloadFromEvent();
        $mayCopy = $this->authorization->mayCopyEvent();

        $vm = new EventShowViewModel();
        $vm ->setEvent($event)
        ->setMayEnroll($mayEnroll)
        ->setEnrolledCount($enrolledCount) //todo: fix for multiple commitments
        ->setCommitments($myCommitments)
        ->setMayMail($mayMail)
        ->setMayInvite($mayInvite)
        ->setMayEdit($mayEdit)
        ->setMayDelete($deleteAuth[AuthorizationService::VALUE])
        ->setMayDeleteMessage($deleteAuth[AuthorizationService::MESSAGE])
        ->setMayDownload($mayDownload)
        ->setMayCopy($mayCopy)
        ->setMyDepartmentsAsChief($myDepartmentsAsChief)
        ->setMyDepartmentsAsDeputy($myDepartmentsAsDeputy);

        return $vm;
    }

    /**
     * Gets the number of people working for the given event.
     * We only count people, not commitments.
     * When the same person works in 3 departments, he still
     * counts as 1 volunteer.
     * @param  Event  $event The Eventg
     * @return integer Returns the number of commitments.
     */
    public function CountVolunteersFor(Event $event)
    {

        $cmmtRepo = $this->entityManager->getRepository(Commitment::class);
        $commitments = $cmmtRepo->findByEvent($event);

        $userIds = array();
        $count=0;
        foreach ($commitments as $c) {
            $uid = (string)$c->getUser()->getId();
            if(! isset($userIds[$uid]))
            {
                $count++;
                $userIds[$uid] = 1;
            }
        }

        $departments = $event->getDepartments();
        foreach ($departments as $dpmt) {
            $count += count($dpmt->getCompanions());
        }
        return $count;
    }

    /**
     * get all sticky events that are in the
     * future or the very near past (1 week or so)
     * @return Event[] Returns an array of event entities.
     */
    public function getEventsForMenu()
    {
        $oneWeekInterval = new \DateInterval('P7D');
        $oneWeekInterval->invert=1; // negative interval. one week back.
        $aWeekAgo = new \DateTime();
        $aWeekAgo->add($oneWeekInterval);

        $qb = $this->repo->createQueryBuilder('e');
        $stickyExpr = $qb->expr()->eq('e.sticky',1);

        $qb->setParameter(':aWeekAgo',$aWeekAgo);
        $aWeekAgoExpr = $qb->expr()->gt('e.date',':aWeekAgo');

        $associationExpression = $this->getAssociationExpression($qb, 'e');
        $visibleExpression = $this->getVisibleExpression($qb, 'e');

        $query = $qb
            ->where($stickyExpr)
            ->andWhere($aWeekAgoExpr)
            ->andWhere($associationExpression)
            ->andWhere($visibleExpression)
            ->orderBy('e.date', 'ASC')
            ->getQuery();

        return $query->getResult();
    }

    /**
     * @param  Event  $event
     * @return CommitmentViewModel
     */
    public function getCommitmentFormViewModel(Event $event)
    {
        $commitmentVM = new CommitmentViewModel();
        foreach ($event->getQuestions() as $q) {
            $qVM = $this->questionService->getQuestionViewModel($q);
            $commitmentVM->addQuestion($qVM);
        }
        return $commitmentVM->setDepartments($event->getFreeDepartments()); // TODO: dont make this on the entity. get it from a service or here.
    }

    public function getCommitmentFormViewModelForEdit(Commitment $commitment)
    {
        $commitmentVM = new CommitmentViewModel();
        foreach ($commitment->getEvent()->getQuestions() as $q) {
            $a = $commitment->getAnswers()->filter(
                    function($answer) use ($q) {return $answer->getQuestion()->getId()==$q->getId();}
            )->first();
            if(!$a){
                $qVM = $this->questionService->getQuestionViewModel($q);
            } else {
                $qVM = $this->questionService->getQuestionViewModel($q, $a);
            }

            $commitmentVM->addQuestion($qVM);
        }
        $commitmentVM->setDepartments($commitment->getEvent()->getDepartments());
        $commitmentVM->setDepartment($commitment->getDepartment());
        $commitmentVM->setUser($commitment->getUser());
        $commitmentVM->setId($commitment->getId());
        return $commitmentVM;
    }

    public function getStatisticsViewModels(Event $event)
    {
        $viewModels = array();

        $qs = $event->getQuestions();
        foreach ($qs as $q) {
            if($q->getAggregate()){
                $viewModel = new EventStatisticsViewModel();
                $viewModel->setText($q->getText());
                $viewModel->setValues($this->questionService->countAnswers($q));
                $viewModels[] = $viewModel;
            }
        }
        return $viewModels;
    }

    /**
     * Creates a copy of the given event and returns int.
     * A copy is always invisible and not locked.
     * @param  Event  $event
     * @return Event
     */
    public function getCopy(Event $event)
    {
        $newEvent = new Event();
        $newEvent->setName('Kopie von "'.$event->getName().'"');
        $newEvent->setDate($event->getDate());
        $newEvent->setSticky($event->getSticky());
        $descAddition = 'Dieser Event ist eine Kopie.'.PHP_EOL
            .'Bearbeite Datum, Name und Beschreibung'.PHP_EOL
            .'Der Event ist nicht sichtbar für User. Schalte ihn sichtbar wenn du bereit bist.'.PHP_EOL
            .PHP_EOL;
        $newEvent->setDescription($descAddition.$event->getDescription());
        $newEvent->setLocked(false);
        $newEvent->setIsForAssociationMembers($event->getIsForAssociationMembers());
        $newEvent->setIsVisible(false);
        return $newEvent;
    }

    /**
     * Sets the relations betwenn the given event and the given departments / questions
     * @param Event $event
     * @param array $departments
     * @param array $questions
     */
    public function setRelations(Event $event, $departments=array(), $questions=array())
    {
        foreach ($departments as $department) {
            $department->setEvent($event);
        }
        foreach ($questions as $question) {
            $question->setEvent($event);
        }
    }

    public function getRowsForDownloadDepartment($department)
    {
        return $this->getRowsForDownload_internal($department->getEvent(), $department->getId(), true);
    }

    public function getRowsForDownload($event)
    {
        return $this->getRowsForDownload_internal($event);
    }

    /**
     * returns a query expression to filter the assiciationMember field.
     * When the user may see all events, the expression is simply '1=1',
     * which evaluates to 'true' in the query (suitable for AND, but not for OR).
     * @param  QueryBuilder $qb The QueryBuilder object.
     * @param string $alias The alias for the event table.
     * @return Doctrine\ORM\Query\Expr Returns an expression object.
     */
    private function getAssociationExpression(QueryBuilder $qb, $alias)
    {
        $p = 0;
        if ($this->authorization->isAssociationMember()) {
            $p = 1;
        }

        if ($this->authorization->maySeeAllEvents()) {
            return $qb->expr()->eq(1,1);
        } else {
            // user.isMember = 1 --> may see event.forMembers = 0/1
            // user.isMember = 0 --> may only see event.forMembers = 0
            // hence event.forMembers <= user.isMember
            //event.isForAssociationMembers <= :userIsMember'
            $qb->setParameter(':userIsMember', $p);
            return $qb->expr()->lte($alias . '.isForAssociationMembers',':userIsMember');
        }
    }

    private function getVisibleExpression(QueryBuilder $qb, $alias)
    {
        if ($this->authorization->maySeeAllEvents()) {
            return $qb->expr()->eq(1,1);
        } else {
            $paramName = ':eventIsVisible';
            $qb->setParameter($paramName, 1);
            return $qb->expr()->eq($alias . '.isVisible',$paramName);
        }
    }

    private function getRowsForDownload_internal($event, $departmentFilterId=0, $doFilterDepartments=false)
    {
        $rows = array();
        // Part 1: add all registered volunteers
        foreach ($event->getCommitments() as $commitment) {
            if($this->shouldSkipDepartment($commitment->getDepartment(),$departmentFilterId,$doFilterDepartments)){
                continue;
            }
            $user=$commitment->getUser();
            $row = array(
                (string)$commitment->getDepartment(),
                'Ja', // registered volunteeres
                $user->getSurname() ,
                $user->getForename() ,
                $user->getGender() ,
                $user->getDateOfBirth()?$user->getDateOfBirth()->format('d.m.Y'):"" ,
                $user->getStreet() ,
                $user->getZip() ,
                $user->getCity() ,
                $user->getEmail() ,
                $user->getPhone() ,
                $user->getOccupation() ,
                $user->getIsRegular()==1?"Ja":"Nein"
            );
            foreach ($this->questionService->getQuestionsAndAnswersSorted($commitment) as $q) {
                array_push($row, $q->getAnswer());
            }
            array_push($rows, $row);
        }
        // Part 2 add all companions
        foreach ($event->getDepartments() as $department) {
            if($this->shouldSkipDepartment($department,$departmentFilterId,$doFilterDepartments)){
                continue;
            }
            $companions = $department->getCompanions();
            foreach ($companions as $companion) {
                $row = array(
                    (string)$department,
                    'Nein', // not registered, companion
                    $companion->getName() ,
                    '' ,
                    '' ,
                    '' ,
                    '' ,
                    '' ,
                    '' ,
                    $companion->getEmail() ,
                    $companion->getPhone() ,
                    $companion->getIsRegular()==1?"Ja":"Nein" ,
                    '' ,
                    $commitment->getRemark() ,
                );
                array_push($rows, $row);
            }
        }
        // Part 3: sort
        uasort($rows, array($this, 'compareDownloadRows'));

        // Part 4: add header
        $questions = $this->questionService->getQuestionsSorted($event);
        $head = array(
            'Ressort',
            'Registrierter Helfer',
            'Nachname',
            'Vorname',
            'Geschlecht',
            'Geb.Datum',
            'Strasse',
            'PLZ',
            'Ort',
            'Email',
            'Telefon',
            'Beruf',
            'Stammhölfer',
        );
        foreach ($questions as $questionViewModel) {
            array_push($head, $questionViewModel->getText());
        }
        array_unshift($rows, $head);

        return $rows;
    }

    private function shouldSkipDepartment($department, $filterId, $doFilter)
    {
        if(!$doFilter){
            return false;
        }
        if($department == null){
            return false;
        }
        if($department->getId() != $filterId){
            return true;
        }
        return false;
    }

    /**
     * returns the comparison for the sorting of the download rows
     * @param  array $row      the one row
     * @param  array $otherRow the other row
     * @return integer comparison (-1,0,1)
     */
    private function compareDownloadRows($row,$otherRow)
    {
        $departmentCompared = strcasecmp($row[0], $otherRow[0]);
        $typeCompared = strcasecmp($row[1], $otherRow[1]);
        $nameCompared = strcasecmp($row[2], $otherRow[2]);

        if($departmentCompared != 0) {
            return $departmentCompared;
        }

        if($typeCompared != 0){
            return $typeCompared;
        }

        return $nameCompared;
    }
}

?>
