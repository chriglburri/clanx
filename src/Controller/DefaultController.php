<?php

namespace App\Controller;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;

class DefaultController extends Controller
{
    /**
     * @Route("/", name="homepage")
     */
    public function indexAction(Request $request)
    {
        // replace this example code with whatever you need
        if($this->isGranted('ROLE_USER')){
            return $this->redirectToRoute('dashboard_index');
        }

        return $this->render('default/index.html.twig', [
            'base_dir' => realpath($this->getParameter('kernel.root_dir').'/..'),
        ]);
    }


    /**
     * Shows help to the registration process.
     *
     * @Route("/help/registration", name="default_help_registration")
     * @Method("GET")
     */
    public function helpRegistrationAction()
    {
        return $this->render('default/help_registration.html.twig');
    }

    /**
     * Shows a message, that registration is not possible at the moment.
     *
     * @Route("/deny/registration", name="registration_denied")
     * @Method("GET")
     */
    public function registrationDeniedAction()
    {
        return $this->render('default/registration_denied.html.twig');
    }

}
