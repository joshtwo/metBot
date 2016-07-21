<?php

function send_headers($socket, $host, $url, $referer=null, $post=null, $cookies=array(), $bytes=null)
{
    if (!$socket)
        return "";
    try
    {
        $headers = "";
        if (isset($post))
            $headers .= "POST $url HTTP/1.1\r\n";
        else $headers .= "GET $url HTTP/1.1\r\n";
        $headers .= "Host: $host\r\n";
        //$headers .= "User-Agent: Mozilla/5.0 (X11; Linux i686; rv:38.0) Gecko/20100101 Firefox/38.0\r\n";
        $headers .= "User-Agent: Mozilla/5.0 (X11; Linux i686; rv:46.0) Gecko/20100101 Firefox/46.0\r\n";
        if ($referer)
        {
            if (is_string($referer))
                $headers .= "Referer: $referer\r\n";
            elseif (is_array($referer))
            {
                foreach($referer as $header => $val)
                    $headers .= "$header: $val\r\n";
            }
        }
        $headers .= "Connection: close\r\n";
        if ($cookies != array())
            $headers .= "Cookie: ".cookie_string($cookies)."\r\n";
        $headers .= "Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8\r\n";
        //$headers .= "Accept-Charset: ISO-8859-1,utf-8;q=0.7,*;q=0.7\r\n";
        $headers .= "Accept-Language: en-US,en;q=0.5\r\n";
        // the fix we've all been waiting for; ask for compressed data
        $headers .= "Accept-Encoding: gzip, deflate\r\n";
        if (isset($post))
        {
            $headers .= "Content-Type: application/x-www-form-urlencoded\r\n";
            $headers .= "Content-Length: ".strlen($post)."\r\n\r\n$post";
        }
        else $headers .= "\r\n";
        $response = "";
        if (_debug('HTTP'))
            echo "OUTGOING:\n\n$headers\n\n";
        fputs($socket, $headers);
        while (!feof ($socket)) $response .= fgets ($socket, 8192);

        if (strpos($response, "Content-Encoding: gzip\r\n") !== false)
        {
            // if it has a content encoding, I think it pretty much must have a body
            if ($pos = strpos($response, "\r\n\r\n"))
            {
                echo "Found CRLF pair...\n";
                $head = substr($response, 0, $pos);
                $body = substr($response, $pos + 4);
            }
            else // they don't send the HTTP body with 2 CRFLs at the start, so we use Content-Length to manually slice it out
            {
                $start = strpos($response, "Content-Length: ");
                if ($start === false)
                {
                    echo "No content length. Checking for gzip header...\n";
                    $gzipHeader = substr(gzencode(''), 0, 10);
                    if (($start == strpos($response, $gzipHeader)) === false)
                    {
                        echo "Can't find gzip header. Returning unmodified reponse...\n";
                        if (_debug('HTTP'))
                            echo "INCOMING RESPONSE (missing gzip header):\n\n" . substr($response, 0, 900) . "\n\n";
                        return $response;
                    }
                    echo "Found gzip header at position $start\n";
                    $head = substr($response, 0, $start);
                    $body = substr($response, $start);
                }
                else
                {
                    $start += strlen("Content-Length: ");
                    $end = strpos($response, "\r\n", $start);
                    $contentLength = substr($response, $start, $end - $start);
                    if (_debug('HTTP'))
                        echo "Content length is $contentLength\n";
                    $contentLength = (int) $contentLength;
                    $head = substr($response, 0, 0 - $contentLength);
                    $body = substr($response, 0 - $contentLength);
                }
            }
            if (strpos($response, "Transfer-Encoding: chunked\r\n") !== false)
            {
                $body = removeChunksFromBody($body);
            }
            $body = gzdecode($body);
            if (_debug('HTTP'))
            {
                echo "INCOMING HEAD:\n\n$head\n\n";
                echo "INCOMING BODY DECODED:\n\n" . substr($body, 0, 200) . "\n\n";
            }
            return $head . "\r\n\r\n" . $body;

        }
        else
        {
            echo "We didn't get back a compressed response, oddly enough.\n";
            if (_debug('HTTP'))
                echo "INCOMING:\n\n" . substr($response, 0, 300) . "\n\n";
            return $response;
        }
    }
    catch (Exception $e)
    {
        echo "Exception occured: ".$e->getMessage()."\n";
        return "";
    }
}

