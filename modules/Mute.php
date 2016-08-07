<?php
class Mute extends module
{
    protected $name = "Mute";
    protected $sysname = "Mute";
    protected $author = "deviant-garde";
    protected $version = "1";
    protected $description = "Allows you to mute people temporarily and manage a list of muted users.";

    // currently muted users and mute settings
    public $channels = array();
    // history of mutes
    public $history = array();

    function main()
    {
        $this->addCmd("mute", 50, "Mute somebody temporarily. All commands can optionally begin with the channel to operate on. Ex. <code>{trigger}mute #ThumbHub jerkoff98 2d Don't be a jerkoff</code>.<br><sub>Run {trigger}mute <i>user</i> <i>time</i> <i>reason</i> to mute <i>user</i> for <i>time</i> long with reason <i>reason</i>. The reason is optional.<br>Run {trigger}mute class to show the current privclass users are demoted to when muted.<br>Run {trigger}mute class <i>class</i> to set the muting privclass to <i>class</i>.");
        $this->addCmd('unmute', 50, "Unmutes someone currently on the mute list. Used {trigger}unmute user. Can optionally give a channel to unmute in.");
        $this->addCmd('mutelist', 50, "Shows the history of muted users.<br><sub>Use {trigger}mutelist to get the history of all users ever muted.<br>Use {trigger}mutelist <i>user</i> to get the history of a specific user.<br>Use {trigger}mutelist <i>page</i> to set the page of the list to <i>page</i>.");
        $this->hook('e_unmute', 'loop');
        $this->loadSettings();
    }

    function loadSettings()
    {
        $this->load($this->channels, 'channels');
        $this->load($this->history, 'history');
    }

    function saveSettings()
    {
        $this->save($this->channels, 'channels');
        $this->save($this->history, 'history');
    }

    // unmutes people when their time has expired
    function e_unmute($cmd, $bot)
    {
        if ($this->channels)
            foreach ($this->channels as $ns => $list)
            {
                if (isset($list['users']))
                    foreach($list['users'] as $user => $time)
                        if (time() - $time['start'] > $time['duration'])
                        {
                            $bot->dAmn->unban($user, $ns);
                            unset($this->channels[$ns]['users'][$user]);
                        }
            }
    }

    function c_mute($cmd, $bot)
    {
        $ns = $bot->dAmn->format($cmd->ns());
        $channel = $bot->dAmn->deform($ns);
        if ($cmd->arg(0) == -1)
            $bot->dAmn->say("$cmd->from: You must give arguments to the command. Try {$bot->trigger}help mute.", $cmd->ns);
        else 
        switch($cmd->arg(0))
        {
        case 'list':
            $bot->dAmn->say("$cmd->from: Pending mute list.", $cmd->ns);
            break;
        case 'class':
            //$bot->dAmn->say("$cmd->from: Pending mute class. Default shall be \"Muted\" until then.", $cmd->ns);
            if (($class = $cmd->arg(1)) == -1)
            {
                if (($class = @$this->channels[$ns]['class']))
                    $bot->dAmn->say("$cmd->from: Currently users are demoted to the class <b>$class</b> when muted.", $cmd->ns);
                else
                    $bot->dAmn->say("$cmd->from: No mute class is currently set. The module will not be able to mute in $channel until you set a room.", $cmd->ns);
            }
            else
            {
                $this->channels[$ns]['class'] = $class;
                $bot->dAmn->say("$cmd->from: Users shall be demoted to class <b>$class</b> when muted.", $cmd->ns);
                $this->saveSettings();
            }
            break;
        default:
            if (!$this->channels[$ns]['class'])
            {
                $bot->dAmn->say("$cmd->from: You cannot mute someone in this chatroom until a mute class is set.", $cmd->ns);
                return;
            }

            $user = $cmd->arg(0);
            $time = $cmd->arg(1);
            $reason = $cmd->arg(2, true);
            if ($user == -1)
                $bot->dAmn->say("$cmd->from: You must specify a user.", $cmd->ns);
            elseif ($time == -1)
            {
                $bot->dAmn->say("$cmd->from: You must pass a period of time. Explanation of format pending.", $cmd->ns);
            }
            else
            {
                if ($reason == -1)
                    $reason = null; // empty reasons are possible
                if (!isset($this->channels[$ns]['users']))
                    $this->channels[$ns]['users'] = array();
                $timeSeconds = $bot->stringToTime($time);
                if (!$timeSeconds)
                {
                    $bot->dAmn->say("$cmd->from: Time string \"$time\" is invalid. The units of time are s, h, d and w (second, hour, day and week).", $cmd->ns);
                    return;
                }
                $time = $timeSeconds;
                $this->channels[$ns]['users'][$user] = array('start' => ($ts = time()), 'duration' => $time);
                // add to the history log
                if (!isset($this->history[$user]))
                    $this->history[$user] = array();
                if (!isset($this->history[$user][$ns]))
                    $this->history[$user][$ns] = array();
                $this->history[$user][$ns][] = array('start' => $ts, 'duration' => $time, 'reason' => $reason, 'by' => $cmd->from);

                $time = $bot->uptime($time, false);
                $bot->dAmn->promote($user, $this->channels[$ns]['class'], $ns);
                $bot->dAmn->say("$cmd->from: Muting <b>:dev$user:</b> ($time) in $channel for reason <i>" . ($reason ? $reason : "none") . "</i>.", $cmd->ns);
                $this->saveSettings();
            }
        }
    }

