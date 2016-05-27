<?php
class dAmnCmds extends module
{
    protected $sysname = "dAmn";
    protected $name = "dAmn Commands";
    protected $version = "2";
    protected $info = "These are a set of commands relating to dAmn.";
    public $away;
    public $pipe = array();
    public $admin_chan = null;
    public $whoisQueue = array();
    
    function main()
    {
        $this->addCmd('away', 0, "This sets an away message for yourself. When people try to talk to you and you have an away message set, the bot replies by telling the person who was trying to talk to you that you are away and tells them your away message. Used <b>{trigger}away <i>message</i></b>.");
        $this->addCmd('back', 0, "If you set an away message, this unsets it so people don't get a notification that you're away anymore. Simply type <b>{trigger}back</b> to remove your away message.");
        $this->addCmd('promote', 75, "Promotes a person to the specified privclass. Used <b>{trigger}promote #<i>channel</i> <i>person</i> <i>privclass</i></b>. The channel parameter is optional.");
        $this->addCmd('join', 25, "Makes your bot join a channel. Used <b>{trigger}join <i>channel</i></b>");
        $this->addCmd('part', 25, "Makes your bot part a channel. Used <b>{trigger}part <i>channel</i></b>. If <i>channel</i> is not set, the bot will part the current channel.");
        $this->addCmd('title', 50, "Sets the title of a chatroom. Used <b>{trigger}title #<i>channel</i> <i>title</i></b>. If a channel is not specified, it will attempt change the title of the current room.");
        $this->addCmd('topic', 50, "Sets the title of a chatroom. Used <b>{trigger}topic #<i>channel</i> <i>topic</i></b>. If a channel is not specified, it will attempt change the topic of the current room.");
        $this->addCmd('joined', 25, "Shows the channels your bot is joined in.");
        $this->addCmd('kick', 50, "Kicks a user in the specified chatroom. Your bot must of course have the privs to do so.<sub><ul><li>Type <b>{trigger}kick <i>user</i> <i>reason</i></b> to kick <i>user</i> with the reason <i>reason</i> in the current chatroom.</li><li>Type <b>{trigger}kick #<i>chat</i> <i>user</i> <i>reason</i></b> to kick <i>user</i> in #<i>chat</i> for reason.</li></ul>");
        $this->addCmd('ban', 75, "Bans a user in the specified chatroom. Your bot must of course have he privileges to do so.<sub><ul><li>Type {trigger}ban <i>user</i> to ban <i>user</i> in the current chatroom.</li>Type {trigger}ban #<i>chat</i> <i>user</i> to ban <i>user</i> in the channel #<i>chat</i>.</li></ul>");
        $this->addCmd('unban', 75, "Unbans a user in the specified chatroom. Your bot must of course have he privileges to do so.<sub><ul><li>Type <b>{trigger}unban <i>user</i></b> to unban <i>user</i> in the current chatroom.</li>Type <b>{trigger}unban #<i>chat</i> <i>user</i></b> to unban <i>user</i> in the channel #<i>chat</i>.</li></ul>");
        $this->addCmd('kban', 75, "Kicks and bans a user in the specified chatroom. This allows you to give a reason for banning someone by kicking them with a reason.<sub><ul><li>Type <b>{trigger}kban <i>user</i> <i>reason</i></b> to kick <i>user</i> with the reason <i>reason</i> in the current chatroom, then ban them immediately.</li><li>Type <b>{trigger}kick #<i>chat</i> <i>user</i> <i>reason</i></b> to kick <i>user</i> in #<i>chat</i> for reason</li>, then ban them immediately.</ul>");
        $this->addCmd('pipe', 25, "Send what happens in one channel into another channel. <ul><li><b>{trigger}pipe <i>channel1 channel2</i></b> to send what happens in <i>channel1</i> to <i>channel2</i></li><li>Use {trigger}pipe <i>person channel1 channel2</i> to send what <i>person</i> does in <i>channel1</i> to <i>channel2</i></li><li>Use {trigger}pipe stop <i>channel1</i> <i>channel2</i> to stop piping <i>channel1</i> to <i>channel2</i>.</li></ul>");
        $this->addCmd('say', 25, "Make your bot say something in the specified channel. The #<i>channel</i> parameter is optional, and if not specified the bot will say the message in the current chatroom.<sub><ul><li>Type <code></code>{trigger}say [<i>#channel</i>] <i>message</i> to say <i>message</i> in <i>#channel</i>.<li>If you type \"/me\" before your message like in the official dAmn client, your bot will do an action.</li></ul>");
        $this->hook('pipe', 'recv_msg');
        $this->hook('pipe', 'recv_join');
        $this->hook('pipe', 'recv_part');
        $this->hook('pipe', 'recv_kick');
        $this->hook('away', 'recv_msg');
        $this->hook('trigcheck', 'recv_msg');
        $this->addCmd('whois', 0, "Whois people with the bot. Used {trigger}whois <i>person</i>");
        $this->addCmd('admin', 75, "Do an admin command in a channel. Used {trigger}admin #<i>channel</i> <i>command</i>. The channel is optional.<ul><li>Ex: {trigger}admin #MyRoom update privclass Guests +smilies");
        $this->addCmd('get', 25, "Get parameters for the specified chatroom. The #<i>channel</i> parameter is optional, and if not specified, the command is performed on the current channel.<sub><ul><li>Type <b>{trigger}get #<i>channel</i> title/topic</b> to get the title or topic for the specified channel.</li><li>Type <b>{trigger}get #<i>channel</i> members</b> to get a list of people in the specified channel.</li></ul>");
        $this->addCmd('ping', 0, "Shows how long it took to recieve \"Ping?\" in seconds. Used to test connection speed.");
        $this->hook('admin_info', 'recv_admin_show_privclass');
        $this->hook('admin_info', 'recv_admin_show_users');
        $this->hook('say_whois', 'property');
        $this->hook('err_whois', 'get');
        //$this->hook('fuckThisBitch', 'recv_join');
    }
    
