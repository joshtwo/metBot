<?php
class System extends module
{
    protected $sysname = "System";
    protected $name = "System Commands";
    protected $version = "1.5";
    protected $info = "These are the default system commands.";
    public $noerror = false;

    function main()
    {
        $this->addCmd('about', 0, "This tells you about the bot.");
        $this->addCmd('commands', 0, "This shows you the bot's commands.<sub><ul><li>Type <b>{trigger}commands</b> to show the commands you have access to.</li><li>Type <b>{trigger}commands all</b> to show all the commands the bot has.</li><li>Type <b>{trigger}commands privs</b> to show commands grouped by the minimum required privclass level needed to use them.</li><li>Type <b>{trigger}commands <i>module1 module2 module3</i></b> to only show commands from <i>module1</i>, <i>module2</i> and <i>module3</i>.</li></ul>");
        $this->addCmd('help', 0, "This gives you help on the commands that have help documentation. Used <b>{trigger}help <i>command</i></b>");
        $this->addCmd('module', 0, "This command shows info about the bot's modules.<sub><ul><li>Type <b>{trigger}module info <i>module</i></b> to get info on the module <i>module</i>.</li><li>Type <b>{trigger}module on/off <i>module</i></b> to turn the module <i>module</i> on and off.</li></ul></sub>");
        $this->addCmd('modules', 0, "This lists the modules in the bot.");
        $this->addCmd('quit', 75, "This makes your bot quit dAmn. Can optionally pass the argument \"quiet\" to make it quit silently.");
        $this->addCmd('restart', 75, "This makes your bot restart completely.<sub><ul><li>Type <b>{trigger}restart quiet</b> to make it quit silently.<li><li>Type <b>{trigger}restart <i>args</i><b> to restart the bot with the arguments <i>args</i> instead of the arguments given at startup. All subsequent restarts will also use these arguments unless you set new ones using this command.</li></ul></sub>");
        $this->addCmd('sudo', 100, "Does a command as another user. Used <b>{trigger}sudo <i>person</i> <i>command</i> <i>arguments</i></b>. Ex: <code>{trigger}sudo Noobobob123 away Noobob is away</code> would run as if Noobob123 said <code>{trigger}away Noobob is away</code>.");
        $this->addCmd('autojoin', 75, "Manage the list of autojoined channels.<sub><ul><li>Use <b>{trigger}autojoin list</b> to show the list of autojoined channels.</li><li>Use <b>{trigger}autojoin add <i>channel</i></b> to add #<i>channel</i> to the bot's autojoin list.</li><li>Use <b>{trigger}autojoin del <i>channel</i></b> to remove #<i>channel</i> from the bot's autojoin list.</li></ul></sub>");
        $this->addCmd('trigger', 50, "Changes the bot's trigger. {trigger}trigger add/del <i>trigger</i> [<i>room</i>] | {trigger}trigger set <i>primaryTrigger</i>");
        $this->addCmd('warn', 50, "Turns command warnings on and off. Ex: You try to do a command you don't have the privs to, and the bot tells you that you don't have the privs to if warnings are on. Use <b>{trigger}warn on/off</b> to turn warnings on or off.");
        $this->addCmd('e', 100, "Execute PHP code. If the code returns anything, the bot will say it in the channel the code was executed in. Type <b>{trigger}e <i>php code</i></b> to execute <i>php code</i> with the bot.");
        $this->addCmd('r', 100, "Prepend PHP code with a return statement and execute it. <sub><ul><li>Type <b>{trigger}r <i>php code</i></b> to prepend <i>php code</i> with a return statement and execute it.</li><li>Typing <code>{trigger}r 1+1;</code> is identical to typing <code>{trigger}e return 1+1;</code></li></ul></sub>");
        $this->addCmd('alias', 75, "Manage your command aliases. <sub><ul><li>Use <b>{trigger}alias add <i>command</i> <i>alias</i></b> to add the alias <i>alias</i> for <i>command</i>.</li><li>Use <b>{trigger}alias del <i>alias</i></b> to remove the alias <i>alias</i>.</li></ul></sub>.");
        $this->addCmd('disable', 75, "Disable commands in chatrooms. {trigger}disable add/del <i>room</i> | {trigger}disable list");
        $this->addCmd('guests', 75, "Enable/disable guests access to the bot's commands. {trigger}guests on/off");
    }