function collect_cookies($response)
{
    $response = substr($response, 0, strpos($response, "\r\n\r\n"));
    $cookies = array();
    foreach (explode("\r\n", $response) as $line)
    {
        if (strpos($line, "Set-Cookie: ") === 0)
        {
            $eq = strpos($line, '=');
            $cookies[substr($line, 12, $eq - 12)] = substr($line, $eq + 1, strpos($line, '; ') - ($eq + 1));
        }
    }
    return $cookies;
}

function cookie_string($cookies)
{
    // is of the format cookie=value; cookie2=value2
    $str = '';
    foreach ($cookies as $key => $val)
    {
        if (!$str)
            $str = "$key=$val";
        else $str .= "; $key=$val";
    }
    return $str;
}

function removeChunks($response)
{
    if (($pos = strpos($response, "\r\n\r\n")) !== false)
    {
        $head = substr($response, 0, $pos);
        $body = substr($response, $pos + 4);
        if (strpos($response, "Transfer-Encoding: chunked\r\n") !== false) // this is safe
        {
            echo "Found chunked response...\n";
            $result = '';
            $num = null;
            $previous = null;
            while ($num !== 0)
            {
                $pos = strpos($body, "\r\n");
                if ($pos === false)
                    break;
                $num = substr($body, 0, $pos);
                $num = hexdec($num);
                if (!$num)
                    break;
                $result .= substr($body, $pos+2, $num);
                $body = substr($body, $pos + 2 + $num + 2);
            }
            return $head . $result;
        }
        return $head . $body;
    }
    else
    {
        echo "Not chunked. Length: " . strlen($response) . "\n";
        // right now, if no chunking happens, just return the request unaffected
        return $response;
    }
}

function removeChunksFromBody($body)
{
    echo "Found chunked response...\n";
    $result = '';
    $num = null;
    $previous = null;
    while ($num !== 0)
    {
        $pos = strpos($body, "\r\n");
        if ($pos === false)
            break;
        $num = substr($body, 0, $pos);
        $num = hexdec($num);
        if (!$num)
            break;
        $result .= substr($body, $pos+2, $num);
        $body = substr($body, $pos + 2 + $num + 2);
    }
    return $result;
}


class dAmn
{
    // TODO: clean up public implementation details somehow
    public $s;

    private $bot;

    // for debugging
    public $queue = array();
    public $brokenPackets = array();
    public $sent;
    public $recieved;
    public $socket_err;
    public $socket_err_str;

    function __construct($bot)
    {
        $this->bot = $bot;
    }

    function send($data)
    {
        $this->sent = fwrite($this->s, $data);
    }

    function recv($keep_count=false)
    {
        if ($this->bot->quit) return;
        $response = '';
        $response = fread($this->s, 15000);
        if (feof($this->s) || $response === false)
        {
            if ($this->socket_err && $this->socket_err_str)
                $this->bot->Console->warn("Socket error #$this->socket_err: $this->socket_err_str");
            $this->bot->disconnected = true;
            $this->bot->Console->notice("Disconnected!");
        }
        return $response;
    }

    function packetLoop() // recieve and process a packet or packets, and return the packet
    {
        $done = false;
        $buffer = '';
        $loops = 0;
        $pkt = '';
        while (!$done && ++$loops)
        {
            /*if ($loops > 2)
            {
                echo "Broken packet received at position ". count($this->queue) . ": -----\n$pkt\n-----\n";
                $this->brokenPackets[] = $pkt;
            }*/
            $pkt = $this->recv();
            if ($this->bot->disconnected)
            {
                echo "Quitting loop due to disconnect...\n";
                $done = true;
            }
            // because the socket is nonblocking, at times $pkt == '' when you
            // only have a partial packet constructed
            // for god knows how long I failed to notice this subtle issue
            // now I make sure the buffer isn't partially full
            if ($pkt == '' && $buffer == '') return -1;
            $buffer .= $pkt;
            if ($buffer[strlen($buffer)-1] == "\0")
            {
                /*if ($loops > 2)
                {
                    echo "Finishing packet:----\n$pkt\n----\n";
                    $this->brokenPacket[] = "@@@$pkt";
                    if (count($this->brokenPacket) >= 100)
                        array_shift($this->brokenPacket);
                }*/
                $done = true;
                $pkt_arr = explode("\0", $buffer);
                array_pop($pkt_arr);
                foreach ($pkt_arr as $p)
                {
                    /*if (count($this->queue) >= 100)
                        array_shift($this->queue);
                    $this->queue[] = $p;*/
                    $this->process($p);
                }
            }
        }
    }

