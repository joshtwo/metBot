<?php
// first new core class in ages
// we're gonna just use the info for Arsenic
class OAuth
{
    public $clientId = 475;
    public $clientSecret = "633ddedd3d10e0b9c7b93e0530c3c208";
    public $accessToken;
    public $refreshToken;
    public $code;

    private $bot;

    function __construct($bot)
    {
        $this->bot = $bot;
    }

    // generate an OAuth URL
    function getPayload($attrs)
    {
        $payload = array();
        foreach ($attrs as $key => $val)
        {
            if ($key == 'client_id')
                $val = $this->clientId;
            if ($key == 'client_secret')
                $val = $this->clientSecret;
            if ($key == 'access_token')
                $val = $this->accessToken;
            if ($key == 'code')
                $val = $this->code;
            if ($key == 'refresh_token')
                $val = $this->refreshToken;
            $payload[]= "$key=" . urlencode($val);
        }
        $payload = join('&', $payload);
        return $payload;
    }

    // sets up a server to wait for the authorization code
    function getCode($scope=null)
    {
        // so fucking 1337 it's 1338
        $s = stream_socket_server('tcp://0.0.0.0:1338', $errno, $errstr);
        if (!$s)
        {
            echo "Error $errno: $errstr\n";
            return;
        }
        // first, do this for the redirect
        $c = stream_socket_accept($s, -1);
        // we don't care about the response
        $response = fread($c, 8192);
        $response = '';

        $attrs = array(
            'client_id' => '',
            'redirect_uri' => 'http://localhost:1338',
            'response_type' => 'code'
        );
        if ($scope)
            $attrs['scope'] = $scope;

        // redirects to the auth url
        $reply = "HTTP/1.1 302 Found\r\nLocation: https://www.deviantart.com/oauth2/authorize?" . $this->getPayload($attrs) . "\r\n\r\n";
        fwrite($c, $reply);
        fclose($c);

        // now wait for the code to come back
        $response = '';
        // first, do this for the redirect
        $c = stream_socket_accept($s, -1);
        // lazy, but good enough
        $response = fread($c, 8192);
        echo $response;

        // tell them we're all good
        $body = "<!DOCTYPE html><html><head><title>Thanks!</title></head><style>h1 {text-align: center;font-family: 'DejaVu Sans', Verdana, sans-serif;}</style><body><h1>Thanks! We've just authorized the bot. Check back in the console window.</h1></body></html>";
        $date = new DateTime("now", new DateTimeZone("UTC"));
        $reply = array(
            "HTTP/1.1 200 OK",
            "Date: " . str_replace("UTC", "GMT", $date->format(DateTime::RFC822)),
            "Server: metBot",
            "Content-Type: text/html; charset=iso-8859-1",
            "Connection: close",
            "Content-Length: " . strlen($body),
            "",
            $body
        );
        $reply = join("\r\n", $reply);
        fwrite($c, $reply);
        fclose($c);
        fclose($s);

        // extract the token
        $start = strpos($response, "GET /?code=") + strlen("GET /?code=");
        $end = strpos($response, "&state=");
            if ($end === false)
                $end = strpos($response, " HTTP/1.1\r\n");
        if ($start !== false && $end !== false)
        {
            $this->code = substr($response, $start, $end - $start);
            $this->response = $response;
            return true;
        }
        else
        {
            return false;
        }
    }

    function getTokens()
    {
        $response = $this->call('/oauth2/token', array(
            'client_id' => '',
            'client_secret' => '',
            'code' => '',
            'grant_type' => 'authorization_code',
            'redirect_uri' => 'http://localhost:1338'
        ));
        if (!@$response->error)
        {
            $this->accessToken = $response->access_token;
            $this->refreshToken = $response->refresh_token;
            $this->bot->saveConfig();
            return true;
        }
        else
            return false;
    }

    // any OAuth call, without a path prefix
    function call($path, $attrs=array(), $post=false)
    {
        $socket = fsockopen('ssl://www.deviantart.com', 443);
        $payload = $this->getPayload($attrs);
        if (!$post && $payload)
            $path .= "?$payload";
        if (_debug('OAUTH_CALLS'))
            echo "Path: $path\nPayload: $payload\n";
        $response = $this->bot->send_headers(
            $socket,
            "www.deviantart.com",
            $path,
            null,
            $post ? $payload : null
        );
        if (_debug('OAUTH_CALLS'))
            echo "> $response\n\n";
        if (($pos = strpos($response, "\r\n\r\n")) !== false)
        {
            $body = substr($response, $pos + 4);
            $json = json_decode($body);
            if ($json == null && json_last_error() != JSON_ERROR_NONE)
                $this->bot->Console->warn("Error trying to decode the JSON: ".json_last_error_msg());
            return $json;
        }
        else
            return false;
    }

    // a call to the API; includes the /api/v1/oauth2/ prefix and access_token
    function apiCall($path, $attrs=array(), $post=false)
    {
        if (!$this->accessToken)
            return false;
        $path = "/api/v1/oauth2/$path";
        $attrs['access_token'] = '';
        $response = $this->call($path, $attrs, $post);
        if (isset($response->error) && $response->error == 'invalid_token')
        {
            echo "OAuth token invalid. Retrieving new one...\n";
            if ($this->refreshTokens())
                return $this->call($path, $attrs, $post);
            else
            {
                echo "Call failed due to token refresh failing.\n";
                return false;
            }
        }
        else
            return $response;
    }

    // uses the refresh token to get a new access token/refresh token pair
    function refreshTokens()
    {
        echo "Refreshing token...\n";
        $response = $this->call('/oauth2/token', array('client_id' => '', 'client_secret' => '', 'refresh_token' => '', 'grant_type' => 'refresh_token'));
        echo "Call completed.\n";
        if ($response && !@$response->error)
        {
            $this->accessToken = $response->access_token;
            $this->refreshToken = $response->refresh_token;
            $this->bot->saveConfig();
            return true;
        }
        else
        {
            if (@$response->error)
                $this->bot->Console->warn("Error refreshing token: {$response->error}");
            return false;
        }
    }
}
