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
    // the config values loaded at startup
    public $config;

    function __construct()
    {
        $this->dAmn = new dAmn($this);
        $this->Commands = new Commands($this);
        $this->Modules = new Modules($this);
        $this->Event = new Event($this);
        $this->Console = new Console($this);
        $this->OAuth = new OAuth($this);

        // user agent to send when logging in
        $this->agent = $this->name . ' ' . $this->version;
    }

    // a variant of send_headers that uses and recycles the dA cookies
    // Do NOT use this for anything except requests to dA
    // Will be replaced by Browser class in the future
    function send_headers($socket, $host, $url, $referer=null, $post=null, $cookies=array())
    {
        if (!$cookies) $cookies = $this->cookie;
        $result = send_headers($socket, $host, $url, $referer, $post, $cookies);
        $newCookies = collect_cookies($result);
        if ($newCookies)
        {
            foreach(array_keys($newCookies) as $key)
                if ($newCookies[$key] == 'deleted')
                    unset($newCookies[$key]);
            if (isset($newCookies['auth']))
            {
                $this->cookie = array_merge($this->cookie, $newCookies);
                echo "Saving new cookies...\n";
                $bot->saveConfig();
            }
            else
            {
                echo "No auth cookie. Cookies:";
                print_r($newCookies);
            }
        }
        return $result;
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

        $this->config = array();
        $keys = array(
            'username', 'password', 'admin', 'trigger', 'primaryTrigger',
            'input', 'log', 'timestamp', 'token', 'cookie', 'colors',
            'warnings', 'join', 'disabledRooms', 'noGuests', 'oauth'
        );
        // save the initial config values in case the user uses command-line
        // options that clash with them
        foreach($keys as $k)
            $this->config[$k] = $$k;

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
        if ($this->cookie)
            foreach(array_keys($this->cookie) as $key)
                if ($this->cookie[$key] == 'deleted')
                    unset($this->cookie[$key]);
        $this->warnings = $warnings;
        $this->pk = $token;
        $this->defineColors($colors);
        $this->colors = $colors;
        $this->disabledRooms = $disabledRooms ? $disabledRooms : array();
        $this->noGuests = $noGuests ? $noGuests : array();
        $this->OAuth->accessToken = $oauth['access_token'];
        $this->OAuth->refreshToken = $oauth['refresh_token'];
    }

    // the complicated part of this function is that you hvae to make sure that
    // you don't save config values which were simply options given on the
    // command line, but DO save them if the user changes them since they've
    // given them
    function saveConfig($file=null)
    {
        if (!$file)
            $file = $this->configFile;

        echo "Saving config \"$file\"...\n";

        global $dir;

        // config values
        $values = array(
            'username' => $this->username,
            'password' => $this->password,
            'admin' => $this->admin,
            'trigger' => $this->trigger->triggers,
            'primaryTrigger' => $this->trigger->primaryTrigger,
            'input' => $this->start_input,
            'log' => $this->log,
            'timestamp' => $this->timestamp,
            'token' => $this->pk,
            'cookie' => $this->cookie,
            'colors' => $this->colors,
            'warnings' => $this->warnings,
            'join' => $this->join,
            'disabledRooms' => $this->disabledRooms,
            'noGuests' => $this->noGuests,
            'oauth' => array('access_token' => $this->OAuth->accessToken, 'refresh_token' => $this->OAuth->refreshToken)
        );

        $options =& $this->options;

        // check to see if the user set any command-line options that override
        // config values and if they've been modified since startup
        if (($option = _or(@$options['o'], @$options['owner'])) == $values['admin'])
        {
            echo "Skipping owner (set to \"$option\" on startup)\n";
            unset($values['admin']);
        }
        elseif ($option)
        {
            echo "Change in owner from command-line option \"$option\", saving...\n";
            unset($options['o']);
            unset($options['owner']);
        }

        if (($option = _or(@$options['t'], @$options['trigger'])))
        {
            $list = str_replace('\,', "\0", _or(@$options['trigger'], @$options['t']));
            $list = explode(',', $option);
            if ($list[0] == $values['primaryTrigger'])
            {
                echo "Skipping primary trigger (set to \"$list[0]\" on startup)\n";
                unset($values['primaryTrigger']);
            }
            else
            {
                echo "Change in primary trigger from command-line option \"$list[0]\", saving...\n";
                unset($options['t']);
                unset($options['trigger']);
            }
            // escaped commas are turned to \0s
            $triggers = array();
            foreach($list as $t)
                $triggers[str_replace("\0", ",", $t)] = array();
            if (array_diff_key($triggers, $values['trigger']) === array()
                && array_diff_key($values['trigger'], $triggers) === array()
                && _or(@$options['t'], @$options['trigger']))
                // if the primary trigger was changed, we need to save the trigger list anyway
                // and we'll know it was because -t/--trigger was deleted in the above block
            {
                echo "Skipping list of triggers (unchanged since startup)\n";
                unset($values['trigger']);
            }
            else
            {
                echo "Change in trigger from command-line option \"$option\", saving...\n";
                unset($options['t']);
                unset($options['trigger']);
            }
        }


        if ((isset($options['i']) || isset($options['input'])))
        {
            if ($values['input'] === true)
            {
                echo "Skipping input (set to \"on\" on startup)\n";
                unset($values['input']);
            }
            else
            {
                echo "Change in input from command-line option \"on\", saving...\n";
                unset($options['i']);
                unset($options['input']);
            }
        }

        if ((isset($options['I']) || isset($options['no-input'])))
        {
            if ($values['input'] === false)
            {
                echo "Skipping input (set to \"off\" on startup)\n";
                unset($values['input']);
            }
            else
            {
                echo "Change in owner from command-line option \"off\", saving...\n";
                unset($options['I']);
                unset($options['no-input']);
            }
        }

        if ((isset($options['l']) || isset($options['logging'])))
        {
            if ($values['log'] === true)
            {
                echo "Skipping logging (set to \"on\" on startup)\n";
                unset($values['log']);
            }
            else
            {
                echo "Change in owner from command-line option \"on\", saving...\n";
                unset($options['l']);
                unset($options['logging']);
            }
        }

        if ((isset($options['L']) || isset($options['no-logging'])))
        {
            if ($values['log'] === false)
            {
                echo "Skipping logging (set to \"off\" on startup)\n";
                unset($values['log']);
            }
            else
            {
                echo "Change in logging from command-line option \"off\", saving...\n";
                unset($options['L']);
                unset($options['no-logging']);
            }
        }

        $lower = function($list) {
            for ($i = 0; $i < count($list); ++$i)
                $list[$i] = strtolower($list[$i]);
            return $list;
        };

        $format = function($list) use ($lower) {
            $autojoin = explode(',', $list);
            for ($i = 0; $i < count($autojoin); ++$i)
                $autojoin[$i] = $this->dAmn->format($autojoin[$i]);
            return $lower($autojoin);
        };

        if ($format($option = _or(@$options['j'], @$options['join'])) === $lower($values['join']))
        {
            echo "Skipping autojoin (no new rooms added since startup)\n";
            unset($values['join']);
        }
        elseif ($option)
        {
            echo "Change in autojoin from command-line option \"$option\", saving...\n";
            unset($options['j']);
            unset($options['join']);
        }

        if (($option = _or(@$options['a'], @$options['add-autojoin'])) !== null
            && array_merge($lower($this->config['join']), $format($option)) == $lower($values['join']))
        {
            echo "Skipping autojoin (no new rooms added since startup)\n";
            unset($values['join']);
        }
        elseif ($option)
        {
            echo "Change in add-autojoin from command-line option \"$option\", saving...\n";
            unset($options['a']);
            unset($options['add-autojoin']);
        }

        // now, all values which the user has set with command-line arguments
        // that haven't been changed since startup should be unset in the
        // $values array
        // all we have to do now is merge that up with the current config

        // now to actually put it all together after this enormous amount of tedium
        $fileContents = array();
        $keys = array_keys($this->config);

        foreach($keys as $k)
        {
            if (!isset($values[$k]))
                $values[$k] = $this->config[$k];
            $fileContents[] = '$' . $k . ' = ' . var_export($values[$k], true) . ';';
        }
        $fileContents = join("\n", $fileContents);

        if (!is_dir('./data'))
            mkdir('./data');
        if (!is_dir('./data/config'))
            mkdir('./data/config');
        $fp = fopen($dir.'./data/config/'.$file.'.ini', 'w');
        fwrite($fp, $fileContents);
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

    // turns formatted time strings into seconds
    function stringToTime($string)
    {
        $units = array(
            's' => 1,
            'm' => 60,
            'h' => 60 * 60,
            'd' => 60 * 60 * 24,
            'w' => 60 * 60 * 24 * 7,
        );

        $string = explode(' ', $string);
        $time = 0;

        foreach($string as $s)
        {
            if ($unit = @$units[(substr($s, -1))])
                $time += (float) (substr($s, 0, -1)) * $unit;
            else return null;
        }
        return $time;
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
        for ($i = 0; $i < count($this->join); ++$i)
            $this->join[$i] = $this->dAmn->format($this->join[$i]);
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