    function say($msg, $chan, $npmsg=false)
    {
        $chan = $this->format($chan);
        if (substr(trim($msg), 0, 4)=="/me ")
        {
            $this->send("send $chan\n\naction main\n\n". substr($msg, 4) ."\0");
        }
        else
        {
            if ($npmsg)
                $this->send("send $chan\n\nnpmsg main\n\n$msg\0");
            else
                $this->send("send $chan\n\nmsg main\n\n$msg\0");
        }
    }

    function promote($person, $privclass, $chan)
    {
        $chan = $this->format($chan);
        $this->send("send $chan\n\npromote $person\n\n$privclass\0");
    }

    function kick($person, $chan, $reason="")
    {
        $chan = $this->format($chan);
        $this->send("kick $chan\nu=$person\n\n$reason\0");
    }

    function kill($person, $reason="")
    {
          $this->send("kill login:$person\n\n$reason\0");
    }

    function ban($person, $chan)
    {
        $chan = $this->format($chan);
        $this->send("send $chan\n\nban $person\n\0");
    }

    function unban($person, $chan)
    {
        $chan = $this->format($chan);
        $this->send("send $chan\n\nunban $person\n\0");
    }

    function admin($command, $chan)
    {
        $chan = $this->format($chan);
        $this->send("send $chan\n\nadmin\n\n$command\0");
    }

    function get($property, $chan)
    {
        $chan = $this->format($chan);
        $this->send("get $chan\np=$property\n\0");
    }

    function join($chan)
    {
        $chan = $this->format($chan);
        $this->send("join ". $chan . "\n\0");
    }

    function part($chan)
    {
        $chan = $this->format($chan);
        $this->send("part ". $chan . "\n\0");
    }

    function set_title($title, $chan)
    {
        $chan = $this->format($chan);
        $this->send("set $chan\np=title\n\n$title\0");
    }

    function set_topic($topic, $chan)
    {
        $chan = $this->format($chan);
        $this->send("set $chan\np=topic\n\n$topic\0");
    }

    // TODO: possibly redo this function? I thought this was a good idea... at 15
    function query($item, $chan) // return data the bot has stored about chatrooms it joined
    {
        $chan = $this->format($chan);
        if (in_array($chan, array_keys($this->info)))
        {
            if (preg_match("/^(topic|title)$/", $item))
            {
                return isset($this->info[$chan][$item]['body'])?$this->info[$chan][$item]['body']:false;
            }
            elseif (preg_match("/^(topic|title)-by$/", $item))
            {
                return isset($this->info[$chan][substr($item, 0, 5)]['by'])?$this->info[$chan][substr($item, 0, 5)]['by']:false;
            }
            elseif (preg_match("/^(topic|title)-ts$/", $item))
            {
                return isset($this->info[$chan][substr($item, 0, 5)]['ts'])?$this->info[$chan][substr($item, 0, 5)]['ts']:false;
            }
            elseif ($item == "members")
            {
                return isset($this->info[$chan]['members'])?$this->info[$chan]['members']:false;
            }
            elseif ($item == "members-list")
            {
                return isset($this->info[$chan]['members'])?array_keys($this->info[$chan]['members']):false;
            }
            elseif ($item == "pc")
            {
                return isset($this->info[$chan]['pc'])?$this->info[$chan]['pc']:false;
            }
            elseif ($item == "login")
            {
                return isset($this->info[$chan])?$this->info[$chan]:false;
            }
            elseif ($item == "info")
            {
                return isset($this->info[$chan]['info'])?$this->info[$chan]['info']:false;
            }
            elseif ($item == "conns")
            {
                return isset($this->info[$chan]['conns'])?$this->info[$chan]['conns']:false;
            }
            elseif (preg_match("/^(pc|user)-info$/", $item))
            {
                return isset($this->info[$chan][str_replace("-", '', $item)])?$this->info[$chan][str_replace("-", '', $item)]:false;
            }
            else return false;
        }
        else return false;
    }