    function c_about($cmd, $bot)
    {
        $uptime = $bot->uptime();
        $bot->dAmn->say("<abbr title=\"$cmd->from\"></abbr>Hello! I'm running <b><a href=\"http://github.com/joshtwo/metBot\" title=\"". $bot->name." ".$bot->version."\">".$bot->name ." ". $bot->version."</a></b> by <b>:devdeviant-garde:</b>. My owner is <b>:dev". $bot->admin .":</b>. I've been up for $uptime.", $cmd->ns);
    }

    function c_commands($cmd, $bot)
    {
        if ($cmd->args != "privs")
        {
            $unusable = array();
            $commands_info = array();
            $modList = array();
            ksort($bot->Modules->mods);
            if ($cmd->args == "all")
                $all = true;
            else
                $all = false;

            if ($cmd->args && $cmd->args != "all")
                $modList = explode(' ', $cmd->args);

            $not_privd = 0;
            foreach($bot->Modules->mods as $mod => $val)
            {
                if ($modList && !in_array($val->getSysName(), $modList))
                {
                    $unusable[] = $mod;
                    continue;
                }

                if ($val->commands)
                {
                    foreach($val->commands as $key => $value)
                    {
                        $commands_info[$key] = clone $value;
                        $commands_info[$key]->module = $mod;
                        if (!$all)
                        {
                            if (!$bot->Commands->has_privs($cmd->from, $key))
                                $not_privd++;
                        }
                    }
                    if ($not_privd == count($val->commands))
                        $unusable[] = $mod;
                }
                else
                    $unusable[] = $mod;
                $not_privd = 0;
            }

            $txt = "<abbr title=\"$cmd->from\"></abbr>";
            if (!$all)
                $txt .= "You have access to the following commands. ";
            $txt .= "Commands that are <s>striked</s> are disabled.<br><br><sub>";
            $cmds = array();
            foreach($bot->Modules->mods as $mod => $val)
            {
                if (in_array($mod, $unusable))
                {
                    continue;
                }
                $txt .= "<b><u>".$val->getSysName()."</u></b>: ";
                foreach($commands_info as $key => $value)
                {
                    if (!isset($commands_info[$key]->module)) continue;
                    if ($commands_info[$key]->module == $mod)
                    {
                        if (!$commands_info[$key]->isOn() || !$bot->Modules->mods[$mod]->isOn())
                        {
                            if (!$all && !$bot->Commands->has_privs($cmd->from, $key)) $txt .= '';
                            else
                            {
                                $cmds[$key] = "<abbr title=\"must be level ".$commands_info[$key]->privs." to use\"><i>$key</i></abbr>";
                            }
                        }
                        elseif ($commands_info[$key]->isOn() && $bot->Modules->mods[$mod]->isOn())
                        {
                            if (!$all && !$bot->Commands->has_privs($cmd->from, $key)) $txt.= '';
                            else
                            {
                                $cmds[$key] = "<abbr title=\"must be level ".$commands_info[$key]->privs." to use\">$key</abbr>";
                            }
                        }
                    }
                }
                ksort($cmds);
                $txt .= join(', ', $cmds);
                $txt .= "<br>";
                $cmds = array();
            }
            $txt .= "<br>Type <code>". $bot->trigger ."help </code><i><code>command</code></i> to get help on a command.<br>Type <code>".$bot->trigger."commands privs</code> to see commands grouped by privilege level.</sub>";
            $txt .= $all == false? "<sub><br>Type <code>". $bot->trigger ."commands all</code> to see commands you do not have access to as well.</sub>" : '';
            $bot->dAmn->say($txt, $cmd->ns);
        }
        elseif($cmd->args == "privs")
        {
            $txt = "<abbr title=\"$cmd->from\"></abbr>Commands that are <s>striked</s> are disabled.<br><br><sub>";
            $cmds = array();
            $used_levels = array();

            foreach($bot->Modules->mods as $mod => $val)
            {
                foreach($val->commands as $key => $value)
                {
                    $commands_info[$key] = $value;
                    $commands_info[$key]->module = $mod;
                    if (!in_array($val->commands[$key]->privs, $used_levels))
                    {
                        $used_levels[] = $val->commands[$key]->privs;
                    }
                }
            }

            foreach($bot->levels as $lvl => $name)
            {
                if (!in_array($lvl, $used_levels)) continue;
                $txt .= "<b><u>$name ($lvl):</u></b> ";
                foreach ($commands_info as $command => $info)
                {
                    if ($info->privs == $lvl)
                    {
                        $cmds[] = "<abbr title=\"$info->module\">$command</abbr>";# $command;
                    }
                }
                $txt .= join(', ', $cmds);
                $txt .= '<br>';
                $cmds = array();
            }
            $txt .= "<br>Type <code>". $bot->trigger ."help </code><i><code>command</code></i> to get help on a command.</sub>";
            $txt .=  "<sub><br>Type <code>". $bot->trigger ."commands all</code> to see commands you do not have access to as well.</sub>" ;
            $bot->dAmn->say($txt, $cmd->ns);
        }
    }