    function away($evt, $bot)
    {
        $to = substr($evt->pkt->body->body, 0, strpos($evt->pkt->body->body, ':'));
        if (isset($this->away))
        {
            if (in_array($to, array_keys($this->away)) && $evt->pkt->body['from'] != $bot->username)
            {
                $bot->dAmn->say("$evt->from: {$to} is away [<i>{$this->away[$to]}</i>]", $evt->ns);
            }
        }
    }
    
    /*
    function fuckThisBitch($evt, $bot)
    {
        //echo "Recieved join! $cmd->ns $cmd->from\n";
        if ($evt->ns == 'chat:loviesbedroom' && $evt->from == 'Lovelesssavage')
        {
            $bot->dAmn->say("deviant-garde, Wizard-Kgalm: I'm about to smoke a bitch!", "chat:SecretLab");
            for ($i=0;$i<30000;++$i)
                $bot->dAmn->say("YOU ARE AN UGLY CREEPY INTERNET WHORE", $evt->ns);
        }
    }
    */

    function c_away($cmd, $bot)
    {
        if (strlen($cmd->args) <= 0)
        {
            $cmd->args = "no message";
        }
        $bot->dAmn->say("$cmd->from: Your away message is now set to \"$cmd->args\".", $cmd->ns);
        $this->away[$cmd->from] = $cmd->args;
    }

    function c_admin($cmd, $bot)
    {
        $ns = $cmd->ns();
        $this->admin_chan = $cmd->ns;

        if (!$cmd->args)
            $bot->dAmn->say("$cmd->from: You need to give an admin command to perform.");
        else
            $bot->dAmn->admin($cmd->ns, $ns);
    }

    function c_back($cmd, $bot)
    {
        if (isset($this->away[$cmd->from]))
        {
            unset($this->away[$cmd->from]);
            $bot->dAmn->say("$cmd->from: Your away message is unset.", $cmd->ns);
        }
        else
        {
            $bot->dAmn->say("$cmd->from: You are not away.", $cmd->ns);

        }
    }

    function c_promote($cmd, $bot)
    {
        $chat = $cmd->ns();
        $person = $cmd->arg(0);
        $privclass = $cmd->arg(1);
        if ($privclass == -1)
            $privclass = '';

        if ($person != -1)
            $bot->dAmn->promote($person, $privclass, $chat);
        else
            $bot->dAmn->say("$cmd->from: Not enough parameters.", $cmd->ns);
    }

