<?php namespace Ignited\LaravelOmnipay;

use Closure;
use Omnipay\Common\GatewayFactory;
use Omnipay\Common\Helper;
use Omnipay\Common\CreditCard;

class LaravelOmnipayManager {
    /**
     * The application instance.
     *
     * @var \Illuminate\Foundation\Application | \Laravel\Lumen\Application
     */
    protected $app;

    /**
     * Omnipay Factory Instance
     * @var \Omnipay\Common\GatewayFactory
     */
    protected $factory;

    /**
     * The current gateway to use
     * @var string
     */
    protected $gateway;

    /**
     * The Guzzle client to use (null means use default)
     * @var \GuzzleHttp\Client|null
     */
    protected $httpClient;

    /**
     * The array of resolved queue connections.
     *
     * @var array
     */
    protected $gateways = [];

    /**
     * Create a new omnipay manager instance.
     *
     * @param \Illuminate\Foundation\Application | \Laravel\Lumen\Application $app
     * @param \Omnipay\Common\GatewayFactory $factory
     */
    public function __construct($app, GatewayFactory $factory)
    {
        $this->app = $app;
        $this->factory = $factory;
    }

    /** 
     * Get an instance of the specified gateway
     * @param string index of config array to use
     * @return \Omnipay\Common\AbstractGateway
     */
    public function gateway($name = null)
    {
        $name = $name ?: $this->getGateway();

        if (!isset($this->gateways[$name]))
        {
            $this->gateways[$name] = $this->resolve($name);
        }

        return $this->gateways[$name];
    }

    protected function resolve($name)
    {
        $config = $this->getConfig($name);

        if (is_null($config))
        {
            throw new \UnexpectedValueException("Gateway [$name] is not defined.");
        }

        $gateway = $this->factory->create($config['driver'], $this->getHttpClient());

        $class = trim(Helper::getGatewayClassName($config['driver']), "\\");

        $reflection = new \ReflectionClass($class);

        foreach($config['options'] as $optionName=>$value)
        {
            $method = 'set' . ucfirst($optionName);

            if ($reflection->hasMethod($method)) {
                $gateway->{$method}($value);
            }
        }

        return $gateway;
    }

    public function creditCard($cardInput)
    {
        return new CreditCard($cardInput);
    }

    protected function getDefault()
    {
        return $this->app['config']['omnipay.default'];
    }

    /**
     * Get the configuration.
     *
     * @param string $name
     * @return mixed
     */
    protected function getConfig($name)
    {
        return $this->app['config']['omnipay.gateways.{$name}'];
    }

    /**
     * Get the gateway name.
     *
     * @return string
     */
    public function getGateway()
    {
        if(!isset($this->gateway))
        {
            $this->gateway = $this->getDefault();
        }
        return $this->gateway;
    }

    /**
     * Set the gateway name.
     *
     * @param string $name
     * @return void
     */
    public function setGateway($name)
    {
        $this->gateway = $name;
    }

    /**
     * Set a Guzzle client instance.
     *
     * @param \GuzzleHttp\Client $httpClient
     */
    public function setHttpClient($httpClient)
    {
        $this->httpClient = $httpClient;
    }

    /**
     * Get the Guzzle client instance.
     *
     * @return \GuzzleHttp\Client
     */
    public function getHttpClient()
    {
        return $this->httpClient;
    }

    public function __call($method, $parameters)
    {
        if(method_exists($this->gateway(), $method))
        {
            return call_user_func_array([$this->gateway(), $method], $parameters);
        }

        throw new \BadMethodCallException("Method [$method] is not supported by the gateway [$this->gateway].");
    }
}
