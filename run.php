<?php
// try the STDERR shit
ini_set('display_errors', 'On');
ini_set('error_log', './bot-errors.log');
ini_set('default_socket_timeout', 15);

// return the first given argument that's true; Haskell inspired
function _or()
{
    foreach(func_get_args() as $a)
        if ($a)
            return $a;
}

// is a certain debug flag defined?
function _debug($flag)
{
    return defined("METBOT_DEBUG_$flag");
}

if (file_exists('./core/status/restart.bot'))
{
    // 
    unlink('./core/status/restart.bot');
}
if (file_exists('./core/status/close.bot'))
    unlink('./core/status/close.bot');



$options = getopt('hc::u:o:t:a:b:iIlLqQj:a:D:', array(
    'help',
    'config::',
    'user:',
    'owner:',
    'trigger:',
    'agent:',
    'browser:',
    'input',
    'no-input',
    'logging',
    'no-logging',
    'autojoin:',
    'add-autojoin:',
    'oauth',
    'no-oauth'
));

if ($options === false || isset($options['h']) || isset($options['help']))
{
    // help message
    echo <<<EOT
Usage: {$argv[0]} [OPTION]...
Runs metBot, the one and only.

Options:
  -h, --help          Print this help text.
  -c, --config=USER   Set up a config file, optionally for USER if specified.
  -u, --user=USER     Run the bot with USER's config file.
  -o, --owner=USER    Set the owner of the bot to USER.
  -t, --trigger=TRIGS Set the bot's trigger(s), a comma-separated list. If you
                        want to use a comma in your trigger, type it as \, in
                        the list. Ex -t !,\,,^ gives you ! , ^ as 3 triggers.
  -A, --agent=AGENT   Send the string AGENT as the bot's user agent on login.
  -b, --browser=AGENT Set the browser user agent. Automatically sets the user-
                        agent to imitate the deviantART chat.
  -i, --input         Force input to be on.
  -I, --no-input      Force input to be off.
  -l, --logging       Force logging to be on.
  -L, --no-logging    Force logging to be off.
  -q, --oauth         Force collection of new OAuth session.
  -Q, --no-oauth      Keep the bot from attempting to get a new OAuth session
                        on startup. This is useful when you run the bot on a
                        server and don't want it possibly freezing up because
                        something happened to the session.
  -j, --autojoin=LIST Set the autojoin list to LIST, a comma-separated list
                        of chatrooms. You don't need to use # in the names.
                        ex: {$argv[0]} -j Botdom,seniors,mychatroom
  -a, --add-autojoin=LIST  Add the comma-separated list LIST of rooms to the
                             bot's existing autojoin list.
  -D FLAGS            Enable the comma-separated list of debug flags FLAGS.

Note that the "off" options override the "on" options (ex. -I supercedes -i),
and that -j/--autojoin overrides -a/--add-autojoin. If a login.ini config
file does not exist and you have not used -u/--user to pick a different file,
the bot will start configuring by default.

Check out <http://github.com/joshtwo/metBot> for more details, set-up
instructions and further documentation.

EOT;
    return;
}

require_once('./core/core.php');
$bot = new bot();
// in this current test release we're going to disable OAuth by
// default as we only use it inside of the private release
//$bot->getOAuth = false;

// this allows us to check our command-line options list against the config
// file we're loading so we can decide what to save and what not to save
// if the user sets a config directive with a command-line argument and
// does not change it during the course of running the bot
// (such as adding a channel to the autojoin list after doing -a or -j)
// then bot::saveConfig() will use the $join value that was in the config
// at loading time. Otherwise it'll save the new value.
$bot->options = $options;

if (isset($options['D']))
{
    foreach (explode(',', $options['D']) as $constant)
        define("METBOT_DEBUG_$constant", true);
}

if (isset($options['c'])
    || isset($options['config'])
    || ($noConfigFile = !file_exists('./data/config/login.ini')))
{
    if (!$noConfigFile)
    {
        $bot->config($user = _or(@$options['config'], @$options['c']));
        $answer = $bot->Console->get("Do you want to connect as $user? [y/n]");
        if ($answer == "y" || $answer == "Y")
            $bot->configFile = $user;
        else exit(); // quit after setting up new config file
    }
    else
        $bot->config();
}

if (isset($options['u']) || isset($options['user']))
    $bot->configFile = _or(@$options['user'], @$options['u']);

$bot->readConfig();

if (isset($options['o']) || isset($options['owner']))
{
    $bot->admin = _or(@$options['owner'], @$options['o']);
    $bot->Console->notice("Setting owner to {$bot->admin}!");
}

