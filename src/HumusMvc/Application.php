<?php

namespace HumusMvc;

use Zend\EventManager\EventManagerAwareInterface;
use Zend\EventManager\EventManagerInterface;
use HumusMvc\Service\ServiceManagerConfig;
use Zend\ServiceManager\ServiceManager;
use Zend_Controller_Request_Abstract as Request;
use Zend_Controller_Response_Abstract as Response;

/**
 * Main application class for invoking applications
 *
 * Expects the user will provide a configured ServiceManager, configured with
 * the following services:
 *
 * - EventManager
 * - ModuleManager
 * - Request
 * - Response
 * - Router
 *
 * The most common workflow is:
 * <code>
 * $services = new Zend\ServiceManager\ServiceManager($servicesConfig);
 * $app      = new Application($appConfig, $services);
 * $app->bootstrap();
 * $app->run();
 * </code>
 *
 * bootstrap() opts in to the default route, dispatch, and view listeners,
 * sets up the MvcEvent, and triggers the bootstrap event. This can be omitted
 * if you wish to setup your own listeners and/or workflow; alternately, you
 * can simply extend the class to override such behavior.
 *
 * @category   Humus
 * @package    HumusMvc
 */
class Application implements
    ApplicationInterface,
    EventManagerAwareInterface
{

    /**
     * @var array
     */
    protected $config = null;

    /**
     * MVC event token
     * @var MvcEvent
     */
    protected $event;

    /**
     * @var EventManagerInterface
     */
    protected $events;

    /**
     * @var Request
     */
    protected $request;

    /**
     * @var Response
     */
    protected $response;

    /**
     * @var ServiceManager
     */
    protected $serviceManager = null;

    /**
     * Constructor
     *
     * @param mixed $config
     * @param ServiceManager $serviceManager
     */
    public function __construct($config, ServiceManager $serviceManager)
    {
        $this->config  = $config;
        $this->serviceManager = $serviceManager;
        $this->setEventManager($serviceManager->get('EventManager'));
        $this->request        = $serviceManager->get('Request');
        $this->response       = $serviceManager->get('Response');
    }

    /**
     * Retrieve the application configuration
     *
     * @return array|object
     */
    public function getConfig()
    {
        return $this->serviceManager->get('Config');
    }

    /**
     * Bootstrap the application
     *
     * Defines and binds the MvcEvent, and passes it the request, response, and
     * router. Triggers the bootstrap event.
     *
     * @return Application
     */
    public function bootstrap()
    {
        $serviceManager = $this->serviceManager;
        $events         = $this->getEventManager();

        // Setup MVC Event
        $this->event = $event  = new MvcEvent();
        $event->setTarget($this);
        $event->setApplication($this)
              ->setRequest($this->getRequest())
              ->setResponse($this->getResponse())
              ->setRouter($serviceManager->get('Router'));

        // Trigger bootstrap events
        $events->trigger(MvcEvent::EVENT_BOOTSTRAP, $event);
        return $this;
    }

    /**
     * Retrieve the service manager
     *
     * @return ServiceManager
     */
    public function getServiceManager()
    {
        return $this->serviceManager;
    }

    /**
     * Get the request object
     *
     * @return Request
     */
    public function getRequest()
    {
        return $this->request;
    }

    /**
     * Get the response object
     *
     * @return Response
     */
    public function getResponse()
    {
        return $this->response;
    }

    /**
     * Get the MVC event instance
     *
     * @return MvcEvent
     */
    public function getMvcEvent()
    {
        return $this->event;
    }

    /**
     * Set the event manager instance
     *
     * @param  EventManagerInterface $eventManager
     * @return Application
     */
    public function setEventManager(EventManagerInterface $eventManager)
    {
        $eventManager->setIdentifiers(array(
            'Zend\Mvc\Application', // for compatibility with zf2 module manager listeners,
                                    // so we don't need to subclass all listeners.
            __CLASS__,
            get_called_class(),
        ));
        $this->events = $eventManager;
        return $this;
    }

    /**
     * Retrieve the event manager
     *
     * Lazy-loads an EventManager instance if none registered.
     *
     * @return EventManagerInterface
     */
    public function getEventManager()
    {
        return $this->events;
    }

    /**
     * Static method for quick and easy initialization of the Application.
     *
     * If you use this init() method, you cannot specify a service with the
     * name of 'ApplicationConfig' in your service manager config. This name is
     * reserved to hold the array from application.config.php.
     *
     * The following services can only be overridden from application.config.php:
     *
     * - ModuleManager
     * - SharedEventManager
     * - EventManager & Zend\EventManager\EventManagerInterface
     *
     * All other services are configured after module loading, thus can be
     * overridden by modules.
     *
     * @param array $configuration
     * @return Application
     */
    public static function init($configuration = array())
    {
        $smConfig = isset($configuration['service_manager']) ? $configuration['service_manager'] : array();
        $serviceManager = new ServiceManager(new ServiceManagerConfig($smConfig));
        $serviceManager->setService('ApplicationConfig', $configuration);
        $serviceManager->get('ModuleManager')->loadModules();
        return $serviceManager->get('Application')->bootstrap();
    }

    /**
     * Run the application
     *
     * @return mixed
     * @throws Exception\RuntimeException if no default controller is registered with front controller
     */
    public function run()
    {

        /** @var $front \Zend_Controller_Front */
        $front = $this->getServiceManager()->get('FrontController');
        $default = $front->getDefaultModule();
        if (null === $front->getControllerDirectory($default)) {
            throw new Exception\RuntimeException(
                'No default controller directory registered with front controller'
            );
        }
        $response = $front->dispatch();

        if ($front->returnResponse()) {
            return $response;
        }
    }
}
