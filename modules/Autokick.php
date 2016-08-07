<?php
class Autokick extends module
{
    protected $sysname = "Autokick";
    protected $name = "Autokick";
    protected $version = "1";
    protected $info = "Allows you to kick someone every time they join.";
    public $autokicked = array();

    function main()
    {
        $this->hook('do_autokick', 'recv_join');
        $list = array(
            'add <i>person</i>' => 'adds <i>person</i> to the autokick list',
            'del <i>person</i>' => 'deletes <i>person</i> from the autokick list',
            'list' => 'lists the people on the autokick list',
            'reason <i>reason</i>' => 'Sets the reason for kicking, or if given no argument, shows the current one'
        );
        $help = '';
        foreach($list as $cmd => $desc)
            $help .= "<li><b>{trigger}autokick $cmd</b> $desc.</li>";  
        $this->addCmd('autokick', 75, "This command allows you to kick someone every time they join.<sub><ul>$help</ul></sub>");
        $this->load_list();
    }

    function load_list()
    {
        $this->load($this->autokicked, "autokicked");
    }

    function save_list()
    {
        $this->save($this->autokicked, "autokicked");
    }

    function c_autokick($cmd, $bot)
    {
        $subCmd = $cmd->arg(0);
        if ($subCmd[0] == '#')
        {
            $target = $subCmd;
            $subCmd = $cmd->arg(1);
            $arg = $cmd->arg(2, true);
        }
        else
        {
            $target = $bot->dAmn->deform($cmd->ns);
            $arg = $cmd->arg(1, true);
        }
        $ns = $bot->dAmn->format(strtolower($target));
        if (!isset($this->autokicked[$ns]))
            $this->autokicked[$ns] = array('list'=>array(),'reason'=>'','switch'=>true);
        switch ($subCmd)
        {
        case 'add':
            if ($arg==-1)
                $bot->dAmn->say("$cmd->from: You need to specify a person to add.", $cmd->ns);
            else
            {
                $this->autokicked[$ns]['list'][] = $arg;
                $this->save_list();
                $bot->dAmn->say("$cmd->from: <b>:dev$arg:</b> has been added to the autokick list for $target.", $cmd->ns);
            }
            break;
        case 'del':
            if ($arg==-1)
                $bot->dAmn->say("$cmd->from: You need to specify a person to delete.", $cmd->ns);
            else
            {
                if (!in_array($arg, $this->autokicked[$ns]['list']))
                    $bot->dAmn->say("$cmd->from: :dev$arg: is not on the autokick list for $target.", $cmd->ns);
                else
                {
                    unset($this->autokicked[$ns]['list'][array_search($arg, $this->autokicked[$ns]['list'])]);
                    $this->save_list();
                    $bot->dAmn->say("$cmd->from: <b>:dev$arg:</b> was deleted from the autokick list for $target.", $cmd->ns);
                }
            }
            break;
        case 'reason':
            if ($arg==-1)
                $bot->dAmn->say("$cmd->from: Autokick reason is <i>\"".$this->autokicked[$ns]['reason']."\"</i>.", $cmd->ns);
            else
            {
                $this->autokicked[$ns]['reason'] = $arg;
                $this->save_list();
                $bot->dAmn->say("$cmd->from: The reason \"<i>$arg</i>\" will be used to autokick people in $target.", $cmd->ns);
            }
        break;
        case 'list':
            if ($this->autokicked[$ns]['list'] != array())
            {
                $list = "$cmd->from: The following users are on the autokick list for $target:<br><sub>";
                $list .= ':dev' . join(':<br>:dev', $this->autokicked[$ns]['list']) . ':';
                $bot->dAmn->say($list, $cmd->ns);
            }
            else $bot->dAmn->say("$cmd->from: There is no autokick list for $target.", $cmd->ns);
            break;
        case 'on':
        case 'off':
            $switch = $subCmd == 'on';
            if ($this->autokicked[$ns]['switch'] == $switch)
                $bot->dAmn->say("$cmd->from: Autokicking in $target was already $subCmd.", $cmd->ns);
            else
            {
                $this->autokicked[$ns]['switch'] = $switch;
                $bot->dAmn->say("$cmd->from: Autokicking in $target is now <b>$subCmd</b>.", $cmd->ns);
                $this->save_list();
            }
            break;
        default:
            $bot->dAmn->say("$cmd->from: You must specify if you want to add or delete a person from the list, or show the list.", $cmd->ns);
        }
    }

    function do_autokick($evt, $bot)
    {
        $ns = strtolower($evt->ns);
        if (isset($this->autokicked[$ns]) && @$this->autokicked[$ns]['switch'] && in_array($evt->from, $this->autokicked[$ns]['list']))
            $bot->dAmn->kick($evt->from, $evt->ns, $this->autokicked[$ns]['reason']);
    }
}
?>
