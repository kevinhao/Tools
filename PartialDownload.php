<?php

date_default_timezone_set('PRC');

class PartialDownload {
    private static $header = array(
        'Accept-Encoding: identity;q=1, *;q=0',
        'Accept-Language: zh-CN,zh;q=0.8,ja;q=0.6,en;q=0.4,zh-TW;q=0.2,de;q=0.2',
        'Upgrade-Insecure-Requests: 1',
        'User-Agent: Opera/9.63 (Macintosh; Intel Mac OS X; U; en) Presto/2.1.1',
        'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,*/*;q=0.8',
        'Connection: keep-alive',
        'Accept: */*',
    );
    private $begin;
    private $descFile;
    private $buffer;
    private $url;

    private function __construct($url, $desc) {
        $this->begin = 0;
        $this->descFile = $desc;
        $this->url = $url;
    }


    private function beginDownload() {
        $curl = curl_init($this->url);
        curl_setopt($curl, CURLOPT_CUSTOMREQUEST, 'HEAD');
        curl_setopt($curl, CURLOPT_HTTPHEADER, self::$header);
        curl_setopt($curl, CURLOPT_HEADER, true);
        curl_setopt($curl, CURLINFO_HEADER_OUT, true);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_exec($curl);
        $retHeader = curl_getinfo($curl);
        curl_close($curl);
        switch(@$retHeader['http_code']) {
            case 404 : {
                return $this->beginDownload($this->url);
            } break;
            case 302 : {
                $this->url = $retHeader['redirect_url'];
                return $this->_download($this->url, true);
            } break;
            default : {
                return false;
            } break;
        }
    }

    private function _download($url) {
        echo $url, "\n";
        $curl = curl_init($url);
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_RANGE, $this->begin."-");
        curl_setopt($curl, CURLOPT_BINARYTRANSFER, true);
        curl_setopt($curl, CURLOPT_HEADER, false);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLINFO_HEADER_OUT, true);
        $_buffer = curl_exec($curl);
        $retHeader = curl_getinfo($curl);
        curl_close($curl);
        if ($this->descFile) {
            if (!file_exists(dirname($this->descFile))) {
                @mkdir(dirname($this->descFile), 0777, true);
            }
            file_put_contents($this->descFile, $_buffer, FILE_APPEND);
            flush();
            $this->begin += strlen($_buffer) ;
        } else {
            $this->buffer = empty($this->buffer) ? $_buffer :  $this->buffer . $_buffer;
            $this->begin = strlen($this->buffer);
        }
        $retHttpCode = $retHeader['http_code'];
        unset($_buffer);
        switch($retHttpCode) {
            case 200 : {
                print_r($retHttpCode);
                return true;
            } break;
            case 206 : {
                return $this->_download($this->url, true);
            } break;
            case 302 : {
                $this->url = $retHeader['redirect_url'];
                return $this->_download($this->url, true);
            } break;
            case 403 : {
                return $this->beginDownload();
            } break;
            case 404 : {
                return $this->beginDownload();
            } break;
            default : {
                $downloadContentLength = $retHeader['download_content_length'];
                if ($downloadContentLength == 0) {
                    return true;
                } else {
                    return false;
                }
            }
        }
    }


    public static function download($url,  $desc = null) {
        $handler = new PartialDownload($url, $desc);
        return $handler->beginDownload();
    }
}