<?php
// TODO: possibly rewrite this? This thing is years old and a little quirky
class Welcome extends module
{
    protected $sysname = "Welcome";
    protected $name = "Welcome";
    protected $version = "1";
    protected $info = "These commands are used to set welcomes.";
    public $welcomes = array();
    public $switches = array();
    public $indv = false;

    function main()
    {
        $this->addCmd("wt", 50, "Sets a welcome. The #<i>channel</i> parameter is optional. If you do not specify it, the welcome will be set for the current channel. In all welcomes, <b>{from}</b> is replaced with the name of the person that enters the room.<sub><ul><li>Use <b>{trigger}wt #<i>channel</i> on/off</b> to turn welcomes on and off.</li><li>Use <b>{trigger}wt #<i>channel</i> all <i>message</i></b> to welcome everyone that enters #<i>channel</i> with <i>message</i></li><li>Use <b>{trigger}wt #<i>channel</i> pc <i>privclass</i> <i>message</i></b> to welcome everyone that joins and is in the <i>privclass</i> privclass with <i>message</i>.</li><li>Use <b>{trigger}wt #<i>channel</i> indv/individual <i>message</i></b> to let people set their own welcomes with the {trigger}welcome command, with their welcomes coming after the message <i>message</i>. You do not have to set an individual welcome message.</li><li>Use <b>{trigger}wt show</b> to show the settings configured in the welcome module for all rooms.</li></ul>");
        $this->addCmd("welcome", 0, "When individual welcomes are on, this sets your welcome. Used <b>{trigger}welcome <i>message</i></b>.");
        $this->hook("do_welcome", "recv_join");
        $this->load($this->welcomes, "welcomes");
        $this->load($this->switches, "switch");
    }

