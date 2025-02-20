<?php

namespace Laminas\Mvc\Controller\Plugin;

use Traversable;
use Laminas\Mvc\Exception\DomainException;
use Laminas\EventManager\SharedEventManagerInterface as SharedEvents;
use Laminas\Mvc\Controller\ControllerManager;
use Laminas\Mvc\Exception;
use Laminas\Mvc\InjectApplicationEventInterface;
use Laminas\Mvc\MvcEvent;
use Laminas\Router\RouteMatch;
use Laminas\Stdlib\CallbackHandler;

class Forward extends AbstractPlugin
{
    /**
     * @var MvcEvent
     */
    protected $event;

    /**
     * @var int
     */
    protected $maxNestedForwards = 10;

    /**
     * @var int
     */
    protected $numNestedForwards = 0;

    /**
     * @var array[]|null
     */
    protected $listenersToDetach = null;

    public function __construct(protected ControllerManager $controllers)
    {
    }

    /**
     * Set maximum number of nested forwards allowed
     *
     * @param  int $maxNestedForwards
     * @return self
     */
    public function setMaxNestedForwards($maxNestedForwards)
    {
        $this->maxNestedForwards = (int) $maxNestedForwards;

        return $this;
    }

    /**
     * Get information on listeners that need to be detached before dispatching.
     *
     * Each entry in the array contains three keys:
     *
     * id (identifier for event-emitting component),
     * event (the hooked event)
     * and class (the class of listener that should be detached).
     *
     * @return array
     */
    public function getListenersToDetach()
    {
        // If a blacklist has not been explicitly set, return the default:
        if (null === $this->listenersToDetach) {
            // We need to detach the InjectViewModelListener to prevent templates
            // from getting attached to the ViewModel twice when a calling action
            // returns the output generated by a forwarded action.
            $this->listenersToDetach = [[
                'id'    => \Laminas\Stdlib\DispatchableInterface::class,
                'event' => MvcEvent::EVENT_DISPATCH,
                'class' => \Laminas\Mvc\View\Http\InjectViewModelListener::class,
            ]];
        }
        return $this->listenersToDetach;
    }

    /**
     * Set information on listeners that need to be detached before dispatching.
     *
     * @param  array $listeners Listener information; see getListenersToDetach() for details on format.
     *
     * @return self
     */
    public function setListenersToDetach($listeners)
    {
        $this->listenersToDetach = $listeners;

        return $this;
    }

    /**
     * Dispatch another controller
     *
     * @param  string $name Controller name; either a class name or an alias used in the controller manager
     * @param  null|array $params Parameters with which to seed a custom RouteMatch object for the new controller
     * @return mixed
     * @throws Exception\DomainException if composed controller does not define InjectApplicationEventInterface
     *         or Locator aware; or if the discovered controller is not dispatchable
     */
    public function dispatch($name, ?array $params = null)
    {
        $event   = clone($this->getEvent());

        $controller = $this->controllers->get($name);
        if ($controller instanceof InjectApplicationEventInterface) {
            $controller->setEvent($event);
        }

        // Allow passing parameters to seed the RouteMatch with & copy matched route name
        if ($params !== null) {
            $routeMatch = new RouteMatch($params);
            $routeMatch->setMatchedRouteName($event->getRouteMatch()->getMatchedRouteName());
            $event->setRouteMatch($routeMatch);
        }

        if ($this->numNestedForwards > $this->maxNestedForwards) {
            throw new DomainException(
                "Circular forwarding detected: greater than $this->maxNestedForwards nested forwards"
            );
        }
        $this->numNestedForwards++;

        // Detach listeners that may cause problems during dispatch:
        $sharedEvents = $event->getApplication()->getEventManager()->getSharedManager();
        $listeners = $this->detachProblemListeners($sharedEvents);

        $return = $controller->dispatch($event->getRequest(), $event->getResponse());

        // If we detached any listeners, reattach them now:
        $this->reattachProblemListeners($sharedEvents, $listeners);

        $this->numNestedForwards--;

        return $return;
    }

