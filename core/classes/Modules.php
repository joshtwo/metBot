<?php

class Modules
{
    public $mods = array();
    private $bot;

    function __construct($bot)
    {
        $this->bot = $bot;
    }

    function load($module_dir, $ext="php")
    {
        if (is_dir($module_dir))
        {
            if ($module_dir[strlen($module_dir) - 1] != '/')
                $module_dir .= '/';
            $files = scandir($module_dir);
            $pos = strlen($ext) * -1;
            foreach ($files as $f)
            {
                if (substr($f, $pos) == $ext)
                {
                    $class = substr($f, 0, $pos - 1);
                    require_once($module_dir . $class . '.' . $ext);
                    $this->mods[$class] = new $class($this->bot->Event);
                }
            }
        }
    }
}

?>