    function c_help($cmd, $bot)
    {
        $help_text = NULL;
        if($cmd->args)
        {
            $help_command = $cmd->arg(0);

            if ($cmd->arg(1) != -1)
            {
                $cmd->from = $cmd->arg(1, true);
            }

            foreach($bot->Modules->mods as $key => $value)
            {
                if (isset($bot->Modules->mods[$key]->commands[$help_command]->help))
                {
                    $help_text = $bot->Modules->mods[$key]->commands[$help_command]->help;
                }
            }


            if ($help_text == null)
            {
                $bot->dAmn->say("$cmd->from: There is no help text for \"$help_command\".", $cmd->ns);
                return;
            }
            $help_text = str_replace('{trigger}', $bot->trigger, $help_text);
            $bot->dAmn->say("<abbr title=\"". $cmd->from ."\"></abbr><b>". $help_command .":</b> ". $help_text, $cmd->ns);
        }
        else
        {
            $bot->dAmn->say("$cmd->from: Please specify the command you want help with.", $cmd->ns);
        }
    }

    function c_modules($cmd, $bot)
    {
        $module_names = array_keys($bot->Modules->mods);
        ksort($module_names);
        $a = array();
        $txt = "Modules that are <s>striked</s> are turned off.<br><sub>";
        foreach($module_names as $mod)
        {
            $sysname = $bot->Modules->mods[$mod]->getSysName();
            if ($bot->Modules->mods[$mod]->isOn())
            {
                $a[]= "$sysname";
            }
            else
            {
                $a[]= "<i>$sysname</i>";
            }
        }
        $txt .= join(', ', $a);
        $txt .= "</sub>";

        $bot->dAmn->say("<abbr title=\"$cmd->from\"></abbr>$txt", $cmd->ns);
    }

