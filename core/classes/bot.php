<?php
class bot
{
    public $name = "metBot";
    public $version = "0.7 (repo)";
    public $disconnected = false;
    public $timestamp;
    public $quit = false;
    public $override = array();
    public $attach = true;
    public $input = null;
    public $start_input = true;
    public $configFile = 'login';
    public $savePk = false;
    public $disabledRooms = array();
    public $noGuests = false;
    public $levels;
    // cmd-line options to let us know which NOT to save
    // currently does nothing
    public $options;
    public $getOAuth;

    function __construct()
    {
        $this->dAmn = new dAmn($this);
        $this->Commands = new Commands($this);
        $this->Modules = new Modules($this);
        $this->Event = new Event($this);
        $this->Console = new Console($this);
        $this->OAuth = new OAuth($this);
    }

    // TOOD: better error checking here
    function readConfig($configFile=null)
    {
        if ($configFile == null) $configFile = $this->configFile;
        $login = @file_get_contents("./data/config/$configFile.ini");
        if (!$login)
            die("The config file \"$configFile\" does not exist.\n");
        eval($login);
        eval($user = @file_get_contents('./data/config/users.ini'));
        $this->username = $username;
        $this->password = $password;
        $this->admin = $admin;
        $this->trigger = new Trigger($trigger, $primaryTrigger);
        $this->start_input = $input;
        $this->join = $join;
        if ($user)
        {
            $this->levels = $levels;
            $this->privs = $privs;
        }
        
        if (!isset($this->privs[100]) || !in_array($this->admin, $this->privs[100]))
            $this->privs[100][] = $this->admin;
        
        if ($this->levels == null)
        {
            $this->levels = array(
                100 => "Owner",
                75  => "Administrator",
                50  => "Operator",
                25  => "Member",
                0   => "Guest",
                -1  => "Banned",
            );
        }
        
        $this->log = $log;
        $this->timestamp = $timestamp;
        $this->startup_time = time();
        $this->cookie = $cookie;
        $this->warnings = $warnings;
        $this->pk = $token;
        $this->defineColors($colors);
        $this->colors = $colors;
        $this->disabledRooms = $disabledRooms ? $disabledRooms : array();
        $this->noGuests = $noGuests ? $noGuests : array();
        $this->OAuth->accessToken = $oauth['access_token'];
        $this->OAuth->refreshToken = $oauth['refresh_token'];
    }

    function saveConfig($file=null)
    {
        if (!$file)
            $file = $this->configFile;
        //$this->Console->msg("Saving config \"$file\"...");
        // I shouldn't use this because if I configure a new user on startup
        // then attempt to use the console msg functions, it'll complain that
        // none of the color constants are defined. Better to just echo.
        echo "Saving config \"$file\"...\n";
        // TODO: Seriously, rewrite this ASAP
        global $dir;
        //echo "Current directory: $dir\nCurrent pk: {$this->pk}\n";
        $contents = 
        "\$username = '".$this->username."';\n\$password = '".$this->password."';\n\$admin = '".$this->admin."';\n\$trigger = ".var_export($this->trigger->triggers, true).";\n\$primaryTrigger = \"". $this->trigger->primaryTrigger ."\";\n\$input = ".($this->start_input == true? "true" : "false").";\n\$log = ".($this->log==true?"true":"false").";\n\$timestamp = '".$this->timestamp."';\n\$token = '".$this->pk."';\n\$cookie = ".var_export($this->cookie, true).";\n\$colors = ".($this->colors == true ? "true" : "false") .";\n\$warnings = ".($this->warnings == true ? "true" : "false").";";
        $autojoin = "\n\$join = array(";
        foreach($this->join as $j)
        {
            $autojoin .= "\"$j\",\n";
        }
        $autojoin .= ");\n";
        $contents .= $autojoin;
        $contents .= '$disabledRooms = '.($this->disabledRooms == null ? 'array()' : var_export($this->disabledRooms, true)) .';';
        $contents .= "\n\$noGuests = " . ($this->noGuests ? "true" : "false") .';';
        $contents .= "\n\$oauth = " . var_export(array('access_token' => $this->OAuth->accessToken, 'refresh_token' => $this->OAuth->refreshToken), true) . ";\n";
        if (!is_dir('./data'))
            mkdir('./data');
        if (!is_dir('./data/config'))
            mkdir('./data/config');
        $fp = fopen($dir.'./data/config/'.$file.'.ini', 'w');
        fwrite($fp, $contents);
        fclose($fp);
    }