    function parse_tablumps($text)
    {
        $search[]="/&emote\t([^\t])\t([0-9]+)\t([0-9]+)\t(.+)\t(.+)\t/U";
        $replace[]=":\\1:";
        $search[]="/&emote\t(.+)\t([0-9]+)\t([0-9]+)\t(.+)\t(.+)\t/U";
        $replace[]="\\1";
        $search[]="/&br\t/";
        $replace[]="\n";
        $search[]="/&(b|i|s|u|sub|sup|code|ul|ol|li|p|bcode)\t/";
        $replace[]="<\\1>";
        $search[]="/&\\/(b|i|s|u|sub|sup|code|ul|ol|li|p|bcode)\t/";
        $replace[]="</\\1>";
        $search[]="/&acro\t(.*)\t(.*)&\\/acro\t/U";
        $replace[]="<acronym title=\"\\1\">\\2</acronym>";
        $search[]="/&abbr\t(.*)\t(.*)&\\/abbr\t/U";
        $replace[]="<abbr title=\"\\1\">\\2</abbr>";
        $search[]="/&link\t([^\t]*)\t([^\t]*)\t&\t/U";
        $replace[]="\\1 (\\2)";
        $search[]="/&link\t([^\t]*)\t&\t/U";
        $replace[]="\\1";
        $search[]="/&a\t(.*)\t(.*)\t(.*)&\\/a\t/U";
        $replace[]="<a href=\"\\1\" title=\"\\2\">\\3</a>";
        $search[]="/&(iframe|embed)\t(.*)\t([0-9]*)\t([0-9]*)\t&\\/(iframe|embed)\t/U";
        $replace[]="<\\1 src=\"\\2\" width=\"\\3\" height=\"\\4\" />";
        $search[]="/&img\t(.*)\t([0-9]*)\t([0-9]*)\t/U";
        $replace[]="<img src=\"\\1\" width=\"\\2\" height=\"\\3\" />";
        $search[]="/&thumb\t([0-9]*)\t(.*)\t(.*)\t(.*)\t(.*)\t(.*)\t/U";
        $replace[]=":thumb\\1:";
        $search[]="/&dev\t([^\t])\t([^\t]+)\t/U";
        $replace[]=":dev\\2:";
        $search[]="/&avatar\t([^\t]+)\t([^\t]+)\t/U";
        $replace[]=":icon\\1:";
        $search[]="/ width=\"\"/";
        $replace[]="";
        $search[]="/ height=\"\"/";
        $replace[]="";
        $search[]="/&gt;/";
        $replace[]=">";
        $search[]="/&lt;/";
        $replace[]="<";
        $search[]="/&amp;/";
        $replace[]="&";
        $search[]="/&quot;/";
        $replace[]='"';
        $oldtext='';
        while($text!=$oldtext)
        {
            $oldtext=$text;
            $text=preg_replace($search, $replace, $text);
        }

        return $text;
    }