if (isset($options['t']) || isset($options['trigger']))
{
    // escaped commas are turned to \0s
    $option = str_replace('\,', "\0", _or(@$options['trigger'], @$options['t']));
    $option = explode(',', $option);
    $triggers = array();
    foreach($option as $t)
        $triggers[str_replace("\0", ",", $t)] = array();
    $bot->trigger = new Trigger($triggers, str_replace("\0", ',', $option[0]));
}

if (isset($options['i']) || isset($options['input']))
{
    $bot->Console->notice("Input forced on!");
    $bot->input = true;
}
if (isset($options['I']) || isset($options['no-input']))
{
    $bot->Console->notice("Input forced off!");
    $bot->input = false;
}
if (isset($options['l']) || isset($options['logging']))
{
    $bot->Console->notice("Logging forced on!");
    $bot->log = true;
}
if (isset($options['L']) || isset($options['no-logging']))
{
    $bot->Console->notice("Logging forced off!");
    $bot->log = false;
}
if (isset($options['q']) || isset($options['oauth']))
{
    $bot->Console->notice("Forcing new OAuth session!");
    $bot->getOAuth = true;
}
if (isset($options['Q']) || isset($options['no-oauth']))
{
    $bot->Console->notice("Refusing to retrieve new OAuth session!");
    $bot->getOAuth = false;
}

// are we adding or replacing the autojoin list?
$add = false;

if (isset($options['j']) ||
    isset($options['autojoin']) ||
    (isset($options['a']) ||
     isset($options['add-autojoin'])) &&
    ($add = true))
{
    $autojoin = _or(
        @$options['j'],
        @$options['autojoin'],
        @$options['a'],
        @$options['add-autojoin']
    );
    $autojoin = explode(',', $autojoin);
    for ($i = 0; $i < count($autojoin); ++$i)
        $autojoin[$i] = $bot->dAmn->format($autojoin[$i]);
    $bot->join = $add ? array_merge($bot->join, $autojoin) : $autojoin;
    $msg = "The following rooms were " . ($add ? "added" : "set");
    $msg .= " to the autojoin list:\n  ";

    for ($i = 0; $i < count($autojoin); ++$i)
        $autojoin[$i] = $bot->dAmn->deform($autojoin[$i]);
    $msg .= join(', ', $autojoin);

    $bot->Console->notice($msg);
}

if (isset($options['b']) || isset($options['browser']))
{
    $bot->agent = "dAmn WebClient 0.7.pre-1 - dAmn Flash plugin 1.2\nbrowser=" . ($browser = _or(@$options['b'], @$options['browser']));
    $bot->agent .= "\nurl=http://chat.deviantart.com/chat/" . substr($bot->dAmn->deform($bot->join[0]), 1);
    switch (PHP_OS)
    {
    case 'Linux':
        $bot->agent .= "\nflash_runtime=Linux 4.8.11-1-ARCH - LNX 11,2,202,644";
        break;
    }

    $bot->Console->notice("Set browser agent to \"{$browser}\"!");
}

if (isset($options['A']) || isset($options['agent']))
{
    $bot->agent = _or(@$options['A'], @$options['agent']);
    $bot->Console->notice("Setting the user agent to {$bot->agent}!");
}

$bot->Modules->load('./modules/');
if (!$bot->pk)
{
    $bot->savePk = true;
    $array = $bot->dAmn->getAuthtoken($bot->username, $bot->password);
    if (isset($array['token']) && isset($array['cookie']))
    {
        $bot->pk = $array['token'];
        $bot->cookie = $array['cookie'];
        $bot->saveConfig();
    }
}
else
    $bot->Console->notice("Attemping to use saved authtoken...");

if (!$bot->pk)
{
    $bot->Console->warn("Failed to retrieve authtoken. Check your username and password to make sure your login is correct.");
    $bot->quit = true;
}

