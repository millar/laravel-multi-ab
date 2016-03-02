<?php namespace Millar\AB\Session;

interface SessionInterface {

    /**
     * Returns an attribute.
     *
     * @param string $name    The attribute name
     * @param mixed  $default The default value if not found.
     *
     * @return mixed
     *
     * @api
     */
    public function get($name, $default = null);

    /**
     * Returns an attribute for given experiment.
     *
     * @param string $experiment    The experiment name
     * @param string $name    The attribute name
     * @param mixed  $default The default value if not found.
     *
     * @return mixed
     *
     * @api
     */
    public function getExperiment($experiment, $name, $default = null);

    /**
     * Sets an attribute.
     *
     * @param string $name
     * @param mixed  $value
     *
     * @api
     */
    public function set($name, $value);

    /**
     * Sets an attribute for given experiment.
     *
     * @param string $experiment
     * @param string $name
     * @param mixed  $value
     *
     * @api
     */
    public function setExperiment($experiment, $name, $value);

    /**
     * Clears all attributes.
     *
     * @api
     */
    public function clear();

}