    function c_module($cmd, $bot)
    {
        $command = $cmd->arg(0);
        $module = $cmd->arg(1);

        if ($command != -1)
        {
            if ($command == "info")
            {
                if ($module != -1)
                {
                    $name = '';
                    $version = '';
                    $info = '';
                    $switch = '';
                    $commands = '';
                    $found = false;
                    foreach($bot->Modules->mods as $key => $mod)
                    {
                        if ($mod->getSysName() == $module)
                        {
                            $name = $mod->getName();
                            $version = $mod->getVersion();
                            $info = $mod->getInfo();
                            $switch = $mod->isOn() ? "on" : "off";
                            $commands = $mod->commands;
                            $found = true;
                            break;
                        }
                    }
                    if ($found)
                    {
                        $txt .= "<b><u>$module</u></b><sub><br>";
                        $txt .= "<b>Name</b>: $name<br>";
                        $txt .= "<b>Version</b>: $version<br>";
                        $txt .= "<b>Info</b>: $info<br>";
                        $txt .= "<sub>This module is <b>$switch</b>.<br><br>";
                        $txt .= "</sub><b><u>Commands:</u></b><br><sub>";
                        foreach($commands as $command => $the)
                        {
                            if ($the->isOn())
                            {
                                $txt .= "<abbr title=\"must be level ". $the->privs ."\">$command</abbr>, ";
                            }
                            else
                            {
                                $txt .= "<abbr title=\"must be level ". $the->privs ."\"><i>$command</i></abbr>, ";
                            }
                        }
                        $bot->dAmn->say("<abbr title=\"$cmd->from\"></abbr>$txt", $cmd->ns);
                    }
                    else
                    {
                        $bot->dAmn->say("$cmd->from: There is no \"$module\" module.", $cmd->ns);
                    }
                }
                else
                {
                    $bot->dAmn->say("$cmd->from: What module do you want info for?", $cmd->ns);
                }
            }
            elseif ($command == "on")
            {
                if ($module != -1)
                {
                    if (!in_array($cmd->from, $bot->privs[100]))
                    {
                        $bot->dAmn->say("$cmd->from: You are not a high enough level to turn on modules.", $cmd->ns);
                        return;
                    }
                    $found = true;
                    $already_on = false;
                    foreach ($bot->Modules->mods as $key => $mod)
                    {
                        if ($module == $mod->getSysName())
                        {
                            if ($mod->switch->isOn())
                                $already_on = true;
                            else
                            {
                                $bot->Modules->module[$module]->setSwitch(true);
                                $found = true;
                            }
                        }
                    }

                    if ($found == true)
                    {
                        if ($already_on == true)
                        {
                            $bot->dAmn->say("$cmd->from: $module is already on.", $cmd->ns);
                        }
                        else
                        {
                            $bot->dAmn->say("$cmd->from: Module <b>$module</b> is now turned on.", $cmd->ns);
                        }
                    }
                }
                else
                {
                    $bot->dAmn->say("$cmd->from: What module do you want to turn off?", $cmd->ns);
                }
            }
            elseif ($command == "off")
            {
                if ($module != -1)
                {
                    if (!in_array($cmd->from, $bot->privs[100]))
                    {
                        $bot->dAmn->say("$cmd->from: You do not have a high enough level to turn modules off.", $cmd->ns);
                        return;
                    }
                    $found = true;
                    $already_off = false;
                    foreach ($bot->Modules->mods as $key => $mod)
                    {
                        if ($module == $mod->getSysName())
                        {
                            if (!$mod->isOn())
                                $already_off = true;
                            else
                            {
                                $bot->Modules->mods[$module]->setSwitch(false);
                                $found = true;
                            }
                        }
                    }

                    if ($found == true)
                    {
                        if ($already_off == true)
                        {
                            $bot->dAmn->say("$cmd->from: $module is already off.", $cmd->ns);
                        }
                        else
                        {
                            $bot->dAmn->say("$cmd->from: Module <b>$module</b> is now turned off.", $cmd->ns);
                        }
                    }
                }
                else
                {
                    $bot->dAmn->say("$cmd->from: What module do you want to turn off?", $cmd->ns);
                }
            }
            else
            {
                $bot->Event->setArgs("module");
                $bot->Commands->execute("help");
            }
        }
        else
        {

            $bot->Event->setArgs("module");
            $bot->Commands->execute("help");
        }
    }

    function c_quit($cmd, $bot)
    {
        $uptime = $bot->uptime();
        if ($cmd->arg(0) == -1)
        {
            $bot->dAmn->say("$cmd->from: Quitting. Uptime: $uptime", $cmd->ns);
        }
        elseif ($cmd->arg(0) != "quiet")
        {
            $bot->dAmn->say("$cmd->from: Unknown argument. Command is {$bot->trigger}quit or {$bot->trigger}quit quiet.", $cmd->ns);
            return;
        }
        if (!is_dir('./core/status'))
            mkdir('./core/status');
        $fp = fopen('./core/status/close.bot', 'w');
        fclose($fp);
        // TODO: Shouldn't have to send this raw. Make a dAmn::quit()
        $bot->dAmn->send("disconnect\n\0");
        $bot->quit = true;
        $bot->input = false;
    }

