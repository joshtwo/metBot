This is a chatroom bot for [dAmn (deviantART Messaging Network)](http://chat.deviantart.com/) written in [PHP](http://php.net). It currently works with PHP 5.5+ but future versions may expect PHP 7. It is heavily event-based and modular (see `module.php` and `dAmn.php` in `core/classes/`), and the experimental branch will tend to have more modules than the ones seen here. Full documentation of the API is currently a work in progress but mostly completed, so don't hesitate to read through the core classes (particularly the above two files in addition to `Event.php`) and see how the current modules are written (see the section "Extending the bot" below) if you want to give writing your own module a shot!

# For new users
If you aren't familiar with metBot, or with dAmn bots in general, you'll want to read this section before using the bot.

## Installation
First, you need to have PHP installed in order to use this bot. If you haven't done this already and are unsure how to, please [consult the Botdom wiki](http://botdom.com/documentation/Install_PHP). If you have PHP installed, download the bot either by cloning it using `git` or [downloading and extracting the .zip](https://github.com/joshtwo/metBot/archive/master.zip) to a convenient place.

The bot is started by executing `run.php`, but controllers `run.bat` and `run.sh` (the former for Windows and the latter for Unix-based shells) allow your bot to restart (through chat commands) and retry after failure automatically. If you're a Windows user and are unsure of what to do, just double click "run.bat" to run metBot.

If this is your first time running metBot, it'll automatically start configuring a new profile for your bot. It'll ask you for the following infortion:
* Bot username - the username of your bot's dA account
* Bot password - the password of your bot's dA account.
* Bot administrator - the name of YOUR dA account
* Bot trigger - this is a character or string of characters that starts every command you send to the bot in the chatroom. For example, if the trigger is ! then you command the bot like this:
```
5:10:05 pm <n00blord666> !about
5:10:05 pm <myKoolChatBot> Hello! I'm running metBot 0.7 (repo). My owner is
  n00blord666. I've been up for 9 hours 38 minutes 22 seconds.
5:10:12 pm <n00blord666> !say Hello.
5:10:13 pm <myKoolChatBot> Hello.
```
* Channels to join - a space separated list of channels to join. ex: #Botdom #Fun4Fun #MyPrivateChat
* Channel logging - Typically you want this. If you say yes, chat logs will be recorded in the format of `data/logs/<month>-<day>-<year>/<chatroom name>` inside of the bot's folder.
* Warn users - If a user doesn't have the privileges to use a command, this will tell them if you choose yes, or ignore them quietly if you choose no.

These last two options DO NOT WORK ON WINDOWS unless you use an alternative Unix-like shell, like Cygwin.
* Console colors - If enabled, console messages will be formatted with colors to make them more readable and a little prettier. The Windows Command Prompt doesnot support these by default, so doing this will cause unparsed color escapes to show up in your console and ruin the output.
* Console input - If enabled, you can talk through the console window as your bot, and run its commands as the administrator. See section "Console Input" for more info. Note: This DOES NOT WORK ON WINDOWS due to PHP being unable to do non-blocking reads from standard input in the Command Prompt.

## Some useful commands
* !help <command name> - Gives you help on any command on the bot. Use this. Love and cherish this, like Snow White would a small, adorable woodland creature. Example: !help commands
* !commands - Shows you all the commands you have access to on the bot.
* !join <channel> - Make your bot join another channel. Example: !join #Botdom
* !user - Manages your bot's user list. This determines who has privileges for what commands. Ex: !user add MyFriend 50 would make the deviantART user MyFriend an Operator on your bot. Also try !users (shows the bot's user list) and !level/!levels (manages/lists the bot's privilege levels).

## Command-line arguments
Several options and actions can be accessed by giving the start-up script command-line arguments. Note that as of right now, options which you specify this way may end up saved in the config file the bot is using.
```
metBot Beta 7
Usage: run.php [OPTION]...
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
                        ex: run.php -j Botdom,seniors,mychatroom
  -a, --add-autojoin=LIST  Add the comma-separated list LIST of rooms to the
                             bot's existing autojoin list.
  -D FLAGS            Enable the comma-separated list of debug flags FLAGS.

Note that the "off" options override the "on" options (ex. -I supercedes -i),
and that -j/--autojoin overrides -a/--add-autojoin. If a config file for the
selected user does not exist, the bot will start configuring it by default.

Check out <http://github.com/joshtwo/metBot> for more details, set-up
instructions and further documentation.
```

# Advanced users
You may not care about this stuff unless you're a little more ambitious.

## Console input
If you're using a Unix-based OS or a terminal on Windows that doesn't suck (i.e. not Command Prompt) then you should be able to optionally use the bot's console input. Anything you type that isn't a bot or console command will show up as a message. Here's an example session:
```
[4:22:36 pm][#ThumbHub] ** Adananian has joined
[4:22:36 pm][#ThumbHub] <ThumbHubBot> Welcome back, Adananian! :thumb27637318:
[4:22:48 pm][#ThumbHub] <Adananian> Hey guys. Wassup?
#ThumbHub ~> Hi there, Adanian
[4:23:39 pm] ** Sending message...
[4:23:39 pm][#ThumbHub] <lambdabot> Hi there, Adanian
[4:24:45 pm][#ThumbHub] <Adananian> lambdabot: Hi. How are you?
#ThumbHub ~> 
```

### Bot commands
Type any command the way you'd type a command in the channel. Example:
```
#SecretLab ~> !r 1 + 1;
[4:29:56 pm] ** Running command "r 1 + 1;"...
[4:29:56 pm] Evaluating code...
[4:29:56 pm][#SecretLab] <lambdabot> Return value: <bcode>2</bcode>
#SecretLab ~>
```
### Console commands
There's a set of commands you can use in the console which start with / and which are essential to using input mode. Current ones are:
* /set <channel> - Set the channel the bot talks/operates in to a different one. Super important! Ex: /set #MyFavoriteChannel
* /topic, /title, /members - Show the topic or title or list of members of the current chatroom.
* /multi - Start a multiline message. Type /end to end the mutliline message and send it. Example:
```
#Fun4Fun ~> /multi
> This is an example
> of a multiline message
> It sends once I type /end
> /end
[4:38:25 pm][#Fun4Fun] <lambdabot> This is an example
of a multiline message
It sends once I type /end
#Fun4Fun ~> 
```
* /me <message> - perform an action in the chatroom. Example:
```
#Fun4Fun ~> /me does something awesome
[4:39:14 pm] Sending message...
[4:39:14 pm][#Fun4Fun] * lambdabot does something awesome
#Fun4Fun ~>
```
* /quit - Quits input mode. You'll probably appreciate this the most if you get curious because you use Windows and I told you not to do input mode. ;)

## Extending the bot
As mentioned above, you'll want to look at existing modules in the `modules/` folder and check out the `module.php`, `dAmn.php` and possibly `Event.php` classes in `core/classes` to figure out how to make a module. Here's a short explanation of how modules work if you're already familiar with other dAmn bots, as a complete guide is coming very soon, promise. Cross my heart and hope to hard drive failure.

If you want to create a module, you'll need to add a .php file with your module's code to `module/`, and make a class that extends from `module`. Make sure your file name and class name are the same or your bot will crash on startup. You set up your commands and events in the `main()` method, and all command hook/event hooks take two arguments: an instance of the core class, `bot`, typically named `$bot`; and the info for this current event, an `EventData` object, typically named `$cmd` for command hooks or `$evt` for event hooks.

Events execute in order of priority (higher numered priority first), and then in the order they're hooked: you can set a priority for your events when hooking them with module::hookEvent() (see `module.php`). You can trigger an event using `Event::trigger()` accessed through `$bot->Event->trigger()`. Returning anything from an event will immediately end the event execution chain, and that value will be the return value of `Event::trigger()`. Please see `Event.php`.

A more complete guide and a detailed example module will be available in upcoming releases. Trust the comments and the existing modules, even after the guide is here!
