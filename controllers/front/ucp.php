<?php

class UcpWellKnownUcpModuleFrontController extends ModuleFrontController
{
    public $display_header = false;
    public $display_footer = false;

    public function initContent()
    {
        header('Content-Type: application/json');

        echo json_encode([
            "version" => "1.0",
            "capabilities" => ["product_read"]
        ]);

        exit;
    }
}