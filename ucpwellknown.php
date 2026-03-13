<?php

if (!defined('_PS_VERSION_')) {
    exit;
}

class UcpWellKnown extends Module
{
    public function __construct()
    {
        $this->name = 'ucpwellknown';
        $this->tab = 'front_office_features';
        $this->version = '1.0.0';
        $this->author = 'Basilio';
        $this->need_instance = 0;

        parent::__construct();

        $this->displayName = 'UCP well-known endpoint';
        $this->description = 'Expose /.well-known/ucp endpoint';
    }

    public function install()
    {
        return parent::install();
    }

    public function uninstall()
    {
        return parent::uninstall();
    }
}