    function process($packet) // process a recieved packet
    {
        //echo "Packet: $packet\n";
        $packet = $this->parse_tablumps($packet);
        $packet = new Packet($packet);

        $this->bot->Event->setPacket($packet);
        switch($packet->cmd)
        {
            case 'recv':
                $this->bot->Event->setNs($ns = $ns = $packet->param);
                $chat = $this->deform($ns);

                switch($packet->body->cmd)
                {
                    case 'msg':
                        $this->bot->Event->setFrom($from = $packet->body['from']);
                        $this->bot->Console->msg(NORM_BOLD.'<'.$from.'> '.NORM.$packet->body->body, $chat);
                        foreach ($this->bot->trigger->triggers as $t => $rooms)
                        {
                            if (substr($packet->body->body, 0, strlen($t)) == $t && $from != $this->bot->username)
                            {
                                if ($rooms && !in_array($packet->param, $rooms)) return;
                                $this->bot->Commands->process($from, substr($packet->body->body, strlen($t)));
                                break;
                            }
                        }
                    break;

                    case 'action':
                        $this->bot->Event->setFrom($from = $packet->body['from']);
                        $this->bot->Console->msg(CYAN_BOLD."* ".$from.CYAN." ".$packet->body->body.NORM, $chat);
                    break;

                    case 'join':
                        $this->bot->Event->setFrom($from = $packet->body->param);
                        $this->bot->Console->notice($from." has joined", $chat);
                        if (!isset($this->info[$ns]['members'][$from]))
                            $this->info[$ns]['members'][$from] = new Packet($packet->body->body);
                    break;

                    case 'part':
                        if (!isset($packet->body['r']))
                        {
                            $packet->body['r'] = " ";
                        }

                        $this->bot->Console->notice($packet->body->param.' has left ['.$packet->body['r'].']', $chat);
                        $this->bot->Event->setFrom($from = $packet->body->param);
                        unset($this->info[$ns]['members'][$from]);
                    break;

                    case 'privchg':
                        $this->bot->Event->setFrom($from = $packet->body['by']);
                        $this->bot->Console->notice($packet->body->param."'s privclass has been set to ".$packet->body['pc']." by ".$from, $chat);
                        // a big important fix here
                        $this->info[$ns]['members'][$packet->body->param]['pc'] = $packet->body['pc'];
                    break;

                    case 'kicked':
                        $this->bot->Event->setFrom($from = $packet->body['by']);
                        $this->bot->Console->notice($packet->body->param." has been kicked by ".$from."* ".NORM.$packet->body->body, $chat);
                        unset($this->info[$ns]['members'][$from]);
                    break;

                    case 'admin':
                        switch ($packet->body->param)
                        {
                            case 'create':
                            case 'update':
                                $this->bot->Event->setFrom($from = $packet->body['by']);
                                $this->bot->Console->notice("the privclass ".$packet->body['name']." has been ".$packet->body->param."d by ".$packet->body['by']." with: ".$packet->body['privs'], $chat);
                            break;
                            case 'rename':
                            case 'move':
                                $this->bot->Console->notice("the privclass ".$packet->body['prev']." has been ".$packet->body->param."d to ".$packet->body['name']." by ".$packet->body['by'], $chat);
                            break;
                            case 'show':
                                if ($packet->body['p'] == "privclass")
                                {
                                    $info = new Packet($packet->body->body, ' ');
                                    $this->info[$this->format($packet->param)]['pcinfo'] = $info->args;
                                }
                                if ($packet->body['p'] == "users")
                                {
                                    // WHY DID I DO THIS?
                                    $info = new Packet(str_replace(": ", ":", $packet->body->body), ':');
                                    $this->info[$this->format($packet->param)]['userinfo'] = $info->args;
                                }
                                $this->bot->Console->notice("Got ". $packet->body['p'] ."info for $chat");
                            break;
                        }
                    break;
                }
            break;

            case 'login':
                $this->bot->Console->notice('Login for '.$packet->body['symbol'].$packet->param.": ".$packet['e']);
            break;

            case 'join':
                $this->bot->Event->setNs($ns = $packet->param);
                $packet->param = $this->deform($packet->param);
                $this->bot->Console->notice('Join for '.$packet->param.': '.$packet['e']);
                if ($packet['e'] == 'ok')
                {
                    $this->joined[] = $this->format($packet->param);
                }
            break;

            case 'part':
                $this->bot->Event->setNs($ns = $packet->param);
                $packet->param = $this->deform($packet->param);
                $this->bot->Console->msg('Part for '.$packet->param.': '.$packet['e']);
                if ($packet['e'] == 'ok')
                {
                    $keys = array_flip($this->joined);
                    unset($this->joined[$keys[$this->format($packet->param)]]);
                }
            break;

            case 'send':
                $chat = $this->deform($packet->param);
                $this->bot->Console->warn("Error sending to $chat: ".$packet['e']);
            break;

            case 'kick':
                $chat = $this->deform($packet->param);
                $this->bot->Console->warn('Error kicking '.$packet['u']." in $chat: ".$packet['e']);
            break;

            case 'disconnect':
                if (!$packet['e']) $packet['e'] = ' ';
                $this->bot->Console->warn('You have been disconnected ** ['.$packet['e'].']');
                $this->bot->disconnected = true;
            break;

            case 'ping':
                $this->send("pong\n\0");
                //$this->bot->Console->msg("ping, pong");
            break;

            case 'kicked':
                $this->bot->Event->setNs($ns = $packet->param);
                $this->bot->Console->warn('You were kicked by '.$packet['by'].' * '.NORM.$packet->body, $this->deform($ns));
                $this->join($ns);
            break;

            case 'property':
            {
                $this->bot->Event->setNs($ns = $packet->param);
                switch($packet['p'])
                {
                    case 'title':
                        $this->info[$ns]['title']['body'] = str_replace(chr(0), '', $packet->body);
                        $this->info[$ns]['title']['by'] = $packet['by'];
                        $this->info[$ns]['title']['ts'] = $packet['ts'];
                        $chat = $this->deform($ns);
                        $this->bot->Console->notice("Title for $chat set by ".$packet['by']);
                    break;

                    case 'topic':
                        $this->info[$ns]['topic']['body'] = str_replace(chr(0), '', $packet->body);
                        $this->info[$ns]['topic']['by'] = $packet['by'];
                        $this->info[$ns]['topic']['ts'] = $packet['ts'];
                        $chat = $this->deform($ns);
                        $this->bot->Console->notice("Topic for $chat set by ".$packet['by']);
                    break;

                    case 'members':
                        $members = $packet->body;
                        $this->members_packet[$ns] = $members;
                        $members = explode("\n\n", $this->members_packet[$ns]);
                        foreach($members as $member)
                        {
                            $member = new Packet($member);
                            if (!isset($this->info[$ns]['members'][$member->param]))
                                $this->info[$ns]['members'][$member->param] = $member;
                        }
                        $chat = $this->deform($ns);
                        $this->bot->Console->notice("Got members for $chat");
                    break;

                    case 'privclasses':
                        $privs = new Packet($packet->body, ':');
                        $this->info[$ns]['pc'] = $privs->args;
                        $chat = $this->deform($ns);
                        $this->bot->Console->notice("Got privclasses for $chat");
                    break;

                    case 'info':
                        $conns = explode("conn\n", $packet->body);
                        $info = new Packet(array_shift($conns));
                        foreach($conns as $key => $conn)
                        {
                            $conn = new Packet($conn, '=', false);
                            $conn->body = str_replace("ns ", '', $conn->body);
                            $conn->body = explode("\n\n", $conn->body);
                            $conns[$key] = $conn;
                        }
                        $this->info[$ns]['info'] = $info;
                        $this->info[$ns]['conns'] = $conns;
                        $chat = $this->deform($ns);
                        $this->bot->Console->notice("Got whois info on $chat");
                    break;
                }
            }
        }
        $this->bot->Event->process();
    }