    function c_restart($cmd, $bot)
    {
        $uptime = $bot->uptime();
        if (!is_dir('./core/status'))
            mkdir('./core/status');
        if ($cmd->arg(0) == -1)
        {
            $bot->dAmn->say("$cmd->from: Restarting. Uptime: $uptime", $cmd->ns);
            $fp = fopen('./core/status/restart.bot', 'w');
            fclose($fp);
        }
        elseif ($cmd->arg(0) != "quiet")
        {
            //$bot->dAmn->say("$cmd->from: Unknown argument. Command is {$bot->trigger}quit or {$bot->trigger}quit quiet.", $cmd->ns);
            //return;
            file_put_contents('./core/status/restart.bot', $cmd->args);
        }
        else
        {
            $fp = fopen('./core/status/restart.bot', 'w');
            fclose($fp);
        }
        $bot->dAmn->send("disconnect\n\0");
        $bot->Console->msg("Restarting bot...");
        $bot->quit = true;
        $bot->restart = true;
        $bot->input = false;
    }

    function c_sudo($cmd, $bot)
    {

        $bot->Event->setFrom($cmd->arg(0));
        $command = $cmd->arg(1);
        $bot->Event->setArgs(($args = $cmd->arg(2, true)) != -1 ? $args : null);
        $bot->Commands->execute($command);
    }

    function c_autojoin($cmd, $bot)
    {
        $command = $cmd->arg(0);
        $channel = $cmd->arg(1);
        if ($command != -1)
        {
            if ($command == "add")
            {
                if ($channel != -1)
                {
                    $bot->join[] = $bot->dAmn->format($channel);
                    $bot->dAmn->say("$cmd->from: Channel <b>". $bot->dAmn->deform($channel) ."</b> has been added to the autojoin list.", $cmd->ns);
                    $bot->saveConfig();
                }
                else
                {
                    $bot->dAmn->say("$cmd->from: What channel do you want to add?", $cmd->ns);
                }
            }
            elseif ($command == "del")
            {
                if ($channel != -1)
                {
                    $match = false;
                    foreach ($bot->join as $key => $j)
                    {
                        if (strtolower($j) == strtolower($bot->dAmn->format($channel)))
                        {
                            array_splice($bot->join, $key, 1);
                            $match = true;
                        }
                    }

                    if ($match)
                    {
                        $bot->saveConfig();
                        $bot->dAmn->say("$cmd->from: Channel <b>". $bot->dAmn->deform($channel) ."</b> was removed.", $cmd->ns);
                    }
                    else
                    {
                        $bot->dAmn->say("$cmd->from: Channel ". $bot->dAmn->deform($channel) ." isn't on the autojoin list.", $cmd->ns);
                    }
                }
                else
                {
                    $bot->dAmn->say("$cmd->from: What channel do you want to delete?", $cmd->ns);
                }
            }
            elseif ($command == "list")
            {
                $chans = array();
                foreach ($bot->join as $j)
                {
                    $chans[]= $bot->dAmn->deform($j);
                }
                $bot->dAmn->say("<abbr title=\"$cmd->from\"></abbr><b>Autojoined Channels:</b><br><sub>".join(", ", $chans), $cmd->ns);
            }
            else
            {

                $bot->Event->setArgs("autojoin");
                $bot->Commands->execute("help");
            }
        }
        else
        {
            $bot->Event->setArgs("autojoin");
            $bot->Commands->execute("help");
        }
    }