function handleLogin(&$bot, $error, $skip_retry=false, $retries=1)
{
    $retry_error = 1;
    switch ($error)
    {
        case 1:
            $bot->Console->notice("Logged in to dAmn successfully!");
            foreach($bot->join as $j)
            {
                $bot->dAmn->join($j);
                $bot->dAmn->packetLoop();
            }
            if ($bot->savePk)
            {
                $bot->Console->notice("Saving new authtoken...");
                $bot->saveConfig();
            }
        break;
        case 2:
            $bot->Console->warn(($skip_retry ? "Failed to log in with old authtoken." : "Failed to log in with old authtoken, retrieving new authtoken...") . NORM, null);
            if (!$skip_retry)
            {
                $array = $bot->dAmn->getAuthtoken($bot->username, $bot->password);
                if (isset($array['token']) && isset($array['cookie']))
                {
                    $bot->pk = $array['token'];
                    $bot->cookie = $array['cookie'];
                    $bot->savePk = true;
                }
                $retry_error = $bot->dAmn->login($bot->username, $bot->pk);
            }
            else
            {
                echo "Giving up...\n";
            }
        break;
        case 3:
            $bot->Console->warn("Uh oh, looks like you're banned from dAmn!");
        break;
        case 4:
            if ($retries > 50)
            {
                $bot->Console->warn("Look, we've tried this 50 god damn times in a row. Let's start with clean slate.");
                $retries = 1;
            }
            else
                $bot->Console->warn("Failed to connect to dAmn... let's try again!");
            $wait = (int) log(pow($retries, $retries));
            echo "Sleeping for $wait seconds...\n";
            sleep($wait);
            $retry_error = $bot->dAmn->login($bot->username, $bot->pk);
            handleLogin($bot, $retry_error, false, ++$retries);
        break;
        default:
            $bot->Console->warn("I'm not sure what's going on here! Error #$retry_error");
            exit();
        break;
    }

    if ($retry_error != 1 && !$skip_retry)
    {
        handleLogin($bot, $retry_error, true);
        $bot->saveConfig();
    }
}

function run(&$bot)
{
    if (!$bot->quit)
    {
        $success = false;
        if (!$bot->OAuth->accessToken)
        {
            $bot->Console->notice("Getting OAuth credentials...");
            if ($bot->OAuth->refreshToken && !isset($bot->getOAuth))
            {
                $success = $bot->OAuth->refreshTokens();
                if ($success)
                    $bot->OAuth->notice("Successfully refreshed tokens!");
                else
                    $bot->OAuth->warn("Failed to refresh tokens.");
            }
        }
        else
            $success = true;

        // if this looks a tad confusing, know it forces us to make sure
        // that if the user wants to force/refuse the retrieval of a new
        // session, we obey it, whether or not we succeeded above
        if (isset($bot->getOAuth) && $bot->getOAuth || !$success && (!isset($bot->getOAuth) || $bot->getOAuth))
        {
            $bot->Console->notice("Authenticating through OAuth...");
            $bot->Console->notice("Please go to http://localhost:1338/ in your browser.");
            $success = $bot->OAuth->getCode('browse user');
            if (!$success)
                $bot->Console->warn("Failed to get code. Giving up...");
            else
            {
                $success = $bot->OAuth->getTokens();
                if (!$success)
                    $bot->Console->warn("Failed to get tokens with code. Giving up...");
                else
                    $bot->Console->notice("Successfully OAuth authenticated!");
            }
        }
        $error = $bot->dAmn->login($bot->username, $bot->pk);
        handleLogin($bot, $error);
    }
    stream_set_blocking($bot->dAmn->s, false);

    if ($bot->input === null)
        $bot->input = $bot->start_input;

    while (!$bot->quit)
    {
        usleep(5000);
        if (!$bot->input)
        {
            $bot->dAmn->packetLoop();
            if ($bot->disconnected)
            {

                if (!$bot->quit)
                {
                    $bot->restart = true;
                    $fp = fopen('./core/status/restart.bot', 'w');
                    fclose($fp);
                }
                $bot->Console->warn("The bot has disconnected from the server.");
                $bot->disconnected = false;
                $bot->quit = true;
            }
        }
        else
        {
            $bot->Console->notice("Input is now on.");
            // I run dAmn::packetLoop() in dAmn::input()
            $bot->dAmn->input();
            stream_set_blocking(STDIN, true);
            $bot->Console->notice("Input is now off.");
            $bot->input = false;
        }
        $bot->Event->loop(); // events hooked to 'loop'
    }
    if ($bot->quit || $bot->restart)
        $bot->input = false;
    $bot->Console->notice("Shutting down...");
}

// The following are for debugging purposes
function fakeRun(&$bot)
{
    echo "Enter packets to be interpreted. \\n becomes newline.";
    for(;;) $bot->dAmn->process(trim(str_replace("\\n", "\n", $bot->Console->get(">", true))));
}

// if I need this more I should spruce it up a bit using the !e command's code
function evalLoop(&$bot)
{
    echo "Enter code to be evaluated:";
    for(;;)
    {
        $code = eval(trim($bot->Console->get("> ")));
        if ($code) print_r($code);
    }
}

run($bot);
?>