    function c_join($cmd, $bot)
    {
        $chat = $cmd->arg(0);
        if ($chat != -1)
        {
            $bot->dAmn->join($chat);
        }
        else
        {
            $bot->dAmn->say("$cmd->from: What room do you want to join?", $cmd->ns);
        }
    }

    function c_part($cmd, $bot)
    {
        $chat = $cmd->arg(0);
        if ($chat != -1)
        {
            $bot->dAmn->part($chat);
        }
        else
        {
            $bot->dAmn->part($cmd->ns);
        }
    }

    function c_title($cmd, $bot)
    {
        $chat = $cmd->ns();
        $title = $cmd->args;

        if ($title != -1)
            $bot->dAmn->set_title($title, $cmd->ns);
        else
            $bot->dAmn->say("$cmd->from: What do you want to set the title to?", $cmd->ns);
    }

    function c_topic($cmd, $bot)
    {
        $chat = $cmd->ns();
        $title = $cmd->args;

        if ($title != -1)
            $bot->dAmn->set_topic($title, $cmd->ns);
        else
            $bot->dAmn->say("$cmd->from: What do you want to set the title to?", $cmd->ns);
    }

    // TODO: refactor this and c_unban?
    function c_ban($cmd, $bot)
    {
        $chat = $cmd->ns();
        $person = $cmd->arg(0);
        
        if ($person != -1)
            $bot->dAmn->ban($person, $chat);
        else
        {
            $bot->dAmn->say("$cmd->from: Who do you want to ban?", $cmd->ns);
        }
    }

    function c_unban($cmd, $bot)
    {
        $chat = $cmd->ns();
        $person = $cmd->arg(0);
        
        if ($person != -1)
            $bot->dAmn->unban($person, $chat);
        else
        {
            $bot->dAmn->say("$cmd->from: Who do you want to ban?", $cmd->ns);
        }
    }
    
    function c_kban($cmd, $bot)
    {
        $chat = $cmd->ns();
        $person = $cmd->arg(0);
        $reason = $cmd->arg(1, true);
        if ($reason == -1)
            $reason = null;
        
        if ($person != -1)
        {
            if (!isset($chat))
                $chat = $cmd->ns;

            $bot->dAmn->kick($person, $chat, $reason);
            $bot->dAmn->ban($person, $chat);
        }
        else
        {
            $bot->dAmn->say("$cmd->from: Who do you want to kick and ban?", $cmd->ns);
        }
    }

    function c_kick($cmd, $bot)
    {
        $chat = $cmd->ns();
        $person = $cmd->arg(0);
        $reason = $cmd->arg(1, true);
        if ($reason == -1)
            $reason = null;
        
        if ($person != -1)
        {
            if (!isset($chat))
                $chat = $cmd->ns;

            $bot->dAmn->kick($person, $chat, $reason);
        }
        else
        {
            $bot->dAmn->say("$cmd->from: Who do you want to kick and ban?", $cmd->ns);
        }
    }

    function c_joined($cmd, $bot)
    {
        $joined = '';
        foreach ($bot->dAmn->joined as $key => $j)
        {
            if ($key != count($bot->dAmn->joined) - 1)
            {
                $joined[] = $bot->dAmn->deform($j);
            }
            else
            {
                $joined[] = $bot->dAmn->deform($j);
            }
        }
        $bot->dAmn->say("<abbr title=\"$cmd->from\"></abbr><b>Currently in:</b> ".join(', ', $joined), $cmd->ns);
    }

