<?php

namespace App\Controller;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\ParamConverter;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;
use Swift_Mailer;
use App\Entity\Commitment;
use App\Entity\Department;
use App\Entity\Event;
use App\ViewModel\Commitment\CommitmentViewModel;
use App\Form\Commitment\CommitmentType;
use App\Service\IAuthorizationService;
use App\Service\ICommitmentService;
use App\Service\IEventService;
use App\Service\IMailBuilderService;

/**
 * Commitment controller.
 *
 * @Route("/commitment")
 */
class CommitmentController extends Controller
{
    /**
     * Displays a form to edit an existing Commitment entity.
     *
     * @Route("/{id}/edit", name="commitment_edit")
     * @Method({"GET", "POST"})
     * @Security("has_role('ROLE_USER')")
     */
    public function editAction(
        Request $request,
        Commitment $commitment,
        IEventService $eventService,
        IAuthorizationService $auth,
        IMailBuilderService $mailBuilder,
        ICommitmentService $commitmentService,
        Swift_Mailer $mailer
    )
    {
        $department = $commitment->getDepartment(); // may be null!
        $event = $commitment->getEvent();
        if (!$auth->mayEditOrDeleteCommitment($commitment))
        {
            //TODO: Localization
            $this->addFlash('warning', "Eintrag kann nicht geändert werden.");
            //TODO: Solve this with referers. see https://github.com/chriglburri/clanx/issues/118
            if ($department) {
                return $this->redirectToRoute('department_show', array(
                    'id' => $department->getId(),
                ));
            } else {
                return $this->redirectToRoute('event_edit', array(
                    'id' => $event->getId(),
                ));
            }
        }

        $deleteForm = $this->createDeleteForm($commitment); // for delete button!

        $formVM = $eventService->getCommitmentFormViewModelForEdit($commitment); //CommitmentViewModel

        $editForm = $this->getEnrollForm($formVM);

        $editForm->handleRequest($request);

        if ($editForm->isSubmitted() && $editForm->isValid()) {
            $noMessage = $editForm->get('noMessage')->getData();
            $mailFlashMsg = $commitment->getUser()." wurde NICHT benachrichtigt.";
            if(!$noMessage)
            {
                $text = $editForm->get('message')->getData();
                $operator = $this->getUser();
                $message = $mailBuilder->buildCommitmentVolunteerNotification($text,$commitment,$operator);
                $mailer->send($message);

                $mailFlashMsg = $commitment->getUser()." wurde benachrichtigt.";
            }

            $success = $commitmentService->updateCommitment($formVM, $commitment);

            //TODO: Localization
            if ($success) {
                $this->addFlash('success', "Änderung gespeichert, ".$mailFlashMsg);
            } else {
                $this->addFlash('danger', 'Änderungen konnten NICHT gespeichert werden!');
            }
            //TODO: Solve this with referers. see https://github.com/chriglburri/clanx/issues/118
            if ($department) {
                return $this->redirectToRoute('department_show', array(
                    'id' => $department->getId(),
                ));
            } else {
                return $this->redirectToRoute('event_edit', array(
                    'id' => $event->getId(),
                ));
            }
        }

        return $this->render('commitment/edit.html.twig', array(
            'commitment' => $commitment,
            'department' => $commitment->getDepartment(),
            'volunteer' => $commitment->getUser(),
            'event' => $event,
            'edit_form' => $editForm->createView(),
            'delete_form' => $deleteForm->createView(),
        ));
    }

    /**
     * Deletes a Commitment entity.
     *
     * @Route("/{id}", name="commitment_delete")
     * @Method("DELETE")
     * @Security("has_role('ROLE_USER')")
     */
    public function deleteAction(
        Request $request,
        Commitment $commitment,
        IAuthorizationService $auth,
        IMailBuilderService $mailBuilder,
        ICommitmentService $commitmentService,
        Swift_Mailer $mailer
    )
    {
        $department = $commitment->getDepartment();
        $event = $commitment->getEvent();
        if (!$auth->mayEditOrDeleteCommitment($commitment))
        {
            // TODO: Localization
            $this->addFlash('warning', "Eintrag kann nicht gelöscht werden.");
            //TODO: Solve this with referers. see https://github.com/chriglburri/clanx/issues/118
            if ($department) {
                return $this->redirectToRoute('department_show', array(
                    'id' => $department->getId(),
                ));
            } else {
                return $this->redirectToRoute('event_edit', array(
                    'id' => $event->getId(),
                ));
            }

        }

        $form = $this->createDeleteForm($commitment);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // here, noMessage is a hidden field. have to cheat it into bool.
            $noMessage = $form->get('noMessage')->getData()=='1';
            $mailFlashMsg = $commitment->getUser()." wurde NICHT benachrichtigt.";
            if(!$noMessage)
            {
                $text = $form->get('message')->getData();
                $operator = $this->getUser();

                $message = $mailBuilder->buildCommitmentVolunteerNotification($text,$commitment,$operator);
                $mailer->send($message);

                $mailFlashMsg = $commitment->getUser()." wurde benachrichtigt.";
            }

            $success = $commitmentService->deleteCommitment($commitment);

            //TODO: Localization
            if ($success) {
                $this->addFlash('success', "Daten wurden gelöscht."." ".$mailFlashMsg);
            } else {
                $this->addFlash('danger', 'Änderungen konnten NICHT gelöscht werden!');
            }
        }

        //TODO: Solve this with referers. see https://github.com/chriglburri/clanx/issues/118
        if ($department) {
            return $this->redirectToRoute('department_show', array(
                'id' => $department->getId(),
            ));
        } else {
            return $this->redirectToRoute('event_edit', array(
                'id' => $event->getId(),
            ));
        }

    }

    /**
     * Creates a form to delete a commitment entity.
     *
     * @param Commitment $commitment The Commitment entity
     *
     * @return \Symfony\Component\Form\Form The form
     */
    private function createDeleteForm(Commitment $commitment)
    {
        return $this->createFormBuilder()
            ->add('message', HiddenType::class, array(
                // on btn click, data will be copied from the commitment form
                'attr' => array('class'=>'clx-commitment-delete-message'), ))
            ->add('noMessage', HiddenType::class, array(
                // on btn click, data will be copied from the commitment form
                'attr' => array('class'=>'clx-commitment-delete-noMessage'), ))
            ->setAction($this->generateUrl('commitment_delete', array(
                'id' => $commitment->getId())
            ))
            ->setMethod('DELETE')
            ->getForm()
        ;
    }

    /**
     * @param  CommitmentViewModel $vm
     * @return FormType
     */
    private function getEnrollForm(CommitmentViewModel $vm)
    {
        $options = array(
            CommitmentType::DEPARTMENT_CHOICES_KEY => $vm->getDepartments(),
            CommitmentType::USE_DEPARTMENTS_KEY => $vm->hasDepartments(),
            CommitmentType::USE_VOLUNTEER_NOTIFICATION_KEY => true,
        );

        $form = $this->createForm('App\Form\Commitment\CommitmentType', $vm, $options);

        foreach ($vm->getQuestions() as $q) {
            $attributes = array();
            $attributes = $q->fillAttributes($attributes);
            $form->add($q->getFormFieldName(), $q->getFormType(), $attributes);
        }
        return $form;
    }
}