    function c_unmute($cmd, $bot)
    {
        $ns = $bot->dAmn->format($cmd->ns());
        $channel = $bot->dAmn->deform($ns);
        if (($user = $cmd->arg(0)) == -1)
            $bot->dAmn->say("$cmd->from: You must give a user to unmute.", $cmd->ns);
        elseif (isset($this->channels[$ns]) &&
            isset($this->channels[$ns]['users']) &&
            isset($this->channels[$ns]['users'][$user]))
        {
            unset($this->channels[$ns]['users'][$user]);
            $this->saveSettings();
            $bot->dAmn->unban($user, $ns);
            $bot->dAmn->say("$cmd->from: User :dev$user: unmuted in $channel.", $cmd->ns);
        }
        else
            $bot->dAmn->say("$cmd->from: User $user is not banned in $channel.", $cmd->ns);
    }

    function c_mutelist($cmd, $bot)
    {
        $ns = $cmd->ns();
        $channel = $bot->dAmn->deform($ns);
        $command = $cmd->arg(0);
        $user = $cmd->arg(1);
        $page = 0;
        // the max number of entries for each page
        $maxEntries = 5;

        $userList = array();
        $msg = array();

        if (!$this->history)
        {
            $bot->dAmn->say("$cmd->from: There is no mute history.", $cmd->ns);
            return;
        }

        if ($command == 'user' && $user != -1)
        {
            if (!(
                isset($this->history) &&
                isset($this->history) &&
                isset($this->history[$user])
            ))
            {
                $bot->dAmn->say("$cmd->from: The user $user hasn't been muted.", $cmd->ns);
                return;
            }
            else
            {
                $userList = array($user);
            }
        }
        else
        {
            $keys = array_reverse(array_keys($this->history));
            if (is_numeric($command) && $command > 0)
                $page = ((int) $command) - 1;
            $userList = array();
            for ($i = 0; $i < $maxEntries && $i + $page*$maxEntries < count($keys); ++$i)
                $userList[] = $keys[$i + $page*$maxEntries];
        }
        
        if (!$userList)
        {
            if ($page != 0)
                $bot->dAmn->say("$cmd->from: There are no entries on page " . ($page+1) . ".", $cmd->ns);
            else
                $bot->dAmn->say("$cmd->from: There is no mute history.", $cmd->ns);
            return;
        }


        foreach ($userList as $user)
        {
            $info = $this->history[$user];
            $msg[] = "<b>Info for :dev$user:</b><sub>";
            $msg[] = "Number of mutes: " . count($info);
            foreach($info as $ns => $mutes)
            {
                $msg[] = "Mutes in <b>" . $bot->dAmn->deform($ns) . "</b>:";
                for($i = count($mutes); $i > 0; --$i)
                {
                    $msg[] = "- Muted by :dev" . $mutes[$i-1]['by'] . ":";
                    $msg[] = "- Muted on: " . date('M, d Y - h:i:s A', $mutes[$i-1]['start']);
                    $msg[] = "- Duration: " . $bot->uptime($mutes[$i-1]['duration'], false);
                    $msg[] = "- Reason: " . ($mutes[$i-1]['reason'] ? $mutes[$i-1]['reason'] : '<i>none</i>');
                }
            }
            $msg[] = "</sub>";
        }

        $pages = array();
        for ($i = 0; $i < count($keys); $i += $maxEntries)
        {
            $p = (floor(($i+1)/$maxEntries));
            if ($p == $page)
                $pages[$p] = "<b>".($p+1)."</b>";
            else
                $pages[$p] = $p+1;
        }

        if ($command != 'user')
            $msg[] = '<sub>Page: ' . join(" | ", $pages) . '</sub>';

        $msg = join("\n", $msg);
        $bot->dAmn->say('<abbr title="' . $cmd->from . '"></abbr>' . $msg, $cmd->ns);
    }
}
