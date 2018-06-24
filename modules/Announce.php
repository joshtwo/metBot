<?php
class Announce extends module
{
    protected $sysname = "Announce";
    protected $name = "Announce";
    protected $version = "1";
    protected $info = "Post announcements at a regular interval";

    public $announcements = array();

    function main()
    {
        $helpText = [
            // TODO: List announcements for only one room, not all the fucking rooms maybe?
            'list' => 'to list all announcements on the bot',
            'add <i>interval</i> <i>message</i>' => 'to add the announcement <i>interval</i> with message <i>message</i>',
            'del <i>announcement</i>' => 'to delete announcement number <i>announcement</i>',
            'set <i>announcement</i> interval/msg <i>value</i>' => 'to set the interval or message of announcement number <i>annonucement</i> to <i>value</i>'
        ];
        $helpStr = "Set up announcements to be posted at a regular interval. The #<i>channel</i> parameter is optional, defaulting to the current channel. Intervals are specified as a number with a unit of time.";
        $helpStr .= " Units are s for seconds, m for minutes, d for days, and w for weeks. Ex. <code>10m, 60s, \"1h 30m\", 0.5w</code>.<br><sub><ul>";
        foreach ($helpText as $sub => $description)
        {
            $helpStr .= "<li>Use <b>{trigger}announce #<i>channel</i> {$sub}</b> {$description}.</li>";
        }
        $helpStr .= "</ul></sub>";
        $this->addCmd("announce", 75, $helpStr);
        $this->hook('e_announce', 'loop');
        $this->loadAnnouncements();
    }

    function loadAnnouncements()
    {
        $this->load($this->announcements, 'announce');

        // this way the room won't fucking spam every announcement on startup
        // but instead will wait until a full first interval has passed
        if (!$this->announcements) return;
        $rooms = array_keys($this->announcements);
        foreach($rooms as $r)
        {
            $ids = array_keys($this->announcements[$r]);
            foreach ($ids as $id)
                $this->announcements[$r][$id]['time'] = 0;
        }
    }

    function saveAnnouncements()
    {
        $this->save($this->announcements, 'announce');
    }

    function c_announce($cmd, $bot)
    {
        $target = $bot->dAmn->format($cmd->ns());
        $chat = $bot->dAmn->deform($target);

        if (!isset($this->announcements[$target]))
            $this->announcements[$target] = array();

        switch ($cmd->arg(0))
        {
        case 'add':
            if (($interval = $cmd->arg(1)) != -1 && ($msg = $cmd->arg(2, true)) != -1)
            {
                $interval = $bot->stringToTime($interval);
                if (!$interval)
                    $bot->dAmn->say("{$cmd->from}: You must set a valid interval to repeat the announcement at.", $cmd->ns);
                else
                {
                    $this->announcements[$target][] = array(
                        'interval' => $interval,
                        'msg' => $msg,
                        'time' => time()
                    );
                    $this->saveAnnouncements();
                    $interval = $bot->uptime($interval, false);
                    $bot->dAmn->say("{$cmd->from}: The announcement <i>\"$msg\"</i> will be posted every <b>$interval</b> in $chat.", $cmd->ns);
                }
            }
            else
                $bot->dAmn->say("{$cmd->from}: You must set ". ($interval == -1 ? "an interval" : "a message") . " for this announcement.", $cmd->ns);
            break;
        case 'set':
            if (($id = $cmd->arg(1)) != -1)
            {
                if (!isset($this->announcements[$target][$id]))
                    $bot->dAmn->say("{$cmd->from}: There is no announcement #$id for $chat.", $cmd->ns);
                elseif (($attr = $cmd->arg(2)) != -1)
                    if (!in_array($attr, array('interval', 'msg', 'pc')))
                        $bot->dAmn->say("{$cmd->from}: You must pick a valid attribute to set (interval/msg/pc).", $cmd->ns);
                    elseif (($val = $cmd->arg(3, true)) == -1)
                        $bot->dAmn->say("{$cmd->from}: You didn't provide a value for \"$attr\".", $cmd->ns);
                    else
                    {
                        if ($attr == 'interval')
                        {
                            $val = $bot->stringToTime($val);
                            if (!$val)
                            {
                                $bot->dAmn->say("{$cmd->from}: You must set a valid interval to repeat the announcement at.", $cmd->ns);
                                // I need to fix the flow of this damn code
                                return;
                            }
                        }
                        $this->announcements[$target][$id][$attr] = $val;
                        $this->saveAnnouncements();
                        if ($attr == 'msg')
                        {
                            $attr = 'message';
                            $val = "<i>\"$val\"</i>";
                        }
                        elseif ($attr == 'interval')
                            $val = "<b>".$bot->uptime($val, false)."</b>";
                        else
                        {
                            $attr = 'privclass';
                            $val = "<b>$val</b>";
                        }
                        $bot->dAmn->say("{$cmd->from}: The <b>$attr</b> for announcement <b>#$id</b> in $chat has been set to $val.", $cmd->ns);
                    }
            }
            break;
        case 'del':
            if (($id = $cmd->arg(1)) != -1)
            {
                if (!isset($this->announcements[$target][$id]))
                    $bot->dAmn->say("{$cmd->from}: There is no announcement #$id for $chat.", $cmd->ns);
                else
                {
                    unset($this->announcements[$target][$id]);
                    $this->saveAnnouncements();
                    $bot->dAmn->say("{$cmd->from}: Announcement <b>#$id</b> for $chat has been deleted.", $cmd->ns);
                }
            }
            else
                $bot->dAmn->say("{$cmd->from}: You most provide an ID of an announcement to delete.", $cmd->ns);
            break;
        case 'list':
            $msg = array();
            $i = 0;
            foreach($this->announcements as $ns => $list)
            {
                $ns = $bot->dAmn->deform($ns);
                if (!$list)
                    continue;
                $msg[$i] = array();
                foreach($list as $id => $announcement)
                {
                    $msg[$i][] = "<b>#$id</b>)\n<i>\"{$announcement['msg']}\"</i><br>posted every ". $bot->uptime($announcement['interval'], false);
                }
                $msg[$i] = "<b><u>$ns</u></b><br><sub>" . join("\n\n", $msg[$i]) . "</sub>";
            }
            if ($msg)
                $bot->dAmn->say("{$cmd->from}: The current configured announcements are:\n\n" . join("\n\n", $msg), $cmd->ns);
            else
                $bot->dAmn->say("{$cmd->from}: There are no announcements.", $cmd->ns);
            break;
        default:
            $bot->dAmn->say("{$cmd->from}: The possible subcommands are add/del/set/list.", $cmd->ns);
        }
    }

    function e_announce($cmd, $bot)
    {
        if ($this->announcements)
            foreach ($this->announcements as $ns => $list)
            {
                foreach($list as $id => &$announcement)
                    if ($announcement['time'] === 0)
                    {
                        $this->announcements[$ns][$id]['time'] = time();
                    }
                    elseif (time() - $announcement['time'] > $announcement['interval'])
                    {
                        $devs = array();
                        $bot->dAmn->say(($devs ? "<abbr title=\"$devs\"></abbr>" : "") . ":noes::megaphone: {$announcement['msg']}", $ns);
                        $this->announcements[$ns][$id]['time'] = time();
                    }
            }
    }
}