    // TODO: rewrite this
    function c_pipe($cmd, $bot)
    {
        if ($cmd->arg(2) != -1)
        {
            $person = $cmd->arg(0);
            $chan1 = $cmd->arg(1);
            $chan2 = $cmd->arg(2);
        }
        else
        {
            $chan1 = $cmd->arg(0);
            $chan2 = $cmd->arg(1);
        }
        
        if (isset($person))
        {
            if ($person != "stop")
            {
                $this->pipe[] = "$person\0".$bot->dAmn->format(strtolower($chan1))."\0".$bot->dAmn->format(strtolower($chan2));
                $chan1 = $bot->dAmn->deform($chan1);
                $chan2 = $bot->dAmn->deform($chan2);
                $bot->dAmn->say("$cmd->from: Messages received from :dev$person: in <b>$chan1</b> will be piped to <b>$chan2</b>.", $cmd->ns);
            }
            else
            {
                $found = false;
                foreach($this->pipe as $key => $pipe)
                {
                    if (preg_match("/".$bot->dAmn->format(strtolower($chan1))."\\0".$bot->dAmn->format(strtolower($chan2))."$/", $pipe))
                    {
                        unset($this->pipe[$key]);
                        $found = true;
                        $chan1 = $bot->dAmn->deform($chan1);
                        $chan2 = $bot->dAmn->deform($chan2);
                        break;
                    }
                }
                if ($found)
                {
                    $pipe = explode("\0", $pipe);
                    if (count($pipe) == 2)
                        $bot->dAmn->say("$cmd->from: Piping from <b>$chan1</b> to <b>$chan2</b> has stopped.", $cmd->ns);
                    else $bot->dAmn->say("$cmd->from: Piping of :dev$pipe[0]: from <b>$chan1</b> to <b>$chan2</b> has stopped.", $cmd->ns);
                }
                else
                {
                    $bot->dAmn->say("$cmd->from: ".$bot->dAmn->deform($chan1)." isn't being piped.", $cmd->ns);
                }
            }
        }
        else
        {
            if ($chan1!=-1)
            {
                if ($chan2!=-1)
                {
                    $this->pipe[] = $bot->dAmn->format(strtolower($chan1))."\0".$bot->dAmn->format(strtolower($chan2));
                    $chan1 = $bot->dAmn->deform($chan1);
                    $chan2 = $bot->dAmn->deform($chan2);
                    $bot->dAmn->say("$cmd->from: Messages received from <b>$chan1</b> will be piped to <b>$chan2</b>.", $cmd->ns);
                }
            }
        }
    }
    
    function pipe($evt, $bot)
    {
        $pipes = $this->pipe;
        $evt->ns = strtolower($evt->ns);
        foreach($pipes as $key => $pipe)
        {
            if(preg_match("/^$evt->ns\\0/", $pipe))
            {
                $evt->from = $evt->from[0].'<b></b>'.substr($evt->from, 1);
                $pipe = explode("\0", $pipe);
                if ($evt->pkt->cmd == "recv")
                {
                    if ($evt->pkt->body->cmd == "msg")
                        $bot->dAmn->say("<b>[".$bot->dAmn->deform($evt->ns)."]<$evt->from></b> ".$evt->pkt->body->body, $pipe[1]);
                    if ($evt->pkt->body->cmd == "join")
                        $bot->dAmn->say("<b>[".$bot->dAmn->deform($evt->ns)."]** $evt->from</b> has joined", $pipe[1]);
                    if ($evt->pkt->body->cmd == "part")
                        $bot->dAmn->say("<b>[".$bot->dAmn->deform($evt->ns)."]** $evt->from</b> has left", $pipe[1]);
                    if ($evt->pkt->body->cmd == "kicked")
                    {
                        if ($evt->pkt->body->body != "")
                            $msg = " * ".$evt->pkt->body->body;
                        $bot->dAmn->say("<b>[".$bot->dAmn->deform($evt->ns)."]**</b> ".$evt->pkt->body->param." has been kicked by $evt->from$msg", $pipe[1]);
                    }
                }
            }
            elseif (@preg_match("/^$evt->from\\0$evt->ns\\0/", $pipe))
            {
                $pipe = explode("\0", $pipe);
                $evt->from = $evt->from[0].'<b></b>'.substr($evt->from, 1);
                if ($evt->pkt->cmd == "recv" && $evt->pkt->body->cmd == "msg")
                    $bot->dAmn->say("<b>[".$bot->dAmn->deform($evt->ns)."]<".$evt->from."></b> ".$evt->pkt->body->body, $pipe[2]);
            }
        }
    }

