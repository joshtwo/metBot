<?php

class Commands
{
    private $bot;

    function __construct($bot)
    {
        $this->bot = $bot;
    }

    function execute($command)
    {
        $ns = $this->bot->Event->getNs();
        $from = $this->bot->Event->getFrom();
        $args = $this->bot->Event->getArgs();
        if (in_array(strtolower($ns), $this->bot->disabledRooms) && $from != $this->bot->admin)
            return;
        $found_command = false;
        foreach($this->bot->Modules->mods as $key => $value)
        {
            if (isset($this->bot->Modules->mods[$key]->commands[$command]))
            {
                $found_command = true;
                if (!$this->bot->Modules->mods[$key]->isOn())
                {
                    if ($this->bot->warnings)
                        $this->bot->dAmn->say("The module that the \"$command\" command is attached to is currently turned off.", $ns);
                    return;
                }
                else if ($this->bot->Modules->mods[$key]->commands[$command]->isOn())
                {
                    if ($this->has_privs($from, $command))
                    {
                        if (!$this->bot->Modules->mods[$key]->commands[$command]->method)
                            $command = "c_".$command;
                        else
                            $command = $this->bot->Modules->mods[$key]->commands[$command]->method;
                        $cmd = new EventData($ns, $from, $this->bot->Event->getPacket(), $args);
                        $this->bot->Modules->mods[$key]->$command($cmd, $this->bot);
                        return;
                    }
                    else//if ($this->bot->Event->cmd != $command)
                    {
                        if ($this->bot->warnings)
                            $this->bot->dAmn->say("You do not have a high enough priv level to access this command. The minimum priv level to use this command is ". $this->bot->Modules->mods[$key]->commands[$command]->privs .".", $ns);
                        return;
                    }
                }
                else if (!$this->bot->Modules->mods[$key]->commands[$command]->isOn())
                {
                    if ($this->bot->warnings)
                        $this->bot->dAmn->say("The \"$command\" command is currently turned off.", $ns);
                    return;
                }
                else
                {
                    if ($this->bot->warnings)
                        $this->bot->dAmn->say("The \"$command\" command must be attached to a module.", $ns);
                    return;
                }
            }
        }
        if (!$found_command)
            if ($this->bot->warnings)
                $this->bot->dAmn->say("There is no command \"$command\".", $ns);
        $this->bot->Event->setArgs(null);
    }

    function has_privs($user, $command)
    {
        $level = 0;
        foreach($this->bot->privs as $lvl => $members)
        {
            if (in_array($user, $members))
            {
                $level = $lvl;
                break;
            }
        }

        if ($this->bot->noGuests && $level == 0) return false;

        foreach($this->bot->Modules->mods as $key => $value)
        {
            if (isset($this->bot->Modules->mods[$key]->commands[$command]))
                return ($this->bot->Modules->mods[$key]->commands[$command]->privs <= $level);
        }
    }

    function process($who, $str)
    {
        $this->bot->Event->setFrom($who);
        // no Chromacity
        $str = preg_replace('/<abbr title="colors:[0-9A-Z]{6}:[0-9A-Z]{6}"><\/abbr>$/', '', $str);
        // check if this command has arguments, and if so, separate
        // the command name from them
        if (($pos = strpos($str, ' ')) !== false)
            $name = substr($str, 0, $pos);
        else
            $name = $str;
        $this->bot->Event->setArgs($args = substr($str, strlen($name)+1));
        if (strlen($name) > 0)
        {
            // you should REALLY remove this, what a dumb fucking idea
            if ($args == "?")
            {
                $this->bot->Event->setArgs($name);
                $this->execute('help');
            }
            else
                $this->execute($name);
        }
    }
}