    function getAuthtoken($username, $password) // grab the bot's authtoken
    {
        // first get the validate_key/token values
        if (($socket = @fsockopen("ssl://www.deviantart.com", 443)) == false)
        {
            $this->bot->Console->warn("Couldn't open socket to deviantart.com. Using last retrieved authtoken...");
            return array('token' => $this->bot->pk, 'cookie' => $this->bot->cookie);
        }
        $response = $this->bot->send_headers(
            $socket,
            "www.deviantart.com",
            "/users/login",
            "https://www.deviantart.com/users/loggedin"
        );
        fclose($socket);
        if (!$response)
        {
            $this->bot->Console->warn("Couldn't get form keys; no response from dA. Using last retrieved authtoken...");
            return array('token' => $this->bot->pk, 'cookie' => $this->bot->cookie);
        }
        $cookies = collect_cookies($response);
        preg_match(
            '/name="validate_token" value="(\w+)".+?name="validate_key" value="(\w+)"/Ums',
            $response,
            $matches
        );
        // now log in
        $post = "ref=" . urlencode("https://www.deviantart.com/users/loggedin");
        $post .= "&username=$username&password=" . urlencode($password);
        $post .= "&remember_me=1&validate_token=$matches[1]&validate_key=$matches[2]";

        if (($socket = @fsockopen("ssl://www.deviantart.com", 443)) == false)
        {
            $this->bot->Console->warn("Couldn't open socket to deviantart.com. Using last retrieved authtoken...");
            return array('token' => $this->bot->pk, 'cookie' => $this->bot->cookie);
        }
        $response = send_headers(
            $socket,
            "www.deviantart.com",
            "/users/login",
            "https://www.deviantart.com/users/login",
            $post,
            $cookies
        );
        fclose($socket);
        if (!$response)
        {
            $this->bot->Console->warn("Couldn't perform login; no response from dA. Using last retrieved authtoken...");
            return array('token' => $this->bot->oldpk, 'cookie' => $this->bot->oldcookie);
        }
        if (strpos($response, "Location: http://www.deviantart.com/users/wrong-password") !== false)
        {
            $this->bot->Console->warn("Wrong password!");
            return array();
        }
        $cookies=collect_cookies($response);
        if ($cookies==array())
        {
            $this->bot->Console->warn("Couldn't retrieve the authtoken. Please check your username and password.");
            return array();
        }
        if (($socket = @fsockopen("chat.deviantart.com", 80)) == false)
        {
            $this->bot->Console->warn("Failed to connect to chatrooms to get authtoken.\n");
            return array();
        }
        $response = send_headers(
            $socket,
            "chat.deviantart.com",
            "/chat/Botdom",
            "http://chat.deviantart.com",
            null,
            $cookies
        );
        unset($cookies['features']);
        if (($pos = strpos($response, "dAmn_Login( ")) !== false)
        {
            $response = substr($response, $pos+12);
            return array(
                'token' => substr($response, strpos($response, "\", ")+4, 32),
                'cookie' => $cookies
            );
        }
        else
        {
            $this->bot->Console->warn("Couldn't find authtoken!");
            return array(
                'cookie' => $cookies
            );
        }
    }