    function c_trigger($cmd, $bot)
    {
        $command = $cmd->arg(0);
        $trigger = $cmd->arg(1);
        $room = $cmd->arg(2);

        switch($command)
        {
        case "add":
            if ($trigger != -1)
            {
                if ($room != -1)
                {
                    $bot->trigger->add($trigger, $bot->dAmn->format($room));
                    $bot->dAmn->say("$cmd->from: The trigger $trigger is now enabled for <b>".$bot->dAmn->deform($room)."</b>.", $cmd->ns);
                }
                else
                {
                    if (!$bot->trigger->contains($trigger))
                    {
                        $bot->trigger->add($trigger);
                        $bot->dAmn->say("$cmd->from: The trigger <b>$trigger</b> has been added for all rooms.", $cmd->ns);
                    }
                    else
                        $bot->dAmn->say("$cmd->from: Trigger $trigger already exists.", $cmd->ns);
                }
                $bot->saveConfig();
            }
            else
                $bot->dAmn->say("$cmd->from: You must supply a trigger to add, and optionally a room to enable it for.", $cmd->ns);
            break;
        case "del":
            if ($trigger != -1)
            {
                if ($bot->trigger->contains($trigger))
                {
                    if ($room != -1)
                    {
                        $bot->trigger->del($trigger, $bot->dAmn->format($room));
                        $bot->dAmn->say("$cmd->from: The trigger $trigger is now disabled for <b>".$bot->dAmn->deform($room)."</b>.", $cmd->ns);
                    }
                    else
                    {
                        $bot->trigger->del($trigger);
                        $bot->dAmn->say("$cmd->from: The trigger <b>$trigger</b> has been deleted for all rooms.", $cmd->ns);
                    }
                }
                else
                {
                    $bot->dAmn->say("$cmd->from: The trigger $trigger does not exist.", $cmd->ns);
                }
                $bot->saveConfig();
            }
            else
                $bot->dAmn->say("$cmd->from: You must either supply a trigger to delete, or both a trigger and a room to disable a trigger in.", $cmd->ns);
            break;
        case "list":
            $list = array();
            foreach($bot->trigger->triggers as $trigger => $rooms)
            {
                $item = "<b>$trigger</b> - ";
                if (!$rooms)
                    $item .= 'global';
                else
                    for($i = 0; $i < count($rooms); ++$i)
                    {
                        if ($i != 0)
                            $item .= ', ';
                        $item .= $bot->dAmn->deform($rooms[$i]);
                    }
                $list[] = $item;
            }

            $bot->dAmn->say("<abbr title=\"$cmd->from\"></abbr><b>Trigger List</b><br><sub>".join("\n", $list), $cmd->ns);
            break;
        case "set":
            if ($trigger != -1)
            {
                $bot->trigger->set($trigger);
                $bot->dAmn->say("$cmd->from: Primary trigger set to <b>$trigger</b>", $cmd->ns);
                $bot->saveConfig();
            }
            else
                $bot->dAmn->say("$cmd->from: You must give a trigger to set as the primary trigger.", $cmd->ns);
            break;
        default:
            $bot->Event->setArgs("trig");
            $bot->Commands->execute("help");
        }
    }

    function c_warn($cmd, $bot)
    {
        $switch = strtolower($cmd->arg(0));
        if (!($switch == "on" || $switch == "off"))
            $bot->dAmn->say("$cmd->from: Valid arguments are \"on\" and \"off\".", $cmd->ns);
        else
            $switch = $switch == "on";

        if ($bot->warnings == $switch)
        {
            $bot->dAmn->say("$cmd->from: Warnings are already $switch.", $cmd->ns);
        }
        else
        {
            $bot->warnings = $switch;
            $bot->dAmn->say("$cmd->from: Warnings are now <b>$switch</b>.", $cmd->ns);
            $bot->saveConfig();
        }
    }

    function c_e($cmd, $bot)
    {
        $bot->Console->msg("Evaluating code...");
        $code = $cmd->arg(0, true);
        $return = eval('$this->noerror = true; '.$code);
        if ($this->noerror === FALSE)
        {
            $error = error_get_last();
            $types = array();
            $types['2'] = "Warning";
            $types['4'] = "Parse Error";
            $types['8'] = "Notice";
            $types['256'] = "User Error";
            $types['512'] = "User Warning";
            $types['1024'] = "User Notice";
            $types['4096'] = "Recoverable Error";
            $types['8192'] = "Deprecated Error";
            $types['16384'] = "Deprecated Error";
            $types['30719'] = "All Error";
            $e = $types[''.$error['type']].": $error[message]!";
            $bot->dAmn->say($e, $cmd->ns);
        }
        elseif ($return !== NULL)
        {
            switch(var_export($return, true))
            {
                case "false":
                    $return = "false";
                break;
                case "true":
                    $return = "true";
                break;
            }
            $bot->dAmn->say("Return value: <bcode>". print_r($return, true), $cmd->ns);
        }
        $this->noerror = false;
    }

    function c_r($cmd, $bot)
    {
        $cmd->args = 'return '.$cmd->args;
        $this->c_e($cmd, $bot);
    }