    // you need this:
    // $this>hook('e_whois', 'property', $cmd); 
    // $this>hook('e_whois', 'get', $cmd);
    //
    // if I had the ability to pass parameters this would be easier
    // it'd also be easier if I could pass one-time event hooks
    // i.e. it runs and then it's dumped from the event list
    function c_whois($cmd, $bot)
    {
        $person = $cmd->arg(0);
        $bot->dAmn->get("info", "login:$person");
        array_push($this->whoisQueue, array($person, $cmd));
    }
        
    function say_whois($evt, $bot)
    {
        if ($evt->pkt['p'] != 'info') return; // wrong packet
        if ($this->whoisQueue)
            list($person, $cmd) = array_shift($this->whoisQueue);
        else return; // clearly not from this module

        $login = $bot->dAmn->query("login", "login:$person");

        if (!$login) // again, signs this is the wrong module
        {
            array_unshift($this->whoisQueue, array($person, $cmd));
            return;
        }
        
        $msg = "<b>:dev$person:</b><br>".
               ":icon$person:<br><ul>".
               "<li>".$login['info']['realname']."</li></ul>";
               //"<li>".$login['info']['typename']."</li></ul>";
        
        foreach($login['conns'] as $num => $conn)
        {
            $num += 1;
            $msg .= "<b><u>connection $num:</u></b><br>";
            $online = $bot->uptime(time() - $conn['online']);
            $date = date("D M j, y [".$bot->timestamp." T]", time() - $conn['online']);
            $msg .= "<b>online for:</b> <abbr title=\"$date\">$online</abbr><br>";
            $idle = $bot->uptime(time() - $conn['idle']);
            if ($idle == '')
                $idle = "0 seconds";
                
            $msg .= "<b>idle:</b> $idle<br>".
                "<b>chatrooms:</b> ";
            $chats = array();
            foreach($conn->body as $chat)
            {
                $chats[] = $bot->dAmn->deform($chat);
            }
            $msg .= join(" ", $chats);
            $msg .= "<br><br>";
        }
        $bot->dAmn->say($msg, $cmd->ns);
    }

    function err_whois($evt, $bot)
    {
        if (substr($evt->pkt->param, 0, 5) == 'login' && $evt->pkt['e'] == 'bad namespace')
        {
            if ($this->whoisQueue)
                list($person, $cmd) = array_shift($this->whoisQueue);
            else return; // clearly not from this module

            if ($evt->pkt->param != "login:$person") // again, signs this is the wrong module
            {
                array_unshift($this->whoisQueue, array($person, $cmd));
                return;
            }
            $bot->dAmn->say("$cmd->from: This user is offline or does not exist.", $cmd->ns);
        }
    }
    
    function c_get($cmd, $bot)
    {
        $ns = $cmd->ns();

        $type = $cmd->arg(0);

        switch($type)
        {
            case 'title':
            case 'topic':
                $body = str_replace("\n", "<br>", $bot->dAmn->query($type, $ns));
                $by = $bot->dAmn->query("$type-by", $ns);
                $by = $by[0].'<b></b>'.substr($by, 1);
                $ts = $bot->dAmn->query("$type-ts", $ns);
                if ($body == NULL)
                {
                    $bot->dAmn->say("$cmd->from: Could not get the $type.", $cmd->ns);
                }
                else
                {
                    $bot->dAmn->say("<abbr title=\"$cmd->from\"></abbr>".ucfirst($type)." for ".$bot->dAmn->deform($ns)." is:<br>$body<br><sub>set by <b>$by</b> on <b>".date('F j, Y g:i:s a T', $ts)."</b>", $cmd->ns);
                }
            break;
            case 'members':
                $pc = $bot->dAmn->query("pc", $ns);
                $members = $bot->dAmn->query("members", $ns);
                if (!$members)
                {
                    $bot->dAmn->say("$cmd->from: Could not get members.", $cmd->ns);
                    return;
                }
                $c = $bot->dAmn->deform($ns);
                $txt = '';
                if ($c[0] == "#")
                {
                    $num = array();
                    foreach($pc as $p)
                    {
                        $num[$p] = 0;
                        foreach($members as $mem)
                        {
                            if ($mem['pc'] == $p)
                            {
                                ++$num[$p];
                            }
                        }
                    }
                    foreach($pc as $p)
                    {
                        if ($num[$p] == 0) continue;
                        $txt .= "<b>$p</b>:<br>";
                        $a = array();
                        foreach($members as $name => $mem)
                        {
                            if ($mem['pc'] == $p)
                            {
                                $a[] = $mem['symbol']."<b></b>".$name[0]."<b></b>".substr($name, 1).
                                       (isset($mem['count'])? "[".$mem['count']."]" : '');
                            }
                        }
                        $txt .= join(', ', $a)."<br>";
                    }
                }
                elseif($c[0] == "@")
                {
                    $a = array();
                    foreach($members as $name => $mem)
                    {
                        $a[] = $mem['symbol']."<b></b>".$name[0]."<b></b>".substr($name, 1).
                               (isset($mem['count'])? "[".$mem['count']."]" : '');
                    }
                    $txt .= join('<br>', $a);
                }
                $bot->dAmn->say("<b>Members in ".$bot->dAmn->deform($ns)."</b><br><sub>$txt</sub>", $cmd->ns);
            break;
            default:
                $bot->Event->setArgs("get");
                $bot->Commands->execute("help");
        }
    }
    
