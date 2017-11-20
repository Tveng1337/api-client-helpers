<?php

namespace Wizz\ApiClientHelpers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Cache;

class ACHController extends Controller
{
    /*

    Setting up our error message for the client.

    */
    public function __construct()
    {
        $one = "Sorry, looks like something went wrong. ";

        $two = (env('support_email')) ? "Please contact us at <a href='mailto:".env('support_email')."'>".env('support_email')."</a>" : "Please contact us via email";

        $three = ' for further assistance.';

        $this->error_message = $one.$two.$three;
        $this->security_code = config('api_configs.security_code');
        $this->redirect_code = config('api_configs.not_found_redirect_code', 301);
        $this->redirect_mode = config('api_configs.not_found_redirect_mode');

        $this->version = "1.2";

    }

    /*

    Little helper for our check function.

    */
    protected function is_ok($func)
    {
        return ($this->$func()) ? 'OK' : 'OFF';
    }

    /*

    Validating that all our configs necessary for frontend repo are in place.

    */
    protected function validate_frontend_config()
    {
        if(! env('frontend_repo_url')) return false;

        if(substr(env('frontend_repo_url'), -1) != '/') return false;

        return true;
    }

    /*

    Validating that all our configs necessary for redirect are in place.

    */
    protected function validate_redirect_config()
    {
        if(! env('secret_url')) return false;

        return true;
    }

    /*

    Function to see if we should be caching response from frontend repo.

    If $slug is passed, it will also check whether this $slug is already in cache;

    */
    protected function should_we_cache($ck = false)
    {
        if(env('use_cache_frontend') === false) return false;

        if(request()->input('cache') === 'false') return false;

        if(!app()->environment('production')) return false;
        if($ck && !Cache::has($ck)) return false;

        return true;
    }

    protected function CK($slug) //CK = Cache Key
    {
        $slug = request()->fullUrl(); //request()->getHttpHost().$slug;
        $ua = strtolower(request()->header('User-Agent'));
        $slug = $ua && strrpos($ua, 'msie') > -1 ? "_ie_".$slug : $slug;
        return md5($slug);
    }

    public function splitUrlIntoSegments($url)
    {
        $url_without_query_string = explode('?', $url)[0];
        return array_values(array_filter(explode('/', $url_without_query_string), function ($var) {
            return ($var) ? true : false;
        }));
    }

    /*

    The actual function for handling frontend repo requests.

    */
    public function frontend_repo($slug, Request $req)
    {
        $input = request()->all();
        $input['domain'] = request()->root();
        $conf = $this->from_config();

        $multilingual = $this->checkMultilingual($req);
        if ($multilingual['redirect'])
        {
            return redirect($multilingual['redirect_path']);
        }
        $query = $multilingual['query'];

        $this->trackingHits();

        $input = array_merge($input, $conf);
        if(array_key_exists('page', $input)) unset($input['page']);
        
        session(['addition' => $input]);
        if(!$this->validate_frontend_config()) return $this->error_message;
        
        $ck = $this->CK($slug);
        if ($this->should_we_cache($ck)) {
            $page = Cache::get($ck);
            return insertToken($page);
        }

        try {
            $front = $conf['frontend_repo_url'];
            
            if(config('api_configs.multidomain_mode_dev') || config('api_configs.multidomain_mode')) {
                $slug = !strlen($slug) ? $slug : '/';
            }

            $url = ($slug == '/') ? $front : $front.$slug;
            $domain = $req->url();

            $url = $url . '?' . http_build_query(array_merge($req->all(), $query));
            $page = file_get_contents($url, false, stream_context_create(arrContextOptions()));

            $http_code = array_get($http_response_header, 0, 'HTTP/1.1 200 OK');

            if(strpos($http_code, '238') > -1)
            {
                // code 238 is used for our internal communication between frontend repo and client site,
                // so that we do not ignore errors (410 is an error);
                if($this->redirect_mode === "view")
                {
                    return response(view('api-client-helpers::not_found'), $this->redirect_code);
                }
                else //if($this->redirect_mode === "http")
                { // changed this to else, so that we use http redirect by default even if nothing is specified
                    return redirect()->to('/', $this->redirect_code);
                }
            }

            if ($this->should_we_cache()) Cache::put($ck, $page, $conf['cache_frontend_for']);
            return insertToken($page);

        }
        catch (Exception $e)
        {
            // \Log::info($e);
            return $this->error_message;
        }
    }

