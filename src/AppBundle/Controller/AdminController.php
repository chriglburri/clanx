<?php
namespace AppBundle\Controller;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Doctrine\Common\Collections\Criteria;
use AppBundle\Entity\User;
use AppBundle\Entity\Event;
use AppBundle\Entity\Department;
use AppBundle\Entity\Commitment;
use AppBundle\Entity\LegacyUser;

/**
 * Dashboard controller.
 * @Route("/admin")
 */
class AdminController extends Controller
{
    /**
    * @Route("/toggle/deny/registration", name="toggle_registration_lock")
    * @Method({"GET"})
    * @Security("has_role('ROLE_ADMIN')")
    */
    public function toggleDenyRegistrationAction(Request $request)
    {
        $trans = $this->get('translator');
        $trans->setLocale('de'); // TODO: use real localization here.

        $translated = $trans->trans('flash.successfully_saved',array(),'flash');
        $type = 'success';
        try {
            $settings = $this->get('app.Settings');
            $settings->toggleCanRegister();
         } catch (Exception $e) {
            $type = 'danger';
            $translated = $trans->trans('flash.flash.save_failed_error',
                array('%error%' => $e->getMessage),'flash'
            );
        }
        $this->addFlash($type, $translated);
        return $this->redirectToRoute('dashboard_index');
    }
}
