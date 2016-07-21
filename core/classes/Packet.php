<?php
class Packet implements ArrayAccess
{
    public $cmd;
    public $param;
    public $body;
    public $args = array();
    public $raw;

    function __construct($data=null, $sep='=', $parse_body=true)
    {
        if ($data!=null)
        {
            $this->parse($data, $sep);

            if ($parse_body && $this->body != NULL && $this->cmd != 'property' && $this->cmd != 'kicked')
            {
                $this->body = new Packet($this->body, '=', false);
            }
        }
    }

    // TODO: can this be rewritten? Answer: probably. Start witH Haskell parser
    function parse($data, $sep) //adapted from photofroggy's PHP parser
    {
        $this->raw = $data;
        if ($body = stristr($data, "\n\n"))
        {
            $this->body = substr($body, 2);
            $data = substr($data, 0, strpos($data, "\n\n"));
        }

        foreach(explode("\n", $data) as $id => $str)
        {
            if (($pos = strpos($str, $sep)) != 0)
            {
                $this->args[substr($str, 0, $pos)] = substr($str, $pos+1);
            }
            elseif (strlen($str) >= 1)
            {
                if ($id == 0)
                {
                    if (!stristr($str, ' ')) $this->cmd = $str;
                    else
                    {
                        $this->cmd = substr($str, 0, strpos($str, ' '));
                        $this->param = trim(stristr($str, ' '));
                    }
                }
                else
                {
                    $this->args[] = $str;
                }
            }
        }
    }

    function offsetExists($offset)
    {
        return isset($this->args[$offset]);
    }

    function offsetGet($offset)
    {
        return isset($this->args[$offset]) ? $this->args[$offset] : NULL;
    }

    function offsetSet($offset, $value)
    {
        $this->args[$offset] = $value;
    }

    function offsetUnset($offset)
    {
        unset($this->args[$offset]);
    }

    function __toString()
    {
        return $this->raw;
    }
}
?>
