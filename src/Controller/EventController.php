<?php

namespace App\Controller;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\Form\Form;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\ParamConverter;
use Swift_Mailer;
use App\Entity\Commitment;
use App\Entity\Department;
use App\Entity\Event;
use App\Entity\Mail;
use App\Entity\Question;
use App\Entity\RedirectInfo;
use App\Entity\User;
use App\ViewModel\Commitment\CommitmentViewModel;
use App\Form\Commitment\CommitmentType;
use App\Form\EventCreateType;
use App\Service\AuthorizationService;
use App\Service\IAuthorizationService;
use App\Service\ICommitmentService;
use App\Service\IDepartmentService;
use App\Service\IEventService;
use App\Service\IExportService;
use App\Service\IMailBuilderService;
use App\Service\IQuestionService;
use App\Service\IUserService;

/**
 * Event controller.
 *
 * @Route("/event")
 */
class EventController extends Controller
{
    /**
     * Lists all Event entities.
     *
     * @Route("/", name="event_index")
     * @Method("GET")
     * @Security("has_role('ROLE_USER')")
     */
    public function indexAction(IEventService $eventSvc)
    {
        // should be arrays of Event objects,
        // ordered by date
        $upcoming = $eventSvc->getUpcoming();
        $passed = $eventSvc->getPassed();


        return $this->render('event/index.html.twig', array(
            'upcomingEvents' => $upcoming,
            'passedEvents' => $passed,
        ));
    }

    /**
     * Creates a new Event entity.
     *
     * @Route("/new", name="event_new")
     * @Method({"GET", "POST"})
     * @Security("has_role('ROLE_ADMIN')")
     */
    public function newAction(Request $request)
    {
        $event = new Event();
        $form = $this->createForm('App\Form\EventCreateType', $event);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em = $this->getDoctrine()->getManager();
            $em->persist($event);
            $em->flush();

            $defaultDpt = new Department();
            $defaultDpt->setName("Ich helfe wo ich kann");
            $defaultDpt->setEvent($event);
            $defaultDpt->setChiefUser($this->getUser());
            $em->persist($defaultDpt);

            $dptInput = $form->get('departments')->getData();
            if ($dptInput) {
                $dptNames = explode("\n", $dptInput);

                foreach ($dptNames as $dptName) {
                    $dpt = new Department();
                    $dpt->setName(trim($dptName));
                    $dpt->setEvent($event);
                    $em->persist($dpt);
                }
            }

            $em->flush();
            $this->addFlash('success', 'flash.successfully_saved');
            return $this->redirectToRoute('event_show', array('id' => $event->getId()));
        }

