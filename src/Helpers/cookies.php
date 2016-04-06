<?php 
use Illuminate\Http\Request;


function parse_cookies($header) {
	
	$parts = explode(";",$header);
		$cookie = [];
	foreach ($parts as $i => $part) {
		$cook = explode("=",$part);
		if ($i == 0) {
			$cookie['name'] = trim($cook[0]);
			$cookie['value'] = $cook[1];
		} else
		{
			$cookie[trim($cook[0])] = (array_key_exists(1, $cook)) ? $cook[1] : '';
		}
	}
	return $cookie;
}

function getCookieStringFromRequest(Request $request)
{
	$cookies = $request->cookie();
 	$cookieArray = array();
	foreach ($cookies as $cookieName => $cookieValue) {
	     $cookieArray[] = "{$cookieName}={$cookieValue}";
	}
 	return implode('; ', $cookieArray);
}

function getCookieFromCurlResponce($response)
{
     list($headers, $response) = explode("\r\n\r\n",$response,2);
     preg_match_all('/Set-Cookie: (.*)\b/', $headers, $cookies);
     $cookies = $cookies[1];
}


function setCookiesFromCurlResponse($headers)
{
	preg_match_all('/Set-Cookie: (.*)\b/', $headers, $cookies);
	$cooks = [];
	$cookies = $cookies[1];
	foreach($cookies as $rawCookie) {
		$cookie = parse_cookies($rawCookie);
		$cooks[] = $cookie;
		$minutes = new \Carbon\Carbon($cookie['expires']);
		$minutes = $minutes->diffInMinutes(\Carbon\Carbon::now());
		$cookie = cookie($cookie['name'], $cookie['value'], $minutes, $cookie['path']);
		Cookie::queue($cookie);
	}
	return $cooks;
}

function http_parse_headers($string) 
{
    $headers = array();
    $content = '';
    $str = strtok($string, "\n");
    $h = null;
    while ($str !== false) {
        if ($h and trim($str) === '') {                
            $h = false;
            continue;
        }
        if ($h !== false and false !== strpos($str, ':')) {
            $h = true;
            list($headername, $headervalue) = explode(':', trim($str), 2);
            $headername = strtolower($headername);
            $headervalue = ltrim($headervalue);
            if (isset($headers[$headername])) 
                $headers[$headername] .= ',' . $headervalue;
            else 
                $headers[$headername] = $headervalue;
        }
        if ($h === false) {
            $content .= $str."\n";
        }
        $str = strtok("\n");
    }
    return array($headers, trim($content));
}