    // TODO: Possibly give separate logins separate userlists? Could just merge this info into login.ini
    function saveUserInfo()
    {
        $contents = "\$privs = ".var_export($this->privs, true).";\n";    
        $contents .= "\$levels = ".var_export($this->levels, true).";";
        $fp = fopen('./data/config/users.ini', 'w');
        fwrite($fp, $contents);
        fclose($fp);
    }
    
    function time($secs)
    {
        $time = array();
        $math['w'] = 3600 * 24 * 7;
        $math['d'] = 3600 * 24;
        $math['h'] = 3600;
        $math['m'] = 60;
        $math['s'] = 1;

        foreach($math as $key => $m)
        {
            $time[$key] = floor($secs / $m);
            $secs = $secs % $m;
        }

        return $time;
    }

    function uptime($time=null, $getfromnow=true)
    {
        if ($time == null)
            $uptime = $this->time(time() - $this->startup_time);
        else $uptime = $this->time($getfromnow ? time() - $time : $time);
        $words = array(
                'w' => 'weeks',
                'd' => 'days',
                'h' => 'hours',
                'm' => 'minutes',
                's' => 'seconds'
            );
        $str = NULL;
        foreach($uptime as $key => $u)
        {
            if ($u != 0)
            {
                $str .= $u.' '.$words[$key];
                if ($key != 's') $str .= ' ';
            }
        }
        return trim($str);
    }

    function defineColors($colors=true)
    {
        if ($colors == true)
        {
            define('NORM', "\033[0m");
            define('NORM_BOLD', "\033[0m\033[1m");
            define('RED', "\033[0;31m");
            define('RED_BOLD', "\033[1;31m");
            define('GREEN', "\033[0;32m");
            define('GREEN_BOLD', "\033[1;32m");
            define('YELLOW', "\033[0;33m");
            define('YELLOW_BOLD', "\033[1;33m");
            define('BLUE', "\033[0;34m");
            define('BLUE_BOLD', "\033[1;34m");
            define('PURPLE', "\033[0;35m");
            define('PURPLE_BOLD', "\033[1;35m");
            define('CYAN', "\033[0;36m");
            define('CYAN_BOLD', "\033[1;36m");
            define('WHITE', "\033[0;37m");
            define('WHITE_BOLD', "\033[1;37m");
            define('BLACK', "\033[0;30m");
            define('BLACK_BOLD', "\033[1;30m");
        }
        else
        {
            define('NORM', "");
            define('NORM_BOLD', "");
            define('RED', "");
            define('RED_BOLD', "");
            define('GREEN', "");
            define('GREEN_BOLD', "");
            define('YELLOW', "");
            define('YELLOW_BOLD', "");
            define('BLUE', "");
            define('BLUE_BOLD', "");
            define('PURPLE', "");
            define('PURPLE_BOLD', "");
            define('CYAN', "");
            define('CYAN_BOLD', "");
            define('WHITE', "");
            define('WHITE_BOLD', "");
            define('BLACK', "");
            define('BLACK_BOLD', "");
        }
    }

