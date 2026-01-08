<?php
namespace Paycom;

class Merchant
{
    public $config;

    public function __construct($config)
    {
        $this->config = $config;

        // // read key from key file
        // if ($this->config['keyFile']) {
        //     $this->config['key'] = file_get_contents($this->config['keyFile']);
        // }
    }

    public function Authorize($request_id)
    {

        // 1️⃣ HTTP METHOD tekshirish
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            try {
                throw new PaycomException(
                    $request_id,
                    'Method not allowed. POST required.',
                    PaycomException::ERROR_METHOD_POST
                );
            } catch (PaycomException $e) {
                $e->send();
                exit();
            }
        }

        $headers = apache_request_headers();

        if (!$headers || !isset($headers['Authorization']) ||
            !preg_match('/^\s*Basic\s+(\S+)\s*$/i', $headers['Authorization'], $matches) ||
            base64_decode($matches[1]) != $this->config['login'] . ":" . $this->config['password']
        ) {
            try
            {
                throw new PaycomException(
                    $request_id,
                    'Insufficient privilege to perform this method.',
                    PaycomException::ERROR_INSUFFICIENT_PRIVILEGE
                );
            }
            catch (PaycomException $e)
            {
                $e->send();
                exit();
            }
           
        }

         // Normalize header keys (Content-Type vs content-type)
        $normalized = [];
        foreach ($headers as $key => $value) {
            $normalized[strtolower($key)] = $value;
        }

        // 1️⃣ Content-Type va Accept tekshirish

        // if (
        //     !isset($normalized['content-type']) ||
        //     stripos($normalized['content-type'], 'application/json') === false ||
        //     !isset($normalized['accept']) ||
        //     stripos($normalized['accept'], 'application/json') === false
        // )

        if (
            !isset($normalized['content-type']) ||
            stripos($normalized['content-type'], 'application/json') === false) 
        {
            try {
                throw new PaycomException(
                    $request_id,
                    'Invalid request format. JSON required.',
                    PaycomException::ERROR_INVALID_JSON_RPC_OBJECT
                );
            } catch (PaycomException $e) {
                $e->send();
                exit();
            }
        }

        return true;
    }
}