    function c_wt($cmd, $bot)
    {
        $chan = $bot->dAmn->deform($cmd->ns());
        $ns = $bot->dAmn->format($chan);
        switch ($cmd->arg(0))
        {
            case "all":
                $msg = $cmd->arg(1, true);
                $this->welcomes[$ns]['all'] = $msg;
                $bot->dAmn->say("$cmd->from: The welcome <i>\"$msg\"</i> was set for all people in <b>$chan</b>.", $cmd->ns);
                if ($this->switches[$ns] != "on")
                    $this->switches[$ns] = "on";

                $this->save($this->welcomes, "welcomes");
                $this->save($this->switches, "switch");
            break;
            case "pc":
                $pc = $cmd->arg(1);
                $msg = $cmd->arg(2, true);
                $this->welcomes[$ns]['pc'][$pc] = $msg;
                $bot->dAmn->say("$cmd->from: The welcome <i>\"$msg\"</i> was set for all people in the privclass <b>$pc</b> in <b>$chan</b>.", $cmd->ns);
                if ($this->switches[$ns] != "on")
                    $this->switches[$ns] = "on";

                $this->save($this->welcomes, "welcomes");
                $this->save($this->switches, "switch");
            break;
            case "indv":
            case "individual":
                $this->indv = true;
                if ($cmd->arg(1) == -1)
                {
                    $bot->dAmn->say("$cmd->from: People can now set their own welcomes in <b>$chan</b>.", $cmd->ns);
                    if ($this->switches[$ns] != "on")
                        $this->switches[$ns] = "on";

                    $this->save($this->welcomes, "welcomes");
                    $this->save($this->switches, "switch");
                }
                else
                {
                    $this->welcomes[$ns]['indv']['all'] = $cmd->arg(1, true);
                    $bot->dAmn->say("$cmd->from: People can now set their own welcomes in <b>$chan</b>, coming after the message <i>\"".$cmd->arg(1, true)."\"</i>.", $cmd->ns);
                    if ($this->switches[$ns] != "on")
                        $this->switches[$ns] = "on";

                    $this->save($this->welcomes, "welcomes");
                    $this->save($this->switches, "switch");
                }
            break;
            case "on":
            case "off":
                if (is_array($this->switches))
                {
                    $this->switches[$ns] = $cmd->arg(0);
                }
                else
                {
                    $this->switches = array($ns => $cmd->arg(0));
                }
                $bot->dAmn->say("$cmd->from: Welcomes for $chan have been turned <b>".$cmd->arg(0)."</b>.", $cmd->ns);
                $this->save($this->switches, "switch");
            break;
            case "show":
                if ($this->welcomes != NULL)
                {
                    $count = 0;
                    $text = '';
                    foreach(array_keys($this->welcomes) as $key)
                    {
                        if (isset($this->welcomes[$key]['pc']))
                        {
                            if($this->welcomes[$key]['pc']==NULL)
                                unset($this->welcomes[$key]['pc']);
                        }

                        if (isset($this->welcomes[$key]['indv']))
                        {
                            if ($this->welcomes[$key]['indv'] == NULL)
                                unset($this->welcomes[$key]['indv']);
                            else
                            {
                                $copy = $this->welcomes;
                                unset($this->welcomes[$key]['indv']);
                            }
                        }


                        if ($this->welcomes[$key] == NULL)
                        {
                            if (is_array($copy))
                                $this->welcomes = $copy;

                            $copy = '';
                            continue;
                        }
                        $chan = $bot->dAmn->deform($key);
                        $text .= "<b>Settings for $key</b><br><sub>";
                        $text .= "Welcomes for this channel are <b>"
                            .$this->switches[$key]
                            ."</b>.<br>";
                        foreach ($this->welcomes[$key] as $type => $contents)
                        {
                            if ($type == "pc")
                            {
                                foreach($contents as $pc => $msg)
                                $text .= "<b>$pc</b> - $msg<br>";
                            }
                            if ($type == "indv" && isset($contents['all']))
                                $text .= "<b>Global Indv. Message</b> - ".$contents['all']."<br>";
                            if ($type == "all")
                                $text .= "<b>Global Room Message</b> - $contents<br>";
                        }
                        //echo $text."\n\n";
                        $text .= "<br></sub>";
                    }
                    if ($text != NULL)
                    {
                        $bot->dAmn->say($text, $cmd->ns);
                        $this->save($this->welcomes, "welcomes");
                    }
                    else $bot->dAmn->say("$cmd->from: There are no welcomes set.", $cmd->ns);
                }
                else $bot->dAmn->say("$cmd->from: There are no welcomes set.", $cmd->ns);
            break;
            case "del":
                if ($cmd->arg(1) == "all")
                {
                    if (isset($this->welcomes[$ns]['all']))
                    {
                        unset($this->welcomes[$ns]['all']);
                        $bot->dAmn->say("$cmd->from: The global welcome for <b>$chan</b> was deleted.", $cmd->ns);
                        $this->save($this->welcomes, "welcomes");
                    }
                    else
                    {
                        $bot->dAmn->say("$cmd->from: There is no global welcome for <b>$chan</b>", $cmd->ns);
                    }
                }
                elseif ($cmd->arg(1) == "pc")
                {
                    $pc = $cmd->arg(2);
                    if ($pc !=-1)
                    {
                        if (isset($this->welcomes[$ns]['pc'][$pc]))
                        {
                            unset($this->welcomes[$ns]['pc'][$pc]);
                            $bot->dAmn->say("$cmd->from: The <b>$pc</b> welcome for <b>$chan</b> was deleted.", $cmd->ns);
                            $this->save($this->welcomes, "welcomes");
                        }
                        else
                        {
                            $bot->dAmn->say("$cmd->from: There is no $pc welcome for$chan.", $cmd->ns);
                        }
                    }
                    else $bot->dAmn->say("$cmd->from: You must specify a privclass.", $cmd->ns);
                }
                elseif ($cmd->arg(1) == "indv")
                {
                    $person = strtolower($cmd->arg(2));
                    if ($person != -1)
                    {
                        if (isset($this->welcomes[$ns]['indv'][$person]))
                        {
                            unset($this->welcomes[$ns]['indv'][$person]);
                            $bot->dAmn->say("$cmd->from: <b>:dev$person:'s</b> welcome for <b>$chan</b> was deleted.", $cmd->ns);
                            $this->save($this->welcomes, "welcomes");
                        }
                        else
                        {
                            $bot->dAmn->say("$cmd->from: There is no individual welcome set by $person.", $cmd->ns);
                        }
                    }
                    else
                    {
                        if (isset($this->welcomes[$ns]['indv']['all']))
                        {
                            unset($this->welcomes[$ns]['indv']['all']);
                            $bot->dAmn->say("$cmd->from: The global individual welcome for <b>$chan</b> was deleted.", $cmd->ns);
                            $this->save($this->welcomes, "welcomes");
                        }
                        else
                        {
                            $bot->dAmn->say("$cmd->from: There is no global individual welcome for $chan.", $cmd->ns);
                        }
                    }
                }
            default:
                $bot->Event->setArgs("wt");
                $bot->Commands->execute("help");
        }
    }