    function c_alias($cmd, $bot)
    {
        if (($action = $cmd->arg(0)) != -1)
        {
            switch($action)
            {
                case 'add':
                    if (($command = $cmd->arg(1)) != -1)
                    {
                        if (($alias = $cmd->arg(2)) != -1)
                        {
                            $module = null;
                            foreach($bot->Modules->mods as $name => $mod)
                            {
                                if ($mod->hasCmd($command))
                                {
                                    $module = $name;
                                    break;
                                }
                            }

                            if ($module == null)
                            {
                                $bot->dAmn->say("$cmd->from: There is no \"$command\" command.", $cmd->ns);
                            }
                            else
                            {
                                $bot->Modules->mods[$module]->alias($command, $alias);
                                $bot->dAmn->say("$cmd->from: <b>$alias</b> is now an alias for <b>$command</b>.", $cmd->ns);
                            }
                        }
                        else
                            $bot->dAmn->say("$cmd->from: You must specify an alias for \"$command\".", $cmd->ns);
                    }
                    else
                        $bot->dAmn->say("$cmd->from: What alias do you want for the command?", $cmd->ns);
                break;
                case 'del':
                    $module = null;
                    if (($alias = $cmd->arg(1)) != -1)
                    {
                        foreach($bot->Modules->mods as $name => $mod)
                        {
                            if($mod->hasAlias($alias))
                            {
                                $module = $name;
                                break;
                            }
                        }

                        if ($module == null)
                        {
                            $bot->dAmn->say("$cmd->from: \"$alias\" is not an alias.", $cmd->ns);
                        }
                        else
                        {
                            $command = $bot->Modules->mods[$module]->commands[$alias]->alias;
                            $bot->Modules->mods[$module]->removeAlias($alias);
                            $bot->dAmn->say("$cmd->from: The <b>$alias</b> alias has been removed.", $cmd->ns);
                        }
                    }
                break;
                default:
                    $bot->Event->setArgs("alias");
                    $bot->Commands->execute("help");
                break;
            }
        }
    }

    function c_disable($cmd, $bot)
    {
        $command = $cmd->arg(0);
        $room = $bot->dAmn->format($cmd->arg(1));

        switch($command)
        {
        case "add":
            if (!in_array($room, $bot->disabledRooms))
            {
                $bot->disabledRooms[] = $room;
                $bot->dAmn->say("$cmd->from: Commands shall no longer run in <b>".$bot->dAmn->deform($room)."</b>.", $cmd->ns);
                $bot->saveConfig();
            }
            else
                $bot->dAmn->say("$cmd->from: Commands are already disabled in this room.", $cmd->ns);
            break;
        case "del":
            if (($key = array_search($room, $bot->disabledRooms)) !== false)
            {
                array_splice($bot->disabledRooms, $key, 1);
                $bot->dAmn->say("$cmd->from: Commands are no longer disabled in <b>".$bot->dAmn->deform($room)."</b>.", $cmd->ns);
                $bot->saveConfig();
            }
            else
                $bot->dAmn->say("$cmd->from: Commands are not disabled in this room.", $cmd->ns);
            break;
        case "list":
            $rooms = array();
            foreach($bot->disabledRooms as $r) $rooms[] = $bot->dAmn->deform($r);
            $bot->dAmn->say("<abbr title=\"$cmd->from\"></abbr><b>Disabled Rooms</b><br><sub>".join('<br>', $rooms), $cmd->ns);
            break;
        default:
            $bot->Event->setArgs("disable");
            $bot->Commands->execute("help");
        }
    }

    function c_guests($cmd, $bot)
    {
        switch ($command = $cmd->arg(0))
        {
        case "on":
        case "off":
            $bot->noGuests = $command == "off";
            $bot->saveConfig();
            $bot->dAmn->say("$cmd->from: Guest access to commands is now turned <b>$command</b>.", $cmd->ns);
            break;
        default:
            $bot->dAmn->say("$cmd->from: Guest access to commands is currently <b>".($bot->noGuests ? "off" : "on")."</b>.", $cmd->ns);
        }
    }
}
?>