    /**
     * Detach problem listeners specified by getListenersToDetach() and return an array of information that will
     * allow them to be reattached.
     *
     * @param  SharedEvents $sharedEvents Shared event manager
     * @return array
     */
    protected function detachProblemListeners(SharedEvents $sharedEvents)
    {
        // Convert the problem list from two-dimensional array to more convenient id => event => class format:
        $formattedProblems = [];
        foreach ($this->getListenersToDetach() as $current) {
            if (! isset($formattedProblems[$current['id']])) {
                $formattedProblems[$current['id']] = [];
            }
            if (! isset($formattedProblems[$current['id']][$current['event']])) {
                $formattedProblems[$current['id']][$current['event']] = [];
            }
            $formattedProblems[$current['id']][$current['event']][] = $current['class'];
        }

        // Loop through the class blacklist, detaching problem events and remembering their CallbackHandlers
        // for future reference:
        $results = [];
        foreach ($formattedProblems as $id => $eventArray) {
            $results[$id] = [];
            foreach ($eventArray as $eventName => $classArray) {
                $results[$id][$eventName] = [];
                $events = $this->getSharedListenersById($id, $eventName, $sharedEvents);
                foreach ($events as $priority => $currentPriorityEvents) {
                    foreach ($currentPriorityEvents as $currentEvent) {
                        $currentCallback = $currentEvent;

                        // If we have an array, grab the object
                        if (is_array($currentCallback)) {
                            $currentCallback = array_shift($currentCallback);
                        }

                        // This routine is only valid for object callbacks
                        if (! is_object($currentCallback)) {
                            continue;
                        }

                        foreach ($classArray as $class) {
                            if ($currentCallback instanceof $class) {
                                $this->detachSharedListener($id, $currentEvent, $sharedEvents);
                                $results[$id][$eventName][$priority] = $currentEvent;
                            }
                        }
                    }
                }
            }
        }

        return $results;
    }

    /**
     * Reattach all problem listeners detached by detachProblemListeners(), if any.
     *
     * @param  SharedEvents $sharedEvents Shared event manager
     * @param  array        $listeners    Output of detachProblemListeners()
     * @return void
     */
    protected function reattachProblemListeners(SharedEvents $sharedEvents, array $listeners)
    {
        foreach ($listeners as $id => $eventArray) {
            foreach ($eventArray as $eventName => $callbacks) {
                foreach ($callbacks as $priority => $current) {
                    $callback = $current;

                    $sharedEvents->attach($id, $eventName, $callback, $priority);
                }
            }
        }
    }

    /**
     * Get the event
     *
     * @return MvcEvent
     * @throws Exception\DomainException if unable to find event
     */
    protected function getEvent()
    {
        if ($this->event) {
            return $this->event;
        }

        $controller = $this->getController();
        if (! $controller instanceof InjectApplicationEventInterface) {
            throw new DomainException(sprintf(
                'Forward plugin requires a controller that implements InjectApplicationEventInterface; received %s',
                (get_debug_type($controller))
            ));
        }

        $event = $controller->getEvent();
        if (! $event instanceof MvcEvent) {
            $params = [];
            if ($event) {
                $params = $event->getParams();
            }
            $event  = new MvcEvent();
            $event->setParams($params);
        }
        $this->event = $event;

        return $this->event;
    }

    /**
     * Retrieve shared listeners for an event by identifier.
     *
     * Varies retrieval based on laminas-eventmanager version.
     *
     * @param string|int $id
     * @param string $event
     * @param SharedEvents $sharedEvents
     * @return array|Traversable
     */
    private function getSharedListenersById($id, $event, SharedEvents $sharedEvents)
    {
        return $sharedEvents->getListeners([$id], $event);
    }

    /**
     * Detach a shared listener by identifier.
     *
     * Varies detachment based on laminas-eventmanager version.
     *
     * @param string|int $id
     * @param callable|CallbackHandler $listener
     * @param SharedEvents $sharedEvents
     * @return void
     */
    private function detachSharedListener($id, $listener, SharedEvents $sharedEvents)
    {
        $sharedEvents->detach($listener, $id);
    }
}
