<?php

class UcpWellKnownUcpModuleFrontController extends ModuleFrontController
{
    public $display_header = false;
    public $display_footer = false;

    public function initContent()
    {
        header('Content-Type: application/json');

        $json = [
            "ucp" => [
                "version" => "2026-03-13",
                "supported_versions" => [
                    "2026-03-13" => "http://localhost/prestashop/.well-known/ucp"
                ],
                "services" => [
                    "dev.ucp.shopping" => [
                        [
                            "version" => "2026-03-13",
                            "spec" => "https://ucp.dev/specification/overview/",
                            "transport" => "mcp",
                            "endpoint" => "http://localhost/prestashop/api/ucp/mcp",
                            "schema" => "https://ucp.dev/services/shopping/openrpc.json"
                        ]
                    ]
                ],
                "capabilities" => [
                    "dev.ucp.shopping.checkout" => [
                        [
                            "version" => "2026-03-13",
                            "spec" => "https://ucp.dev/specification/checkout",
                            "schema" => "https://ucp.dev/schemas/shopping/checkout.json"
                        ]
                    ]
                ],
                "payment_handlers" => [
                    "com.google.pay" => [
                        [
                            "id" => "gpay",
                            "version" => "2026-03-13",
                            "spec" => "https://pay.google.com/gp/p/ucp/2026-03-13/",
                            "schema" => "https://pay.google.com/gp/p/ucp/2026-03-13/schemas/config.json",
                            "config" => [
                                "api_version" => 2,
                                "merchant_info" => [
                                    "merchant_name" => "My Shop",
                                    "merchant_id" => "123456789",
                                    "merchant_origin" => "localhost"
                                ]
                            ]
                        ]
                    ]
                ]
            ]
        ];

        echo json_encode($json, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        exit;
    }
}