    function login($username, $token, $agent="") // log in to dAmn
    {
        $this->s = fsockopen('tcp://chat.deviantart.com', 3900, $this->socket_err, $this->socket_err_str);
        if (!$this->s)
        {
            $this->bot->Console->warn("Failed to connect to dAmn.");
            $this->bot->disconnected = true;
            return 3;
        }
        else
        {
            $this->bot->Console->notice("Connected to dAmn...");
        }

        if (!$agent)
            $agent = $this->bot->name . ' ' . $this->bot->version;
        $data = "dAmnClient 0.2\nagent=$agent\n\0";
        #$channel = $this->deform($this->bot->join[0]);
        #$data="dAmnClient 0.2\nagent=dAmn WebClient 0.7.pre-1 - dAmn Flash plugin 1.2\nbrowser=$agent\nurl=http://chat.deviantart.com/chat/$channel\n\0";

        echo "Data: $data\n";
        $this->bot->Console->notice("Initiating dAmn handshake...");
        $this->send($data);
        $response = $this->recv();
        $this->process($response);
        $this->bot->Console->notice("Logging in...");
        $data = "login $username\npk=$token\n\0";
        $this->send($data);
        $response = $this->recv();
        $this->process($response);
        $response = new Packet($response);
        switch ($response['e'])
        {
            case 'ok':
                return 1;
            break;
            case 'authentication failed':
                return 2;
            break;
            case 'not privileged':
                return 3;
            break;
            default:
                return -1;
            break;
        }
    }

