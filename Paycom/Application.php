<?php

namespace Paycom;

class Application
{
    public $config;
    public $request;
    public $response;
    public $merchant;

    /**
     * Application constructor.
     * @param array $config configuration array with <em>merchant_id</em>, <em>login</em>, <em>keyFile</em> keys.
     */
    public function __construct($config)
    {
        $this->config = $config;
        $this->request = new Request();
        $this->response = new Response($this->request);
        $this->merchant = new Merchant($this->config);
    }

    /**
     * Authorizes session and handles requests.
     */
    public function run()
    {
        // authorize session
        $this->merchant->Authorize($this->request->id);

        // handle request
        try {
            switch ($this->request->method) {
                case 'PerformTransaction':
                    $this->PerformTransaction();
                    break;
                case 'CheckTransaction':
                    $this->CheckTransaction();
                    break;
                case 'CancelTransaction':
                    $this->CancelTransaction();
                    break;
                case 'GetStatement':
                    $this->GetStatement();
                    break;
                case 'GetInformation':
                    $this->GetInformation();
                    break;
                case 'ChangePassword':
                    $this->ChangePassword();
                    break;
                default:
                    $this->response->error(
                        PaycomException::ERROR_METHOD_NOT_FOUND,
                        'Method not found.',
                        $this->request->method
                    );
                    break;
            }
        } catch (PaycomException $exc) {
            $exc->send();
        }
    }

