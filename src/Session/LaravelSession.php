<?php namespace Millar\AB\Session;

use Illuminate\Support\Facades\Session;

class LaravelSession implements SessionInterface {

    /**
     * The session key.
     *
     * @var string
     */
    protected $sessionName = 'ab';

    /**
     * A copy of the session data.
     *
     * @var array
     */
    protected $data = null;

    /**
     * Constructor.
     */
    public function __construct()
    {
        $this->data = Session::get($this->sessionName, []);
    }

    /**
     * {@inheritdoc}
     */
    public function get($name, $default = null)
    {
        return array_get($this->data, $name, $default);
    }

    /**
     * {@inheritdoc}
     */
    public function getExperiment($experiment, $name, $default = null)
    {
        if (isset($this->get($name)[$experiment])){
            return $this->get($name)[$experiment];
        } else {
            return $default;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function set($name, $value)
    {
        $this->data[$name] = $value;

        return Session::set($this->sessionName, $this->data);
    }

    /**
     * {@inheritdoc}
     */
    public function setExperiment($experiment, $name, $value)
    {
        $data = $this->get($name, []);
        $data[$experiment] = $value;

        return $this->set($name, $data);
    }

    /**
     * {@inheritdoc}
     */
    public function clear()
    {
        $this->data = [];

        return Session::forget($this->sessionName);
    }

}