    function c_welcome($cmd, $bot)
    {
        $msg = $cmd->arg(0, true);
        $from = strtolower($cmd->from);
        if ($msg != -1)
        {
            if ($this->indv)
            {
                if ($msg == 'clear')
                {
                    unset($this->welcomes[$cmd->ns]['indv'][$from]);
                    $bot->dAmn->say("$cmd->from: Your welcome has been cleared.", $cmd->ns);
                }
                else
                {
                    $this->welcomes[$cmd->ns]['indv'][$from] = $msg;
                    $bot->dAmn->say("$cmd->from: Your welcome message is now set to <i>\"$msg\"</i>.", $cmd->ns);
                }
                $this->save($this->welcomes, "welcomes");
            }
            else
            {
                $bot->dAmn->say("$cmd->from: Individual welcomes are not enabled.", $cmd->ns);
            }
        }
        if ($cmd->args == '')
        {
            $bot->Event->setArgs("welcome");
            $bot->Commands->execute("help");
        }
    }

    function do_welcome($evt, $bot)
    {
        if (isset($this->welcomes[$evt->ns]) && is_array($this->switches) && isset($this->switches[$evt->ns]))
        {
            if ($this->switches[$evt->ns] == "on")
            {
                //$bot->Console->notice("[WELCOME] Seeing if we can welcome a user...");
                echo "Seeing if we can welcome a user...\n";
                $from = $bot->dAmn->query("members", $evt->ns);
                $from = $from[$evt->from];
                if (!is_array($from)) $from = new Packet($from);
                if (isset($from['pc'])) $pc = $from['pc'];
                //echo "From: " . print_r($from, true) . "\n";
                //echo "PC: $pc\n";
                if (isset($this->welcomes[$evt->ns]['indv'][strtolower($evt->from)]) && $this->indv)
                {
                    $msg = str_replace("{from}", $evt->from, $this->welcomes[$evt->ns]['indv'][strtolower($evt->from)]);
                    if (isset($this->welcomes[$evt->ns]['indv']['all']))
                        $msg = str_replace("{from}", $evt->from, $this->welcomes[$evt->ns]['indv']['all']) .' '. $msg;
                    $bot->dAmn->say($msg, $evt->ns);
                }
                if (isset($this->welcomes[$evt->ns]['pc'][$pc]))
                {
                    $msg = str_replace("{from}", $evt->from, $this->welcomes[$evt->ns]['pc'][$pc]);
                    $bot->dAmn->say($msg, $evt->ns);
                }
                if (isset($this->welcomes[$evt->ns]['all']))
                {
                    $msg = str_replace("{from}", $evt->from, $this->welcomes[$evt->ns]['all']);
                    $bot->dAmn->say($msg, $evt->ns);
                }
            }
        }
    }
}
?>
