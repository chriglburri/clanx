<?php
namespace App\Service;

use Doctrine\ORM\EntityManager;
use App\Service\IAuthorizationService;
use App\Entity\Event;

class MenuService implements IMenuService
{
    /**
     * @var App\Service\IAuthorizationService
     */
    protected $authorization;

    public function __construct(
        IAuthorizationService $auth
    )
    {
        $this->authorization = $auth;
    }

    /**
     * Gets the menu item to get to the home page.
     * @return array Returns an array of values which are used in the view model.
     */
    public function getHomeMenu()
    {
        return $this->createItem(
            'Home',
            'fa fa-home',
            'dashboard_index');
    }

    /**
     * Gets the menu item to get to an event detail page.
     * @return array Returns an array of values which are used in the view model.
     */
    public function getEventMenu(Event $event)
    {
        $icon = null;
        if(!$event->getIsVisible()){
            $icon = 'fa fa-eye-slash';
        }
        return $this->createItem(
            $event->getName(),
            $icon,
            'event_show',
            'id',
            $event->getId(),
            true); // "hot" items are visually emphasized
    }

    /**
     * Gets the menu item to get to the event index page.
     * @return array Returns an array of values which are used in the view model.
     */
    public function getInfoMenu()
    {
        return $this->createItem(
            'Info',
            'fa fa-info',
            'info_index');
    }

    /**
     * Gets the menu item to get to the event index page.
     * @return array Returns an array of values which are used in the view model.
     */
    public function getEventIndexMenu()
    {
        return $this->createItem(
            'Events',
            'fa fa-calendar',
            'event_index');
    }

    /**
     * Gets the menu item to get to the user index page.
     * @return array Returns an array of values which are used in the view model.
     * Returns null, if the logged in user may not see the users page.
     */
    public function getUserIndexMenu()
    {
        if(!$this->authorization->maySeeUserPage()){
            return null;
        }
        return $this->createItem(
            'Users',
            'fa fa-users',
            'user_index');
    }

    /**
     * Gets a new menu item.
     * @param  string  $name          The name.
     * @param  string  $icon          The icon.
     * @param  string  $routename     The name of the target route.
     * @param  string  $argumentName  The name of the aditional argument of the route.
     * @param  string  $argumentValue The value of the aditional argument.
     * @param  boolean $hot           Indicator if it is a 'hot' menu.
     * @return array Returns an array of values which are used in the view model.
     */
    private function createItem(
        $name,
        $icon,
        $routename,
        $argumentName=null,
        $argumentValue=null,
        $hot=false)
    {
        return array(
            'text' => $name,
            'icon' => $icon,
            'routename' => $routename,
            'hasArguments' => $argumentName!=null,
            // later this may be a collection!
            'arguments' => array($argumentName => $argumentValue ),
            'hot' => $hot,
        );
    }
}

?>