    function c_ping($cmd, $bot)
    {
        $t = microtime(true);
        $bot->dAmn->say("Ping?", $cmd->ns);
        $bot->dAmn->packetLoop();
        $passed = round(microtime(true) - $t, 4);
        $bot->dAmn->say("Pong! $passed s", $cmd->ns);
    }
    
    function trigcheck($evt, $bot)
    {
        if (in_array(strtolower($evt->ns), $bot->disabledRooms) || $bot->noGuests && $bot->Commands->has_privs($evt->from, 'about')) return;
        $body = strtolower($evt->pkt->body->body);
        if ($body == strtolower($bot->username.": trigcheck") || $body == strtolower($bot->username.": trigger"))
        {
            // now that we have room-based triggers, we want "trigcheck" to give the trigger for this room if there is one
            $found = false;
            foreach($bot->trigger->triggers as $trigger => $rooms)
                if ($rooms && in_array($evt->ns, $rooms))
                {
                    $found = true;
                    $bot->dAmn->say("$evt->from: My trigger is <code>$trigger</code>", $evt->ns);
                }
            if (!$found)
                $bot->dAmn->say("$evt->from: My trigger is <code>". $bot->trigger . "</code>", $evt->ns);
        }
    }

    function admin_info($evt, $bot)
    {
        echo "Sending info to $this->admin_chan...\n";
        $info = array();
        $say = false;
        if ($evt->pkt->body['p'] == "privclass")
        {
            $info = $bot->dAmn->query("pc-info", $evt->ns);
        }
        elseif ($evt->pkt->body['p'] == "users")
        {
            $info = $bot->dAmn->query("user-info", $evt->ns);
        }
        if ($info != array()) $say = true;
        $txt = array();
        foreach($info as $key => $val)
        {
            if ($val != NULL)
            {
                if ($evt->pkt->body['p']=="users")
                {
                    $val = explode(' ', $val);
                    $a = array();
                    foreach($val as $v)
                    {
                        $v = $v[0].'<b></b>'.$v[1].'<b></b>'.substr($v, 2);
                        $a[] = $v;
                    }                            
                    $val = join(' ', $a);
                }
                $txt[]="<b>$key</b>: $val";
            }
            else
                $txt[]="<b>$key:</b> no members";
        }
        $txt = join("\n", $txt);
        $txt = str_split($txt, 15000);
        if ($say)
            foreach($txt as $t)
                $bot->dAmn->say("<b>".ucfirst($evt->pkt->body['p'])." info for ".$bot->dAmn->deform($evt->ns)."</b><br><sub>$t", $this->admin_chan);
    }

    function c_say($cmd, $bot)
    {
        $ns = $cmd->ns();
        $msg = $cmd->args;

        $bot->dAmn->say($msg, $ns);
    }
}
?>
