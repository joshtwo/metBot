<?php

class Console
{
    private $bot;

    function __construct($bot)
    {
        $this->bot =& $bot;
    }

    function msg($text, $type=null, $log=true, $fp=null)
    {
        $msg = '';
        if ($this->bot->input == true)
        {
            if ($fp == null && $this->bot->attach == true)
                $msg = "\r";
            else
                $msg = "\n";
        }

        if ($type == null)
        {
            $msg .= BLUE_BOLD.'['.CYAN.date($this->bot->timestamp).BLUE_BOLD.']'.NORM." $text\n";
            if ($log==true)
            {
                if ($this->bot->colors)
                {
                    $text = $this->stripColors($text);
                }
                $this->log($text, NULL);
            }
        }
        else
        {
            $msg .= BLUE_BOLD.'['.CYAN.date($this->bot->timestamp).BLUE_BOLD.']['.GREEN_BOLD.$type.BLUE_BOLD.']'.NORM." $text\r\n";
            if ($log==true)
            {
                if ($this->bot->colors)
                {

                    $text = $this->stripColors($text);
                }
                $this->log($text, $type);
            }
        }
        if ($fp)
            fwrite($fp, $msg);
        else
            echo $msg;
    }

    function notice($text, $type=null, $log=true)
    {
        $text = GREEN_BOLD.'** '.NORM.$text;
        $this->msg($text, $type, $log);
    }

    function warn($text, $type=null)
    {
        $msg = '';
        if ($this->bot->input == true)
        {
            if ($this->bot->attach == true)
                $msg = "\r";
            else
                $msg = "\n";
        }
        $msg .= RED_BOLD.'!! '.$text.NORM;
        $this->msg($msg, $type);
    }

    function log($text, $type)
    {
        is_dir('./data/logs/') || mkdir('./data/logs/');
        $text = trim($text);
        if (isset($type))
        {
            $text = '['.date($this->bot->timestamp)."][$type] $text";
        }
        else
        {
            $text = '['.date($this->bot->timestamp)."] $text";
        }
        $text = $text."\r\n";
        if ($this->bot->log == true)
        {
            if (!isset($type))
            {
                $type = 'System';
            }
            is_dir('./data/logs/'.date('M-j-y')) || mkdir('./data/logs/'.date('M-j-y'));
            $fp = fopen('./data/logs/'.date('M-j-y').'/'.$type.'.txt', 'a');
            fwrite($fp, $text);
            fclose($fp);
        }
    }

    function get($msg, $empty = false)
    {
        echo $msg;
        $fp = fopen('php://stdin', 'r');
        $input = fgets($fp);
        #$input = trim($input);
        while (($input == "\n" || $input == "\r\n") && $empty === false)
        {
            echo "Error: value cannot be empty.\r\n";
            echo $msg;
            $fp = fopen('php://stdin', 'r');
            $input = /*trim(*/fgets($fp)/*)*/;
        }
        return rtrim($input, "\n");
    }

    function stripColors($text)
    {
        $text = str_replace(NORM_BOLD, '', $text);
        $text = str_replace(NORM, '', $text);

        $text = str_replace(RED_BOLD, '', $text);
        $text = str_replace(RED, '', $text);

        $text = str_replace(GREEN_BOLD, '', $text);
        $text = str_replace(GREEN, '', $text);

        $text = str_replace(YELLOW_BOLD, '', $text);
        $text = str_replace(YELLOW, '', $text);

        $text = str_replace(BLUE_BOLD, '', $text);
        $text = str_replace(BLUE, '', $text);

        $text = str_replace(PURPLE_BOLD, '', $text);
        $text = str_replace(PURPLE, '', $text);

        $text = str_replace(CYAN_BOLD, '', $text);
        $text = str_replace(CYAN, '', $text);

        $text = str_replace(WHITE_BOLD, '', $text);
        $text = str_replace(WHITE, '', $text);

        $text = str_replace(BLACK_BOLD, '', $text);
        $text = str_replace(BLACK, '', $text);
        return $text;
    }

    function mkprompt()
    {
        $prompt = GREEN_BOLD.$this->bot->dAmn->deform($this->bot->ns).RED_BOLD.' ~> '.NORM;
        return $prompt;
    }
}

?>