    /*

    Function to clear all cache (e.g. when new frontend repo code is pushed).

    */
    public function clear_cache()
    {
        if(request()->input('code') !== $this->security_code) return ['result' => 'no access'];;
        try {
            \Artisan::call('cache:clear');
            return ['result' => 'success'];
        } catch (Exception $e) {
            // \Log::info($e);
            return ['result' => 'error'];
        }
    }

    protected $file_types = [
        'image/jpeg',
        'image/png',
        'image/tiff',
        'text/csv',
        'audio/basic',
        'audio/L24',
        'audio/mp4',
        'audio/aac',
        'audio/mpeg',
        'audio/ogg',
        'audio/vorbis',
        'audio/x-ms-wma',
        'audio/x-ms-wax',
        'audio/vnd.rn-realaudio',
        'audio/vnd.wave',
        'audio/webm: WebM',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/pdf',
        'application/msword',
        'application/vnd.ms-excel',
        'application/vnd.openxmlformats-officedocument.presentationml.presentation',
        'application/vnd.ms-powerpoint',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'text/plain',
        'text/plain; charset=UTF-8',

    ];

    protected $view_types = [
        'text/html;charset=UTF-8',
        'text/html; charset=UTF-8',
        'text/html',
        'text/html;charset=ISO-8859-1',
        'text/html; charset=ISO-8859-1',
    ];
    /*

    Prozy function for api by @wizz.

    */
    public function proxy($slug = '/')
    {
        $method = array_get($_SERVER, 'REQUEST_METHOD');
        $res = apiRequestProxy(request());
        $data = explode("\r\n\r\n", $res);
        $data2 = http_parse_headers($res);

        if(preg_match('/^HTTP\/\d\.\d\s+(301|302)/',$data[0]))
        {
            $headers = array_get(http_parse_headers($data[0]), 0);
            return redirect()
                ->to(array_get($headers, 'location'))
                ->header('referer', 'https://api.speedy.company');
        }
        $cookies = setCookiesFromCurlResponse($res);
        $headers = (count($data2) == 3) ? $data2[1] : $data2[0];
        $res = (count($data) == 3) ? $data[2] : $data[1];

        $content_type = array_get($headers, 'content-type');
        switch ($content_type) {
            case 'application/json':
                return response()->json(json_decode($res));
                break;
            case in_array($content_type, $this->view_types):
                return $res;
                break;
            case 'text/plain':
                return $res;
                break;
            case in_array($content_type, $this->file_types):
                $shit = array_get($headers, 'cache-disposition', array_get($headers, 'content-disposition'));
                $path = getPathFromHeaderOrRoute($shit, $slug);
                file_put_contents($path, $res);
                return response()->download($path);
                break;
            case "application/xml":
                $xml = new \SimpleXMLElement($res);
                return $xml->asXML();
                break;
            case 'text/plain; charset=UTF-8':
                $filename = getFilenameFromHeader(array_get($headers, 'content-disposition'));
                if ($filename == 'robots.txt') file_put_contents(public_path().'/robots.txt',$res);
                break;
            default:
                $shit = array_get($headers, 'cache-disposition', array_get($headers, 'content-disposition'));
                $path = getPathFromHeaderOrRoute($shit, $slug);
                file_put_contents($path, $res);
                return response()->download($path);
                break;
        }
        if (strpos('q'.$res, 'Whoops,')) {
            if (! json_decode($res)) {

                return response()->json([
                    'status' => 400,
                    'errors' => [$this->error_message],
                    'alerts' => []
                ]);
            }
        }
    }

    /*

    Our redirector to api functionality.

    */
    public function redirect($slug, Request $request)
    {

        if(!$this->validate_redirect_config()) return $this->error_message;

        return redirect()->to(env('secret_url').'/'.$slug.'?'.http_build_query($request->all()));
    }