    // TODO: look over this more carefully later in case worthy of refactoring, it's years old
    function input() // metBot's input feature
    {
        stream_set_blocking(STDIN, false);
        $this->bot->ns = ($ns = $this->bot->Event->getNs()) ? $ns : $this->format($this->bot->join[0]);
        $mkprompt = true;
        while ($this->bot->input && !$this->bot->disconnected)
        {
            $channel = "";
            $pkt = $this->packetLoop();
            $this->bot->Event->loop();

            if ($pkt != -1)
            {
                if ($this->bot->Event->getPacket()->cmd != "ping")
                    $mkprompt = true;
            }

            if ($mkprompt==true)
            {
                echo $this->bot->Console->mkprompt();
                $mkprompt = false;
            }

            $input_text = $this->bot->Console->get("", true);

            if (strlen($input_text) > 0)
            {
                $mkprompt = true;
                // for triggering commands with multiple triggers
                $ranCommand = false;
                if ($input_text[0] == '/')
                {
                    if (strpos($input_text, ' ') !== false)
                    {
                        $command = explode(' ', $input_text);
                        $command = $command[0];
                        $args = substr($input_text, strpos($input_text, ' ')+1);
                    }
                    else
                    {
                        $command = $input_text;
                        $args = '';
                    }
                    switch ($command)
                    {
                        case "/quit":
                            $this->bot->Console->notice("Quitting input...");
                            stream_set_blocking(STDIN, true);
                            $this->bot->input = false;
                        break;
                        case "/set":
                            $channel = $this->format($args);
                            $chans = array_map('strtolower', $this->joined);
                            if (in_array(strtolower($channel), $chans))
                            {
                                $this->bot->ns = trim($channel);
                                $this->bot->Console->notice("Channel is now set to ".GREEN_BOLD.$this->deform($this->bot->ns));
                            }
                            else $this->bot->Console->notice("You aren't in that channel.");
                        break;
                        case "/e": // TODO: make this use the same method of evaluating as !e does
                            $this->args = substr($input_text, 2);
                            $return_value = eval($this->args);
                            if ($return_value != NULL) echo "Code returned:\n\n" . print_r($return_value, true) . "\n";
                        break;
                        case "/clear": // this is pretty ridiculous, might want to remove it
                            if (PHP_SHLIB_SUFFIX == "dll")
                            {
                                system('cls');
                            }
                            elseif (PHP_SHLIB_SUFFIX == "so")
                            {
                                system('clear');
                            }
                        break;
                        case "/title":
                            $channel = substr($input_text, strlen("/title "));
                            $c = strlen($channel) > 0 ? $channel : $this->bot->ns;
                            $title = $this->query('title', $c);
                            $this->bot->Console->msg("Title is\n". $title ."\n\nset by ". $this->query('title-by', $c) ." on ".date("g:i:s a", $this->query('title-ts',$c)) ."\n");
                        break;
                        case "/topic":
                            $channel = substr($input_text, strlen("/topic "));
                            $c = strlen($channel) > 0 ? $channel : $this->bot->ns;
                            $topic = $this->query('topic', $c);
                            $this->bot->Console->msg("Topic is:\n". $topic ."\n\nset by ". $this->query('topic-by', $c) ." on ".date("g:i:s a", $this->query('topic-ts',$c)) ."\n");
                        break;
                        case "/members":
                            $channel = substr($input_text, strlen("/members "));
                            $c = strlen($channel) > 0 ? $channel : $this->bot->ns;
                            $members = join(", ", $this->query("members-list", $c));
                            $this->bot->Console->msg("Members in ". $this->deform($c) .":\n$members\n");
                        break;
                        /*case "/attach":
                            $this->bot->Console->notice(($this->bot->attach?"Unattaching prompt from":"Attaching prompt to")." the bottom of the screen...");
                            $this->bot->attach = !$this->bot->attach;
                        break;*/
                        case "/me":
                            if (strlen($input_text) > 4)
                            {
                                $this->bot->Console->msg("Sending message...");
                                $this->say($input_text, $this->bot->ns);
                            }
                        break;
                        case '/multi':
                            $message = '';
                            $m = '';
                            stream_set_blocking(STDIN, true);
                            while ($m != "/end\n")
                            {
                                $message .= $m;
                                $m = $this->bot->Console->get('> ', '')."\n";
                            }
                            // dAmn is latin1
                            $message = mb_convert_encoding($message, 'iso-8859-1');
                            stream_set_blocking(STDIN, false);
                            $this->say($message, $this->bot->ns);
                        break;
                        default:
                            $this->bot->Console->warn("Unknown command \"$command\"");
                        break;
                    }
                }
                else
                {
                    // to function with multiple triggers properly
                    foreach (array_keys($this->bot->trigger->triggers) as $t)
                    {
                        if (substr($input_text, 0, strlen($t)) == $t)
                        {
                            $command = substr($input_text, strlen($t));
                            $this->bot->Console->notice("Running command \"$command\"...");
                            $this->bot->Event->setNs($this->bot->ns);
                            $this->bot->Commands->process($this->bot->admin, $command);
                            $ranCommand = true;
                            break;
                        }
                    }

                    if (!$ranCommand && $input_text != "\n")
                    {
                        $this->bot->Console->notice("Sending message...");
                        // dAmn is latin1
                        $input_text = mb_convert_encoding($input_text, 'iso-8859-1');
                        $this->say($input_text, $this->bot->ns);
                    }

                    $ranCommand = false;

                }
            }

            usleep(10000);
        }
    }

    // turn formatted namespaces into #chat or @user (private chats)
    function deform($chat)
    {
        if (($pos = strpos($chat, ':')) !== false)
        {
            list($ns, $name) = explode(':', $chat, 2);
            switch($ns)
            {
            case 'chat':
                return '#' . $name;
            case 'pchat':
                $names = explode(':', $name); // assume there are two
                if (($i = array_search($this->bot->username, $names)) !== false)
                    return '@' . $names[$i ? 0 : 1];
                else return $chat;
            case 'login':
                return $chat;
            }
        }
        else if ($chat[0] == '#' || $chat[0] == '@')
            return $chat;
        else return '#' . $chat;
    }

    // does the opposite; turns a deformed channel into a formatted one
    function format($chat)
    {
        $name = substr($chat, 1);
        if (($pos = strpos($chat, ':')) !== false && in_array(substr($chat, 0, $pos), array('chat','pchat','login')))
            return $chat;
        elseif (@$chat[0] == '@')
            return 'pchat:' . min($this->bot->username, $name) . ':' . max($this->bot->username, $name);
        else
            return 'chat:' . $chat;
    }
}
?>