    function config($file="login")
    {
        if (!$file)
        {
            // try to find it, because for some reason getopts() doesn't like
            // when someone does -c user instead of -cuser
            global $argc, $argv;
            $file = '';
            for ($i = 0; $i < $argc - 1; ++$i)
            {
                if($argv[$i] == '-c' || $argv[$i] == '--config')
                {
                    if (isset($argv[$i + 1]) && $argv[$i + 1][0] != '-')
                        $file = $argv[$i + 1];
                }
            }
            if (!$file)
                $file = 'login';
        }
        echo "Configuring \"$file.ini\"...\n";
        echo "Please enter the following information:\n";
        $this->username = $this->Console->get("Bot username: ");
        $this->password = $this->Console->get("Bot password: ");
        $this->admin = $this->Console->get("Bot administrator (this should be your dA username): ");
        $this->trigger = new Trigger($this->Console->get("Bot trigger: "));
        $this->join = explode(' ', $this->Console->get("Channels to join (seperate with spaces): "));
        //$this->timestamp = $this->Console->get("Timestamp (leave empty for the default or if you don't understand): ", true);
        //if ($this->timestamp == false)
        $this->timestamp = "g:i:s a";
        $this->log = @strtolower($this->Console->get("Do you want your bot to log chatrooms? [y/n]: "))[0] == "y";
        $this->warnings = @strtolower($this->Console->get("Would you like your bot to warn when someone can't use a command? [y/n]: "))[0] == "y";
        $skip = false;
        if (PHP_OS == 'WINNT')
        {
            echo "If you use Windows, PLEASE NOTE that these last two options will probably not work if you pick yes. Please see the README.\n";
            $skip = @strtolower($this->Console->get("Do you want to skip these? If you're unsure, pick yes. [y/n]: "))[0] == "y";
        }
        
        if (!$skip)
        {   
            $this->colors = strtolower($this->Console->get("Would you like to use colors in the console window? [y/n]: ")) == "y";
            $this->input = strtolower($this->Console->get("Would you like to use console input? [y/n]: ")) == "y";
        }
        $this->pk = '';
        $this->cookie = array();
        $this->oldpk = '';
        $this->oldcookie = array();
        $this->levels = array();
        $this->saveConfig($file);
    }
}

class Trigger
{
    public $triggers;
    public $primaryTrigger;

    function __construct($triggers, $primaryTrigger=null)
    {
        if (!is_array($triggers))
        {
            $this->triggers = array($triggers => null);
            $this->primaryTrigger = $triggers;
        }
        else
        {
            $this->triggers = $triggers;
            if (!$primaryTrigger)
                throw new Exception("When setting multiple triggers, there must be a primary trigger given.");
            $this->primaryTrigger = $primaryTrigger;
        }
    }

    // first trigger is the one used when testing against it like a string
    function __toString()
    {
        return $this->primaryTrigger;
    }

    // check if a string is one of the bot's triggers
    function contains($trigger, $room=null)
    {
        if ($room)
            return array_search($room, $this->triggers[$trigger]) !== false;
        else
            return array_key_exists($trigger, $this->triggers);
    }

    // add a new trigger
    function add($trigger, $room=null)
    {
        if ($room)
            if (is_array($this->triggers[$trigger]))
                $this->triggers[$trigger][] = $room;
            else
                $this->triggers[$trigger] = array($room);
        else
            $this->triggers[$trigger] = null;
    }

    // delete a trigger
    function del($trigger, $room=null)
    {
        if (array_key_exists($trigger, $this->triggers))
        {
            if ($room)
            {
                if (($key = array_search($room, $this->triggers[$trigger])) !== false)
                    array_splice($this->triggers[$trigger], $key, 1);
            }
            else
                unset($this->triggers[$trigger]);

            if ($this->primaryTrigger == $trigger)
                $this->primaryTrigger = array_rand($this->triggers);
            return true;
        }
        else
            return false;
    }

    // set the primary trigger
    function set($trigger)
    {
        if (!$this->contains($trigger))
            $this->add($trigger);
        $this->primaryTrigger = $trigger;
    }
}
?>
