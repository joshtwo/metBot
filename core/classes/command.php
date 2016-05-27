<?php
class command
{
    public $method;
    public $privs;
    public $help;
    private $switch = true;
    public $alias = false;

    function isOn() { return $this->switch; }

    // Change status to on/off if argument given is true/false. If no argument
    // is given, then simply switch the status. You may want to extend this and
    // put in some code to do maintenance when your module is turned on/off
    // such as hooking and unhooking certain events.
    function setSwitch(bool $status=null)
    {
        if ($status)
            $this->switch = $status;
        else
            $this->switch = !$switch;
    }

    function __construct($privs, $help)
    {
        $this->privs = $privs;
        $this->help = $help;
    }
}
?>
