<?php
class Event
{
    // the list of events
    private $evts;

    // the core class
    private $bot;

    // the different attributes of the current event
    private $pkt;
    private $ns;
    private $from;
    private $args;
    
    function __construct($bot)
    {
        $this->bot = $bot;
    }

    // set the packet we're using as the current event to process
    function setPacket(Packet $pkt) { $this->pkt = $pkt; }
    function getPacket() { return $this->pkt; }

    // set the namespace of the current event
    function setNs($ns) { $this->ns = $ns; }
    function getNs() { return $this->ns; }

    // set the target user of the current event
    function setFrom($from) { $this->from = $from; }
    function getFrom() { return $this->from; }

    // set the arguments of the current event
    // we want to make this no longer necessary once we convert the command
    // system to using events and a module to be triggered
    function setArgs($args) { $this->args = $args; }
    function getArgs() { return $this->args; }

    // TODO: possibly make events system more expressive
    // de-facto event handling still captures a lot
    // possibilities:
    //
    // $this->hook('e_msg', 'recv:msg');
    // $this->hook('e_users', 'recv:admin.users');
    // $this->hook('e_pc', 'recv:admin.show/p=privclass');
    // $this->hook('e_whois', 'get/e=bad namespace, property/p=info');
    //
    // Returns bool stating if the given event matches the current dAmn packet.
    function received($type)
    {
        $type = explode('_', $type);
        $match = true;

        if (isset($type[0]))
            $match = $match && $type[0] == $this->pkt->cmd;
        if (isset($type[1]))
            $match = $match && $type[1] == $this->pkt->body->cmd;
        if (isset($type[2]))
            $match = $match && $type[2] == $this->pkt->body->param;
        if (isset($type[3]))
            $match = $match && $type[3] == $this->pkt->body['p'];

        return $match;
    }

    // Hooks an event 
    function hook($func, $event, $priority=10)
    {
        if (!isset($this->evts[$event]))
            $this->evts[$event] = array();
        if (!isset($this->evts[$event][$priority]))
            $this->evts[$event][$priority] = array();
        $this->evts[$event][$priority][] = $func;
    }

    // returns true if the event was found and removed, and false if not
    // make sure you give the correct priority
    function unhook($func, $event, $priority=10)
    {
        if (isset($this->evts[$event]) && isset($this->evts[$event][$priority]))
        {    
            if (($key = array_search($func, $this->evts[$event][$priority])) !== false)
            {
                unset($this->evts[$event][$priority][$key]);
                return true;
            }
            else
                return false;
        }
        else
            return false;
    }

    // hooks an event that will only run once
    function hookOnce($func, $event, $priority=10)
    {
        $_this = $this;
        $this->hook(
            function($evt, $bot) use ($func, $event, $priority, $_this) {
                $func();
                $_this->unhook($func, $event, $priority);
            },
            $event,
            $priority
        );
    }

    // run all the hooks attached to a certain event
    // evt is the event name
    // cascade means trigger all sub events. ex. if you trigger recv and it
    // cascades, that also triggers recv_msg, recv_join, recv_kicked, etc.
    // args is any additional arguments to pass in the event
    //
    // if any event hook returns something, the execution is stopped and the
    // return value is whatever that hook returned
    function trigger($type, $cascade=false, $args=null)
    {
        if (!$cascade)
        {
            if (isset($this->evts[$type]))
            {
                $priorities = array_keys($this->evts[$type]);
                sort($priorities); // if only I could do this in my life
                foreach($priorities as $p)
                    foreach ($this->evts[$type][$p] as $e)
                    {
                        $evt = new EventData($this->ns, $this->from, $this->pkt, $args);
                        if (is_string($e))
                        {
                            list($mod, $func) = explode('::', $e);
                            $result = $this->bot->Modules->mods[$mod]->$func($evt, $this->bot);
                            if (isset($result))
                                return $result;
                        }
                        else
                            $e($evt, $this->bot);
                    }
            }
        }
        else
        {
            $type = explode('_', $type);
            foreach (array_keys($this->evts) as $name)
            {
                $match = true;
                $_name = explode('_', $name);
                for ($i = 0; $i < count($type) && $i < count($name); ++$i)
                    if ($type[$i] != $_name[$i]) // is it a sub-event?
                    {
                        $match = false;
                        break;
                    }
                if ($match)
                {
                    $result = $this->trigger($name, false, $args);
                    if (isset($result))
                        return $result;
                }
            }
        }
    }
    
    function process()
    {
        if ($this->evts != null)
            foreach(array_keys($this->evts) as $name)
                if ($this->received($name) && $this->from != $this->bot->username)
                    $this->trigger($name);
    }

    // run the events hooked to "loop"
    function loop()
    {
        $this->trigger('loop');
    }
}
?>
