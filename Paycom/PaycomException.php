<?php
namespace Paycom;

class PaycomException extends \Exception
{
    const ERROR_INTERNAL_SYSTEM = -32400;
    const ERROR_METHOD_POST = -32300;
    const ERROR_INSUFFICIENT_PRIVILEGE = -32504;
    const ERROR_INVALID_JSON_RPC_OBJECT = -32600;
    const ERROR_METHOD_NOT_FOUND = -32601;
    const REQUIRED_PARAMS_NOT_FOUND = -32602;
    const ERROR_INVALID_AMOUNT = 413;
    const ERROR_EXCEED_MAX_AMOUNT = 413;
    const TRANSACTION_ALREADY_EXISTS = 201;
    const TRANSACTION_ALREADY_CANCELLED = 202;
    const ERROR_INVALID_ACCOUNT = -31050;
    const ERROR_COULD_NOT_CANCEL = -31007;
    const ERROR_COULD_NOT_PERFORM = -31008;
    const ERROR_INCOMPLETE = -31099;
    const ERROR_BLOCKED = -31050;
    const CLIENT_NOT_FOUND = 302;
    const SERVICE_NOT_FOUND = 305;
    const INVALID_DATETIME = 414;

    const ERROR_TRANSACTION_NOT_FOUND = 203;
    
    public $request_id;
    public $error;
    public $data;

    /**
     * PaycomException constructor.
     * @param int $request_id id of the request.
     * @param string $message error message.
     * @param int $code error code.
     * @param string|null $data parameter name, that resulted to this error.
     */
    
    public function __construct($request_id, $message, $code, $data = null)
    {
        $this->request_id = $request_id;
        $this->message = $message;
        $this->code = $code;
        $this->data = $data;

        // prepare error data
        $this->error = ['code' => $this->code];

        if ($this->message) {
            $this->error['message'] = $this->message;
        }

        // if ($this->data) {
        //     $this->error['data'] = $this->data;
        // }
    }

    public function send()
    {

        // ERROR_INSUFFICIENT_PRIVILEGE → 401
        if ($this->code === self::ERROR_INSUFFICIENT_PRIVILEGE || $this->code === self::ERROR_INVALID_JSON_RPC_OBJECT) {
            http_response_code(401);
        } else {
            http_response_code(200);
        }

        header('Content-Type: application/json; charset=UTF-8');

        // create response
        $response['jsonrpc'] = "2.0";
        $response['id'] = $this->request_id;
        // $response['result'] = null;
        $response['error'] = $this->error;

        echo json_encode($response);
    }

    public static function message($ru, $uz = '', $en = '')
    {
        return $ru;
    }
}