    /*

    Little helper to see the state of affairs in browser.

    */
    public function check()
    {
        if(request()->input('code') !== $this->security_code) return;

        return [
            'frontend_repo' => $this->is_ok('validate_frontend_config'),
            'redirect' => $this->is_ok('validate_redirect_config'),
            'caching' => $this->is_ok('should_we_cache'),
            'version' => $this->version
        ];
    }

    public function from_config() 
    {
        $uri_host = explode('/', request()->getRequestUri());
        $host = $uri_host[1];

        if (config('api_configs.multidomain_mode_dev') && $uri_host[1]) {
            $dom = config('api_configs.change_project.'.$host);
        } else {
            $has_multidomain = config('api_configs.multidomain_mode') && app()->environment('local'); 
            $host = !$has_multidomain ? request()->getHttpHost() : $host;
            $dom = preg_replace('|[^\d\w ]+|i', '-', $host);
        }
        $keys = [
            'client_id',
            'frontend_repo_url',
            'main_language',
            'multilingualSites',
            'languages',
            'tracking_hits',
            'cache_frontend_for'
        ];
        $domains_from_config = config('api_configs.domains') ? config('api_configs.domains') : [];
        $has_domains = array_key_exists($dom, $domains_from_config); 
        $str = $has_domains ? 'api_configs.domains.'.$dom.'.' : 'api_configs.';
        
        foreach ($keys as $key) {
            $conf[$key] = config($str.$key);
        }
        return $conf;
    }

    public function trackingHits()
    {
        if (!config('api_configs.tracking_hits'))
        {
            return;
        }
        //store hit and write hit_id in cookie
        $hitsQuery = [
            'rt' => array_get($input, 'rt', null),
            'app_id' => config('api_configs.client_id')
        ];
        $query = config('api_configs.secret_url') . '/hits/?' . http_build_query($hitsQuery);
        $res = file_get_contents($query);
        $res = json_decode($res)->data;
        \Cookie::queue('hit_id', $res->id, time()+60*60*24*30, '/');
    }

    public function checkMultilingual($request)
    {
        if (!config('api_configs.is_multilingual'))
        {
            return [
                'redirect' => false,
                'query' => []
            ];
        }

        //getting language from url
        $url_segments = $this->splitUrlIntoSegments($request->path());
        $main_language = env('MAIN_LANGUAGE') ? env('MAIN_LANGUAGE') : 'en';
        $requested_language = array_get($url_segments, 0, $main_language);
        $requested_language = in_array($requested_language, config('api_configs.languages')) ? $requested_language : $main_language;

        //if user tries to change language via switcher rewrite user_language cookie
        if ($request->input('change_lang'))
        {
            setcookie('user_language', $request->input('change_lang'), time() + 60 * 30, '/');
            $_COOKIE['user_language'] = $request->input('change_lang');
            if ($requested_language !== $request->input('change_lang'))
            {
                $redirect_path = $request->input('change_lang') == $main_language ? '/' : '/' . $request->input('change_lang') . '/ ';
                return [
                    'redirect' => true,
                    'redirect_path' => $redirect_path
                ];
            }
        }

        //rewriting user_language on home page
        if ($request->get('l') == $main_language)
        {
            setcookie('user_language', $main_language, time() + 60 * 30, '/');
        }

        //if user_language cookie not found getting it from Accept-Language header
        if (!array_key_exists("user_language", $_COOKIE))
        {
            $user_language = substr(locale_accept_from_http($request->header('accept-language')), 0, 2);
            $user_language = in_array($user_language, config('api_configs.languages')) ? $user_language : $main_language;
            setcookie('user_language', $user_language, time() + 60 * 30, '/');
            $_COOKIE['user_language'] = $user_language;
        }

        //if user_language differs from requested language then redirecting on user_language page
        if ($request->path() == '/' && $request->get('l') !== $main_language && $requested_language !== $_COOKIE['user_language'])
        {
            return [
                'redirect' => true,
                'redirect_path' => $_COOKIE['user_language'] == $main_language ? '/' : '/' . $_COOKIE['user_language'] . '/ '
            ];
        }

        return [
            'redirect' => false,
            'query' => [
                'lang' => $requested_language,
                'main_language' => env('MAIN_LANGUAGE')
            ]
        ];
    }

}