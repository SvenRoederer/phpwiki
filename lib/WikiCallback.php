<?php

/**
 * A callback
 *
 * This is a virtual class.
 *
 * Subclases of WikiCallback can be used to represent either
 * global function callbacks, or object method callbacks.
 *
 * @see WikiFunctionCb, WikiMethodCb.
 */
abstract class WikiCallback
{
    /**
     * Call callback.
     *
     * @param ? mixed This method takes a variable number of arguments (zero or more).
     * The callback function is called with the specified arguments.
     * @return mixed The return value of the callback.
     */
    public function call()
    {
        return $this->call_array(func_get_args());
    }

    /**
     * Call callback (with args in array).
     *
     * @param $args array Contains the arguments to be passed to the callback.
     * @return mixed The return value of the callback.
     * @see call_user_func_array.
     */
    abstract public function call_array($args);

    /**
     * Convert to Pear callback.
     *
     * @return string The name of the callback function.
     *  (This value is suitable for passing as the callback parameter
     *   to a number of different Pear functions and methods.)
     */
    abstract public function toPearCb();
}

/**
 * Global function callback.
 */
class WikiFunctionCb
    extends WikiCallback
{
    /**
     * @param string $functionName Name of global function to call.
     */
    public function __construct($functionName)
    {
        $this->functionName = $functionName;
    }

    function call_array($args)
    {
        return call_user_func_array($this->functionName, $args);
    }

    function toPearCb()
    {
        return $this->functionName;
    }
}

/**
 * Object Method Callback.
 */
class WikiMethodCb
    extends WikiCallback
{
    /**
     * @param object $object Object on which to invoke method.
     * @param string $methodName Name of method to call.
     */
    function __construct(&$object, $methodName)
    {
        $this->object = &$object;
        $this->methodName = $methodName;
    }

    function call_array($args)
    {
        $method = &$this->methodName;
        return call_user_func_array(array(&$this->object, $method), $args);
    }

    function toPearCb()
    {
        return array($this->object, $this->methodName);
    }
}

/**
 * Anonymous function callback.
 */
class WikiAnonymousCb
    extends WikiCallback
{
    /**
     * @param string $args Argument declarations
     * @param string $code Function body
     * @see create_function().
     */
    function __construct($args, $code)
    {
        $this->function = create_function($args, $code);
    }

    function call_array($args)
    {
        return call_user_func_array($this->function, $args);
    }

    function toPearCb()
    {
        trigger_error("Can't convert WikiAnonymousCb to Pear callback",
            E_USER_ERROR);
    }
}
