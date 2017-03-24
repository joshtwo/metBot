<?php
// an object which represents an event
class EventData
{
    // the channel/namespace in which this event occured. ex. the channel
    // a message was sent in, the channel we've received the userlist for
    public $ns;
    // who's the target of the event? ex. who said it, who was kicked,
    // who got promoted
    public $from;
    // arguments to the event, additional non-protocol data. ex. command args
    // if you do !user add deviant-garde 50, the args are "add",
    // "deviant-garde", and "50". The utility functions assume this is a string
    // but set it to anything else at your own risk, pal.
    public $args;
    // the packet sent by the dAmn server
    public $pkt;
    // event name
    public $name;

    // constructor is for setting the most common attributes, but all of them
    // are optional
    function __construct($ns, $from, $pkt, $args)
    {
        $this->ns = $ns;
        $this->args = $args;
        $this->from = $from;
        $this->pkt = $pkt;
    }

    // you can "quote arguments" when you run commands, and this gives you a
    // string where text within quotes have the separator character within
    // them replaced with \0 so it can be parsed as a single argument, as \0
    // can't be present inside of a dAmn packet. I should possibly make this
    // return an array instead of doing the \0 thing...
    // str is the argument string, while sep is the argument separator, like " "
    function parseQuotes($str, $sep)
    {
        $start = strpos($str, '"');
        $end = @strpos($str, '"', $start+1);
        if ($start !== false && $end !== false)
            return substr($str, 0, $start) . str_replace($sep, "\0", substr($str, $start, $end - $start + 1)) . $this->parseQuotes(substr($str, $end+1), $sep);
        else
            return $str;
    }

    // find the string indices for where a certain argument begins and ends
    // pos is the argument position, ex. 0 for first arg, 1 for second, etc.
    // last is if this is the last argument, and you should therefore ignore
    // the separators and turn the rest of the string into one big argument
    // ex. if you set a welcome using !wt pc Guests Hello there, {from}.
    // and it uses event::arg(2, true), it'll return "Hello there, {from}."
    // instead of just "Hello". NOTE that this also prevents quote parsing.
    function find($pos, $last=false, $sep=' ')
    {
        if (strlen($this->args) == 0) return -1;
        $args = $this->parseQuotes($this->args, $sep);
        $start = 0;
        for ($i = 0; $i < $pos; ++$i)
        {
            $start = strpos($args, $sep, $start);
            if ($start === false)
                return -1;
            else
                $start += strlen($sep);
        }
        if ($last)
            $end = strlen($this->args);
        else
        {
            $end = @strpos($args, $sep, $start + strlen($sep));
            if ($end === false)
                $end = strlen($args);
        }
        return array($start, $end);
    }

    // get an argument from the command, which includes quote parsing
    // these arguments mean the same thing as the ones for EventData::find
    // but $indices is a reference to a variable which, if given, will hold
    // the string indices of the requested argument
    function arg($pos, $last=false, $sep=' ', &$indices=null)
    {
        $indices = list($start, $end) = $this->find($pos, $last, $sep);
        if ($start === null) return -1;
        // remove quotes if they're there and this isn't the last argument
        if (!$last && @$this->args[$start] == '"' && @$this->args[$end - 1] == '"')
            return substr($this->args, $start + 1, $end - $start - 2);
        else
            return substr($this->args, $start, $end - $start);
    }

    function replace($pos, $replacement, $last=false, $sep=' ')
    {
        $arg = $this->arg($pos, $last, $sep, $indices);
        if ($arg == -1) return -1;
        else
        {
            $this->args = substr($this->args, 0, $indices[0]) . $replacement . substr($this->args, $indices[1]);
            return $arg;
        }
    }

    function shift($pos, $last=false, $sep=' ')
    {
        $arg = $this->arg($pos, $last, $sep, $indices);
        if ($arg == -1) return -1;
        else
        {
            if ($indices[0] == 0)
            {
                $indices[0] += strlen($sep);
                $indices[1] += strlen($sep);
            }
            $this->args = substr($this->args, 0, $indices[0] - strlen($sep)) . substr($this->args, $indices[1]);
            return $arg;
        }
    }

    // if the first argument is a channel, return that channel; else return the namespace
    function ns()
    {
        return $this->args[0] == '#' || $this->args[0] == '@' ? $this->shift(0) : $this->ns;
    }
}
?>
