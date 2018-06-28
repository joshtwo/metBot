<?php
class User extends module
{
    protected $sysname = "User";
    protected $name = "User System Commands";
    protected $version = "1";
    protected $info = "This module has commands that manages the bot's user system.";

    function main()
    {
        $this->addCmd('level', 75, "Manages the privclass list.<sub><ul><li>Type <b>{trigger}level add <i>privclass</i> <i>level</i></b> to add the privclass <i>privclass</i> with the userlevel <i>level</i>.</li><li>Type <b>{trigger}level del <i>privclass</i></b> to remove the privclass <i>privclass</i> and everyone in it.</li><li>Type <b>{trigger}level ren/rename <i>oldname</i> <i>newname</i></b> to rename the privclass <i>oldname</i> to <i>newname</i>.</li></ul>");
        $this->addCmd('user', 100, "Manages the user access list.<sub><ul><li>Type <b>{trigger}user add <i>person</i> <i>level</i></b> to add <i>person</i> to the user access list with the level <i>level</i>.</li><li>Type <b>{trigger}user del/rem <i>person</i></b> to remove <i>person</i> from the user access list. This person will then have guest access to the bot.");
        $this->addCmd('users', 25, "Shows the user access list.");
        $this->addCmd('levels', 25, "Show's the privclass list.");
    }

    function c_user($cmd, $bot)
    {
        $command = $cmd->arg(0);
        $user = $cmd->arg(1);
        $level = $cmd->arg(2);
        if ($command != -1)
        {
            if ($command == "add")
            {
                if ($user != -1)
                {
                    if ($level !== -1)
                    {
                        $keys = array_keys($bot->levels);
                        if (!is_numeric($level))
                        {
                            $bot->dAmn->say("$cmd->from: You must pass a privclass number to add the user to.", $cmd->ns);
                        }
                        elseif (in_array((int) $level, $keys, true))
                        {
                            $bot->privs[$level][] = $user;
                            $bot->dAmn->say("$cmd->from: User <b>:dev$user:</b> was added to the group <b>". $bot->levels[$level] ."</b>.", $cmd->ns);
                            $bot->saveUserInfo();
                        }
                        else
                        {
                            $bot->dAmn->say("$cmd->from: There is no level $level privclass.", $cmd->ns);
                        }
                    }
                    else
                    {
                        $bot->dAmn->say("$cmd->from: You must set a user level.", $cmd->ns);
                    }
                }
                else
                {
                    $bot->dAmn->say("$cmd->from: What user are you adding?", $cmd->ns);
                }
            }
            elseif ($command == "del")
            {
                if ($user != -1)
                {
                    $found = false;
                    foreach($bot->privs as $level => $members)
                    {
                        if (in_array($user, $members, true))
                        {
                            $found = true;
                            break;
                        }
                    }

                    if ($found)
                    {
                        $key = array_search($user, $bot->privs[$level]);
                        array_splice($bot->privs[$level], $key);
                        $bot->dAmn->say("$cmd->from: <b>$user's</b> was deleted from the privilege table.", $cmd->ns);
                        $bot->saveUserInfo();
                    }

                    else
                    {
                        $bot->dAmn->say("$cmd->from: $user is not in the privilege table.", $cmd->ns);
                    }
                }
                else
                {
                    $bot->dAmn->say("$cmd->from: Who do you want to delete?", $cmd->ns);
                }
            }

        }
        else
        {
            $bot->Event->setArgs("user");
            $bot->Commands->execute("help");
        }
    }

    function c_users($cmd, $bot)
    {
        $text = '';
        $u = array();
        foreach ($bot->levels as $level => $level_name)
        {
            foreach ($bot->privs as $priv => $users)
            {
                if ($priv == $level && count($users) > 0)
                {
                    foreach($users as $user)
                        $u[] = ":dev$user:";
                }
            }
            if (count($u) > 0)
            {
                $text .= "<b>$level_name ($level)</b>:<sub><br>";
                $text .= join(", ", $u);
                $text .= "</sub><br><br>";
            }
            $u = array();
        }
        $bot->dAmn->say("<abbr title=\"$cmd->from\"></abbr><b>Users:</b><br><sub>$text</sub>", $cmd->ns);
    }

    function c_level($cmd, $bot)
    {
        $command = $cmd->arg(0);
        $level   = $cmd->arg(1);
        $priv    = $cmd->arg(2);

        if ($command != -1)
        {
            if ($command == "add")
            {
                if ($level != -1)
                {
                    if ($priv != -1)
                    {
                        $bot->levels[$priv] = $level;
                        $bot->dAmn->say("$cmd->from: Level <b>$priv</b> privclass <b>$level</b> was added.", $cmd->ns);
                        krsort($bot->levels);
                        $bot->saveUserInfo();
                    }
                    else
                    {
                        $bot->dAmn->say("$cmd->from: What level is this privclass?", $cmd->ns);
                    }
                }
                else
                {
                    $bot->dAmn->say("$cmd->from: You must specify the name of the level to add.", $cmd->ns);
                }
            }
            elseif ($command == "del")
            {
                if ($level != -1)
                {
                    if (in_array($level, $bot->levels, true))
                    {
                        $levelpriv = -10;
                        foreach ($bot->levels as $prv => $lvl)
                        {
                            if ($level == $lvl)
                            {
                                unset($bot->levels[$prv]);
                                $levelpriv = $prv;
                            }
                        }
                        $removed = 0;
                        foreach ($bot->privs as $lvl => $members)
                        {
                            if ($levelpriv == $lvl)
                            {
                                $removed = count($members);
                                unset($bot->privs[$lvl]);
                            }
                        }
                        krsort($bot->levels);
                        $bot->saveUserInfo();
                        $bot->dAmn->say("$cmd->from: Privclass <b>$level</b> was removed successfull; <b>$removed</b> people were affected.", $cmd->ns);
                    }
                    else
                    {
                        $bot->dAmn->say("$cmd->from: $level isn't a privclass.", $cmd->ns);
                    }
                }
                else
                {
                    $bot->dAmn->say("$cmd->from: What class do you want to delete?", $cmd->ns);
                }
            }
            elseif ($command == "rename" || $command == "ren")
            {
                if ($level != -1)
                {
                    if ($priv != -1)
                    {
                        if (in_array($level, $bot->levels, true))
                        {
                            foreach ($bot->levels as $prv => $lvl)
                            {
                                if ($level == $lvl)
                                    $bot->levels[$prv] = $priv;
                            }
                            krsort($bot->levels);
                            $bot->saveUserInfo();
                            $bot->dAmn->say("$cmd->from: Level <b>$level</b> was renamed to <b>$priv</b>.", $cmd->ns);
                        }
                        else
                        {
                            $bot->dAmn->say("$cmd->from: $level isn't a level.", $cmd->ns);
                        }
                    }
                    else
                    {
                        $bot->dAmn->say("$cmd->from: What are you renaming the level to?", $cmd->ns);
                    }
                }
                else
                {
                    $bot->dAmn->say("$cmd->from: What level are you renaming?", $cmd->ns);
                }
            }
        }
        else
        {
            $bot->Event->setArgs("level");
            $bot->Commands->execute("help");
        }
    }

    function c_levels($cmd, $bot)
    {
        $levels = array();
        foreach($bot->levels as $level => $name)
        {
            $levels[] = "<b>$name</b>: $level";
        }
        $bot->dAmn->say("<abbr title=\"$cmd->from\"></abbr><b>Levels:</b><br><sub>".join("<br>", $levels)."</sub>", $cmd->ns);
    }
}
?>