        return $this->render('event/new.html.twig', array(
            'event' => $event,
            'form' => $form->createView(),
        ));
    }

    /**
     * Finds and displays a Event entity.
     *
     * @Route("/{id}", name="event_show")
     * @Method({"GET", "POST"})
     * @Security("has_role('ROLE_USER')")
     */
    // Place this method at the end. Because the route is just /event/id.
    // It must come after routes like /event/new or event/whaterever.
    // Otherwise when the client calls /event/new, symfony tries to open
    // an event with the id "new" (what a silly little framework...)
    public function showAction(
        Request $request,
        Event $event,
        IEventService $eventSvc,
        IAuthorizationService $auth,
        IMailBuilderService $mailBuilder,
        ICommitmentService $commitmentService,
        Swift_Mailer $mailer
    ) {
        $trans = $this->get('translator');
        $trans->setLocale('de'); // TODO: use real localization here.


        $authResult = $auth->mayShowEventDetail($event);
        if (!$authResult[AuthorizationService::VALUE]) {
            $this->addFlash('danger', $authResult[AuthorizationService::MESSAGE]);
            return $this->redirectToRoute('event_index');
        }

        if ($auth->mayEnroll($event)) {
            $formVM = $eventSvc->getCommitmentFormViewModel($event); //CommitmentViewModel
            $enrollForm = $this->createEnrollForm($formVM, $commitmentService); //Symfony\Component\Form\Form
            $this->handleEnrollForm($request, $enrollForm, $formVM, $event, $mailBuilder, $commitmentService, $mailer);
        }

        //EventShowViewModel
        $detailViewModel = $eventSvc->getDetailViewModel($event);
        $detailViewModel->setDeleteForm($this->createDeleteForm($event)->createView());
        if (isset($enrollForm)) {
            $detailViewModel->setEnrollForm($enrollForm->createView());
        }

        return $this->render('event/show.html.twig', array('view_model'=>$detailViewModel));
    }
    /**
     *
     * @param  CommitmentViewModel $vm
     * @return Symfony\Component\Form\Form
     */
    private function createEnrollForm(
        CommitmentViewModel $vm,
        ICommitmentService $commitmentService
    ) {
        $options = array(
            CommitmentType::DEPARTMENT_CHOICES_KEY => $vm->getDepartments(),
            CommitmentType::USE_DEPARTMENTS_KEY => $vm->hasDepartments(),
            CommitmentType::USE_VOLUNTEER_NOTIFICATION_KEY => false,
        );

        $form = $this->createForm(CommitmentType::class, $vm, $options);

        foreach ($vm->getQuestions() as $q) {
            $attributes = array();
            $attributes = $q->fillAttributes($attributes);

            $form->add($q->getFormFieldName(), $q->getFormType(), $attributes);
        }
        return $form;
    }

    private function handleEnrollForm(
        Request $request,
        Form $enrollForm,
        CommitmentViewModel $formVM,
        Event $event,
        IMailBuilderService $mailBuilder,
        ICommitmentService $commitmentService,
        Swift_Mailer $mailer
    ) {
        $enrollForm->handleRequest($request);
        if (!$enrollForm->isSubmitted()) {
            return;
        }
        if ($enrollForm->isValid()) {
            $commitment = $commitmentService->saveCommitment($event, $formVM);
            if ($commitment != null) {
                //TODO finish here
                $this->sendMail($commitment, $mailBuilder, $mailer);
                $this->addFlash('success', 'flash.enroll_succeeded');
            } else {
                $this->addFlash('warning', 'flash.enroll_failed');
            }

            return $this->redirectToRoute('event_show', array(
                'id' => $event->getId(),
            ));
        } else {
            $this->addFlash('danger', 'flash.enroll_required_data_missing');
        }
    }

    /**
     * Displays a form to edit an existing Event entity.
     *
     * @Route("/{id}/edit", name="event_edit")
     * @Method({"GET", "POST"})
     * @Security("has_role('ROLE_ADMIN')")
     */
    public function editAction(Request $request, Event $event)
    {
        $deleteForm = $this->createDeleteForm($event);
        $editForm = $this->createForm('App\Form\EventType', $event);
        $editForm->handleRequest($request);
        $em = $this->getDoctrine()->getManager();

        if ($editForm->isSubmitted() && $editForm->isValid()) {
            $em->persist($event);
            $em->flush();

            $this->addFlash('success', "Änderung gespeichert.");

            return $this->redirectToRoute('event_show', array('id' => $event->getId()));
        }

        return $this->render('event/edit.html.twig', array(
            'event' => $event,
            'edit_form' => $editForm->createView(),
            'delete_form' => $deleteForm->createView()
        ));
    }

    /**
     * Deletes a Event entity.
     *
     * @Route("/{id}", name="event_delete")
     * @Method("DELETE")
     * @Security("has_role('ROLE_ADMIN')")
     */
    public function deleteAction(Request $request, Event $event)
    {
        $form = $this->createDeleteForm($event);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em = $this->getDoctrine()->getManager();
            $departments = $em->getRepository(Department::class)->findByEvent($event);
            foreach ($departments as $department) {
                $em->remove($department);
            }
            $questions = $em->getRepository(Question::class)->findByEvent($event);
            foreach ($questions as $question) {
                $em->remove($question);
            }
            $em->remove($event);
            $em->flush();
        }

        return $this->redirectToRoute('event_index');
    }

    /**
     * Creates a form to delete a Event entity.
     *
     * @param Event $event The Event entity
     *
     * @return \Symfony\Component\Form\Form The form
     */
    private function createDeleteForm(Event $event)
    {
        return $this->createFormBuilder()
            ->setAction($this->generateUrl('event_delete', array('id' => $event->getId())))
            ->setMethod('DELETE')
            ->getForm()
        ;
    }

    /**
     * Send the commitment comfirmation mayMail
     * @param  Commitment $commitment
     */
    private function sendMail($commitment, IMailBuilderService $mailBuilder, Swift_Mailer $mailer)
    {
        if (!$commitment) {
            return;
        }

        $message = $mailBuilder->buildCommitmentConfirmation($commitment);
        $mailer->send($message);

        if ($commitment->getDepartment() && $commitment->getDepartment()->getChiefUser()) {
            $messageToChief = $mailBuilder->buildNotificationToChief($commitment);
            $mailer->send($messageToChief);
        }
    }

    /**
     * send an invitation mail to all users who may enroll on the given event
     * @param  Request $request The request.
     * @param  Event   $event   The event.
     * @return view           The view.
     *
     * @Route("/{id}/invite", name="event_invite")
     * @Method({"GET","POST"})
     */
    public function invite(
        Request $request,
        Event $event,
        IAuthorizationService $auth,
        IUserService $userService
    ) {
        $session = $request->getSession();
        $mayInvite = $auth->maySendInvitation($event);

        if (!$mayInvite) {
            $this->addFlash('warning', 'Du darfst keine Einladungen versenden.');
            return $this->redirectToRoute('event_show', array('id'=>$event->getId(),));
        }

        $mailData = new Mail();
        $eventUrl = $this->generateUrl(
            'event_show',
            array('id' => $event->getId()),
            UrlGeneratorInterface::ABSOLUTE_URL
        );

        $mailData->setSubject($event->getName() . " - Einladung zum mitmachen!")
            ->setSender($this->getUser()->getEmail())
            ->setText('Link: '.$eventUrl);

        if ($event->getIsForAssociationMembers()) {
            $users = $userService->getAllAssociationMembers();
        } else {
            $users = $userService->getAllUsers();
        }

        foreach ($users as $usr) {
            $mail = $usr->getEmail();
            $name = $usr->getForename().' '.$usr->getSurname();
            $mailData->addBcc($mail, $name);
        }

        $session->set(Mail::SESSION_KEY, $mailData);

        $backLink = new RedirectInfo();
        $backLink->setRouteName('event_show')
            ->setArguments(array('id'=>$event->getId()))
            ;

        $session->set(RedirectInfo::SESSION_KEY, $backLink);

        return $this->redirectToRoute('mail_edit');
    }

    /**
     * Finds and displays a Event entity.
     *
     * @Route("/{id}/mail/enrolled", name="event_mail_enrolled")
     * @Method({"GET","POST"})
     * @Security("has_role('ROLE_ADMIN')")
     */
    public function mailEnrolledAction(Request $request, Event $event)
    {
        $session = $request->getSession();

        $mailData = new Mail();
        $eventUrl = $this->generateUrl(
            'event_show',
            array('id' => $event->getId()),
            UrlGeneratorInterface::ABSOLUTE_URL
        );

        $mailData->setSubject($event->getName() . " Hölferinfo")
             ->setSender($this->getUser()->getEmail())
             ->setText('Link: '.$eventUrl);

        foreach ($event->getCommitments() as $cmnt) {
            $usr = $cmnt->getUser();
            $mail = $usr->getEmail();
            $name = $usr->getForename().' '.$usr->getSurname();
            $mailData->addBcc($mail, $name);
        }
        foreach ($event->getCompanions() as $companion) {
            $mail = $companion->getEmail();
            if ($mail) {
                $mailData->addBcc($mail);
            }
        }

        $session->set(Mail::SESSION_KEY, $mailData);

        $backLink = new RedirectInfo();
        $backLink->setRouteName('event_show')
                 ->setArguments(array('id'=>$event->getId()))
                 ;

        $session->set(RedirectInfo::SESSION_KEY, $backLink);

        return $this->redirectToRoute('mail_edit');
    }

    /**
     * Prepares session variables and redirects to the MailController
     *
     * @Route("/{id}/redirect/mail/to/{user_id}", name="event_redirect_mail_to")
     * @Method("GET")
     * @ParamConverter("recipient", class=User::class, options={"id" = "user_id"})
     * @Security("has_role('ROLE_USER')")
     */
    public function redirectMailToAction(Request $request, Event $event, User $recipient)
    {
        // just in case somone clicks on the "mailTo" button of the
        // chief-of-department or deputy-of-department:
        $session = $request->getSession();

        $backLink = new RedirectInfo();
        $backLink->setRouteName('event_show')
                 ->setArguments(array('id'=>$event->getId()));
        $session->set(RedirectInfo::SESSION_KEY, $backLink);

        $mailData = new Mail();
        $mailData->setSubject('Frage betreffend '.$event->getName())
             ->setRecipient($recipient->getEmail())
             ->setSender($this->getUser()->getEmail());

        $session->set(Mail::SESSION_KEY, $mailData);

        return $this->redirectToRoute('mail_edit');
    }

    /**
     * Download all volunteers and companions as csv.
     *
     * @Route("/{id}/download", name="event_download")
     * @Method({"GET","POST"})
     * @Security("has_role('ROLE_USER')")
     */
    public function downloadAction(
        Request $request,
        Event $event,
        IAuthorizationService $auth,
        IExportService $exportService,
        IEventService $eventService
    ) {
        $trans = $this->get('translator');
        $trans->setLocale('de'); // TODO: use real localization here.
        if (!$auth->mayDownloadFromEvent($event)) {
            $this->addFlash('warning', 'flash.authorization_denied');
            return $this->redirectToRoute('event_show', array(
                'id'=>$event->getId(),
            ));
        }

        // todo: make common function for event download and department download
        $rows = $eventService->getRowsForDownload($event);

        $response = $this->render('export_raw.twig', array(
            'content' => $exportService->getCsvText($rows)
        ));
        $response->headers->set('Content-Type', 'text/csv');
        $fileName = 'Helfer_'.$event->getName().'.csv';
        $response->headers->set('Content-Disposition', 'attachment; filename="'.$fileName.'"');
        return $response;
    }


    /**
     * Copy the given event with all departments and questions, but without commitments.
     *
     * @Route("/{id}/copy", name="event_copy")
     * @Method({"GET","POST"})
     * @Security("has_role('ROLE_ADMIN')")
     */
    public function copyAction(
        Request $request,
        Event $event,
        IEventService $eventSvc,
        IDepartmentService $depSvc,
        IQuestionService $questionSvc
    ) {
        $newEvent = $eventSvc->getCopy($event);
        $newDepartments = $depSvc->getCopyOfEvent($event);
        $newQuestions = $questionSvc->getCopyOfEvent($event);
        $eventSvc->setRelations($newEvent, $newDepartments, $newQuestions);

        try {
            $em = $this->getDoctrine()->getManager();
            $em->persist($newEvent);
            foreach ($newDepartments as $department) {
                $em->persist($department);
            }
            foreach ($newQuestions as $question) {
                $em->persist($question);
            }
            $em->flush();

            $this->addFlash('success', 'flash.successfully_saved');
        } catch (Exception $e) {
            $this->addFlash('danger', 'flash.copy_failed_error');
            $this->addFlash('danger', $e->getMessage());
        }

        return $this->redirectToRoute('event_edit', array('id' => $newEvent->getId()));
    }


    private function array2csv($arrayRow)
    {
        trigger_error("Deprecated function called.", E_USER_NOTICE);
        // https://stackoverflow.com/questions/4249432/export-to-csv-via-php
        //$delimiter = chr(9); // tab (for excel)
        $delimiter = ';';
        $enclosure = '"';
        $escape = '\\';

        ob_start();
        $df = fopen("php://output", 'w');
        //add BOM to fix UTF-8 in Excel
        fputs($df, $bom =(chr(0xEF) . chr(0xBB) . chr(0xBF)));
        fputcsv($df, $arrayRow, $delimiter, $enclosure);
        fclose($df);
        return ob_get_clean();
    }
}
