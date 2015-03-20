<?php
namespace Addvert;

class Logger {
    private $fp;

    public function __construct($fname) {
        $this->fp = fopen($fname, 'a+');
    }

    public function log($msg) {
        $now = date('d-m-Y H:i:s');
        $ip = $_SERVER['REMOTE_ADDR'];
        fwrite($this->fp, "$now § $ip § $msg\n");
        fwrite($this->fp, "-------------------------------------------------\n");
        return $this;
    }

    public function __destruct() {
        if( is_resource($this->fp) )
            fclose($this->fp);
    }
}
