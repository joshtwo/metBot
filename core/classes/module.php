<?php
class module
{
    protected $sysname;
    protected $name;
    protected $version;
    protected $info;

    // this should have never been anything except a fucking boolean
    private $switch = true;
    public $commands;
    private $Event;

    final function __construct($Event)
    {
        $this->Event = $Event;
        $this->main();
    }

    // encapsulation boilerplate
    function getSysName() { return $this->sysname; }
    //function setSysName($sysname) { $this->sysname = $sysname; }
    
    function getName() { return $this->name; }
    //function setName($name) { $this->name = $name; }

    function getVersion() { return $this->version; }
    // We don't care if the version is a string
    //function setVersion($version) { $this->version = $version; }

    function getInfo() { return $this->info; }
    //function setInfo($info) { $this->info = $info; }

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
            $switch = !$switch;
    }


    final function addCmd($command, $privs=0, $help=NULL)
    {
        $this->commands[$command] = new command($privs, $help);
    }
    
    final function hasCmd($command)
    {
        return (is_array($this->commands) && in_array($command, array_keys($this->commands)));
    }
    
    final function hook($func, $event, $priority=10)
    {
        if (is_string($func))
            $func = get_class($this).'::'.$func;
        elseif (!is_callable($func))
            throw Exception('Must use a callable or the name of a member function to hook to a class.');
        $this->Event->hook($func, $event, $priority);
    }
    
    final function unhook($func, $event, $priority=10)
    {
        if (is_string($func))
            $func = get_class($this).'::'.$func;
        elseif (!is_callable($func))
            throw Exception('Must use a callable or the name of a member function to hook to a class.');
        $this->Event->unhook($func, $event, $priority);
    }
    
    final function setMethod($command, $method)
    {
        if (isset($this->commands[$command]))
        {
            $this->commands[$command]->method = $method;
        }
        else return false;
    }
        

    function main()
    {
        throw new Exception(get_class($this).": module does not define its own 'main' method");
    }
    
    final function save($data, $filename)
    {
        is_dir("./data/module") || mkdir("./data/module");
        is_dir("./data/module/".get_class($this)) || mkdir("./data/module/".get_class($this));
        //$contents = json_encode($data);
        $contents = serialize($data);
        //if(!($f = fopen("./data/module/".get_class($this)."/$filename.json", "w"))) return false;
        if(!($f = fopen("./data/module/".get_class($this)."/$filename.bot", "w"))) return false;
        if(!fwrite($f, $contents)) return false;
        fclose($f);
        return true;
    }
    
    final function load(&$var, $filename)
    {
        //if (!file_exists("./data/module/".get_class($this)."/$filename.json"))
            $var = @unserialize(@file_get_contents("./data/module/".get_class($this)."/$filename.bot"));
        //else
        //    $var = @json_decode(@file_get_contents("./data/module/".get_class($this)."/$filename.json"));
        if (!$var) return false;
        else return true;
    }
    
    final function alias($cmd, $alias)
    {
        if (isset($this->commands[$cmd]))
        {
            $this->commands[$alias] = clone $this->commands[$cmd];
            $this->commands[$alias]->help = "This is the alias of the \"$cmd\" command.";
            $this->commands[$alias]->method = ($this->commands[$cmd]->method==null?"c_$cmd":$this->commands[$cmd]->method);
            $this->commands[$alias]->alias = $cmd;
            return true;
        }
        else return false;
    }
    
    final function removeAlias($alias)
    {
        if ($this->commands && isset($this->commands[$alias]) && $this->commands[$alias]->alias)
        {
            unset($this->commands[$alias]);
            return true;
        }
        else return false;
    }
    
    final function hasAlias($alias)
    {
        return $this->commands && in_array($alias, array_keys($this->commands)) && $this->commands[$alias]->alias;
    }
}
?>