    private function PerformTransaction()
    {

        if (!isset($this->request->params)) {
            $this->response->error(PaycomException::REQUIRED_PARAMS_NOT_FOUND, 'Required params not found');
        }

        if (!isset($this->request->params['serviceId']) || $this->request->params['serviceId'] != $this->config['serviceId']) {
            $this->response->error(PaycomException::SERVICE_NOT_FOUND, 'Service not found');
        }

        if (!isset($this->request->params['amount'])) {
            $this->response->error(PaycomException::REQUIRED_PARAMS_NOT_FOUND, 'Amount not found');
        }

        if (!isset($this->request->params['transactionId'])) {
            $this->response->error(PaycomException::REQUIRED_PARAMS_NOT_FOUND, 'TransactionId not found');
        }

        if (!isset($this->request->params['fields'])) {
            $this->response->error(PaycomException::REQUIRED_PARAMS_NOT_FOUND, 'Required fields not found');
        }

        if (!isset($this->request->params['fields']['client_id'])) {
            $this->response->error(PaycomException::REQUIRED_PARAMS_NOT_FOUND, 'Client id not found');
        }

        $client_id = $this->request->params['fields']['client_id'];
        $amount = $this->request->params['amount'];

        if (!is_numeric($amount)) {
            throw new PaycomException(
                $this->request->id,
                'Incorrect amount.',
                PaycomException::ERROR_INVALID_AMOUNT
            );
        }

        if ($amount<100000) {
            throw new PaycomException(
                $this->request->id,
                PaycomException::message(
                    'Неверная сумма.',
                ),
                PaycomException::ERROR_INVALID_AMOUNT,
                'driver_id'
            );
        }

        if ($amount>100000000) {
            throw new PaycomException(
                $this->request->id,
                PaycomException::message(
                    'Сумма превышает максимальный лимит',
                ),
                PaycomException::ERROR_EXCEED_MAX_AMOUNT,
                'driver_id'
            );
        }

        $db = new DB();

        $drivers = $db->select("SELECT d.id, 
                                       d.full_name, 
                                       d.balance, 
                                       c.reg_num, 
                                       cb.name as brand, 
                                       cm.name as model, 
                                       cc.name as color 
                                       FROM tx_driver d
                                       LEFT JOIN tx_car c ON d.car_id = c.id 
                                       LEFT JOIN tx_car_brand cb ON c.car_brand_id=cb.id 
                                       LEFT JOin tx_car_model cm ON c.car_model_id=cm.id
                                       LEFT JOIN tx_car_color cc ON c.car_color_id=cc.id
                                       WHERE d.id=".$client_id." AND d.status!=-1 AND d.city_id!=1");

        if (count($drivers) == 0)
        {
            $this->response->error(PaycomException::CLIENT_NOT_FOUND, 'Client not found');
        }
        else
        {
            $transaction = new Transaction();
            $transaction->transaction_id = $this->request->params['transactionId'];
            $transaction->create_time = date('Y-m-d H:i:s');
            $transaction->time_in_stamp = Format::datetime2timestamp($transaction->create_time);
            $transaction->driver_id = $client_id = $this->request->params['fields']['client_id'];
            $transaction->amount = $this->request->params['amount'];

            $transaction->save();

            if ($transaction->id == 0)
            {
                $this->response->error(PaycomException::TRANSACTION_ALREADY_EXISTS, 'Transaction already exists');
            }

            $result = array();
            $result['timestamp'] = date('Y-m-d H:i:s');
            $result['providerTrnId'] = $transaction->id;
            $result['fields'] = array();
            $result['fields']['client_id'] = $client_id;

            $this->response->send($result);

        }
    }

    private function CheckTransaction()
    {
        if (!isset($this->request->params)) {
            $this->response->error(PaycomException::REQUIRED_PARAMS_NOT_FOUND, 'Required params not found');
        }

        if (!isset($this->request->params['serviceId']) || $this->request->params['serviceId'] != $this->config['serviceId']) {
            $this->response->error(PaycomException::SERVICE_NOT_FOUND, 'Service not found');
        }
        
        if (!isset($this->request->params['transactionId'])) {
            $this->response->error(PaycomException::REQUIRED_PARAMS_NOT_FOUND, 'TransactionId not found');
        }

        if (!isset($this->request->params['timestamp'])) {
            $this->response->error(PaycomException::REQUIRED_PARAMS_NOT_FOUND, 'Timestamp not found');
        }

        // todo: Find transaction by id
        $transaction = new Transaction();

        $found = $transaction->find($this->request->params['transactionId']);

        if (!$found) {
            $this->response->error(
                PaycomException::ERROR_TRANSACTION_NOT_FOUND,
                'Transaction not found.'
            );
        }
        else
        {
            $result = array();
            $result['transactionState'] = (int)$found->state;
            $result['timestamp'] = Format::formatTimestampUz($found->create_time);
            $result['providerTrnId'] = $found->id;
            $this->response->send($result);
        }
    }

    private function CancelTransaction()
    {

        if (!isset($this->request->params)) {
            $this->response->error(PaycomException::REQUIRED_PARAMS_NOT_FOUND, 'Required params not found');
        }

        if (!isset($this->request->params['serviceId']) || $this->request->params['serviceId'] != $this->config['serviceId']) {
            $this->response->error(PaycomException::SERVICE_NOT_FOUND, 'Service not found');
        }
        
        if (!isset($this->request->params['transactionId'])) {
            $this->response->error(PaycomException::REQUIRED_PARAMS_NOT_FOUND, 'TransactionId not found');
        }

        if (!isset($this->request->params['timestamp'])) {
            $this->response->error(PaycomException::REQUIRED_PARAMS_NOT_FOUND, 'Timestamp not found');
        }

        $transaction = new Transaction();

        // search transaction by id
        $found = $transaction->find($this->request->params['transactionId']);

        // if transaction not found, send error
        if (!$found) {
            $this->response->error(
                PaycomException::ERROR_TRANSACTION_NOT_FOUND,
                'Transaction not found.'
            );
        }
        else
        {
            if ($found->state==2)
            {
                $this->response->error(
                    PaycomException::TRANSACTION_ALREADY_CANCELLED,
                    'Transaction already cancelled.'
                );
            }
            else if ($found->state==1)
            {
                $found->cancel();
                $result = array();
                $result['providerTrnId'] = $found->id;
                $result['timestamp'] = date('Y-m-d H:i:s');
                $result['transactionState'] = 2;

                $this->response->send($result);
            }
        }
    }

    private function GetStatement()
    {

        if (!isset($this->request->params)) {
            $this->response->error(PaycomException::REQUIRED_PARAMS_NOT_FOUND, 'Required params not found');
        }

        if (!isset($this->request->params['serviceId']) || $this->request->params['serviceId'] != $this->config['serviceId']) {
            $this->response->error(PaycomException::SERVICE_NOT_FOUND, 'Service not found');
        }

        if (!isset($this->request->params['dateFrom'])) {
            $this->response->error(PaycomException::REQUIRED_PARAMS_NOT_FOUND, 'DateFrom not found');
        }

        if (!isset($this->request->params['dateTo'])) {
            $this->response->error(PaycomException::REQUIRED_PARAMS_NOT_FOUND, 'DateTo not found');
        }

        $dateFrom = $this->request->params['dateFrom'];
        $dateTo = $this->request->params['dateTo'];

        if (!Format::isValidDateTime($dateFrom))
        {
            $this->response->error(PaycomException::INVALID_DATETIME, 'Invalid format DateFrom');
        }

        if (!Format::isValidDateTime($dateTo))
        {
            $this->response->error(PaycomException::INVALID_DATETIME, 'Invalid format DateTo');
        }

        $db = new DB();

        $transactions = $db->select("SELECT id AS providerTrnId, 
                                     transaction_id AS transactionId,
                                     create_time AS timestamp,
                                     amount FROM `paynet_transactions`
                                     WHERE create_time>='".$dateFrom."' AND create_time<='".$dateTo."' AND state=1");

        $result = array();
        $result['statements'] = $transactions;

        $this->response->send($result);
    }

    private function GetInformation()
    {
        if (!isset($this->request->params)) {
            $this->response->error(PaycomException::REQUIRED_PARAMS_NOT_FOUND, 'Required params not found');
        }

        if (!isset($this->request->params['fields'])) {
            $this->response->error(PaycomException::REQUIRED_PARAMS_NOT_FOUND, 'Required fields not found');
        }

        if (!isset($this->request->params['fields']['client_id'])) {
            $this->response->error(PaycomException::REQUIRED_PARAMS_NOT_FOUND, 'Client id not found');
        }

        if (!isset($this->request->params['serviceId']) || $this->request->params['serviceId'] != $this->config['serviceId']) {
            $this->response->error(PaycomException::SERVICE_NOT_FOUND, 'Service not found');
        }

        $client_id = $this->request->params['fields']['client_id'];

        $db = new DB();

        $drivers = $db->select("SELECT d.id, 
                                       d.full_name, 
                                       d.balance, 
                                       c.reg_num, 
                                       cb.name as brand, 
                                       cm.name as model, 
                                       cc.name as color 
                                       FROM tx_driver d
                                       LEFT JOIN tx_car c ON d.car_id = c.id 
                                       LEFT JOIN tx_car_brand cb ON c.car_brand_id=cb.id 
                                       LEFT JOin tx_car_model cm ON c.car_model_id=cm.id
                                       LEFT JOIN tx_car_color cc ON c.car_color_id=cc.id
                                       WHERE d.id=".$client_id." AND d.status!=-1 AND d.city_id!=1");

        if (count($drivers) == 0)
        {
            $this->response->error(PaycomException::CLIENT_NOT_FOUND, 'Client not found');
        }
        else
        {
            $driver = $drivers[0];

            $result = array();
	        $result['status'] = "0";
            $result['timestamp'] = date('Y-m-d H:i:s');
            $result['fields'] = array();
            $result['fields']['balance'] = $driver['balance'];
            $result['fields']['name'] = $driver['full_name'];
            $result['fields']['car'] = $driver['reg_num']." ".$driver['color']." ".$driver['brand']." ".$driver['model'];

            $this->response->send($result);
        }
        
        
        // $order = new Order($this->request->id, $this->request->params);

        // validate parameters
        // $order->validate($this->request->params);

        // todo: Check is there another active or completed transaction for this order
        // $transaction = new Transaction();
        // $found = $transaction->find($this->request->params,0);
        // if ($found && ($found->state == Transaction::STATE_CREATED)) {
        //     $this->response->error(
        //         PaycomException::ERROR_INCOMPLETE,
        //         PaycomException::message(
        //             'Есть транзакции в ожидании оплаты.',
        //             'Bajarilayotgan transaksiya mavjud',
        //             'There is waiting transaction for this order.'
        //         )
        //     );
        // }

        // if control is here, then we pass all validations and checks
        // send response, that order is ready to be paid.
        // $this->response->send(['allow' => true]);
    }

    private function CreateTransaction()
    {
        $order = new Order($this->request->params);

        // validate parameters
        $order->validate($this->request->params);

        // todo: Find transaction by id
        $transaction = new Transaction();
        $found = $transaction->find($this->request->params,2);

        if ($found)
        {
                // if (isset($found->error))
            // 	{
            //  	$this->response->error(
            //          $found->error,
            //          PaycomException::message(
            //             'Есть транзакции в ожидании оплаты.',
            //             'Bajarilayotgan transaksiya mavjud',
            //             'There is waiting transaction for this order.'
            //         ),
            //             "client_id"
            //      );	
            // 	}

            //     else if ($found->state != Transaction::STATE_CREATED) { // validate transaction state
            //         $this->response->error(
            //             PaycomException::ERROR_COULD_NOT_PERFORM,
            //             'Transaction found, but is not active.'
            //         );
            //     } 
            if ($found->isExpired()) 
            { 
                // if transaction timed out, cancel it and send error
                $found->cancel(Transaction::REASON_CANCELLED_BY_TIMEOUT);
                $this->response->error(
                    PaycomException::ERROR_COULD_NOT_PERFORM,
                    'Transaction is expired.'
                );
            }
            else 
            { // if transaction found and active, send it as response
                $this->response->send([
                    'create_time' =>$found->create_time,
                    'transaction' => $found->paycom_transaction_id,
                    'state' => $found->state,
                    'receivers' => null
                ]);
            }
        }
        else 
        {
            // transaction not found, create new one
            // validate new transaction time
            
            if (Format::timestamp2milliseconds(1 * $this->request->params['created_time']) - Format::timestamp(true) >= Transaction::TIMEOUT) {
                $this->response->error(
                    PaycomException::ERROR_INVALID_ACCOUNT,
                    PaycomException::message(
                        'С даты создания транзакции прошло ' . Transaction::TIMEOUT . 'мс',
                        'Tranzaksiya yaratilgan sanadan ' . Transaction::TIMEOUT . 'ms o`tgan',
                        'Since create time of the transaction passed ' . Transaction::TIMEOUT . 'ms'
                    ),
                    'time'
                );
            }

            // create new transaction
            // keep create_time as timestamp, it is necessary in response
            $create_time = Format::timestamp(true);
            $transaction->paycom_transaction_id = $this->request->params['id'];
            $transaction->paycom_time = $this->request->params['created_time'];
            $transaction->paycom_time_datetime = Format::timestamp2datetime($this->request->params['created_time']);
            $transaction->create_time = Format::timestamp2datetime($create_time);
            $transaction->time_in_stamp = $create_time;
            $transaction->state = Transaction::STATE_CREATED;
            $transaction->amount = $this->request->params['amount'];
            $transaction->driver_id = $this->request->account('driver_id');
            $transaction->save(1); // after save $transaction->id will be populated with the newly created transaction's id.

            // send response
            $this->response->send([
                'create_time' => $create_time,
                'transaction_id' => (string)$transaction->id,
                'state' => $transaction->state,
                'receivers' => null
            ]);
        }
    }

    private function ChangePassword()
    {
        // validate, password is specified, otherwise send error
        if (!isset($this->request->params['password']) || !trim($this->request->params['password'])) {
            $this->response->error(PaycomException::ERROR_INVALID_ACCOUNT, 'New password not specified.', 'password');
        }

        // if current password specified as new, then send error
        if ($this->merchant->config['key'] == $this->request->params['password']) {
            $this->response->error(PaycomException::ERROR_INSUFFICIENT_PRIVILEGE, 'Insufficient privilege. Incorrect new password.');
        }

        // todo: Implement saving password into data store or file
        // example implementation, that saves new password into file specified in the configuration
        if (!file_put_contents($this->config['keyFile'], $this->request->params['password'])) {
            $this->response->error(PaycomException::ERROR_INTERNAL_SYSTEM, 'Internal System Error.');
        }

        // if control is here, then password is saved into data store
        // send success response
        $this->response->send(['success' => true]);
    }
}