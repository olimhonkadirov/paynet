<?php
namespace Paycom;

class Order
{
    /** Order is available for sell, anyone can buy it. */
    const STATE_AVAILABLE = 0;

    /** Pay in progress, order must not be changed. */
    const STATE_WAITING_PAY = 1;

    /** Order completed and not available for sell. */
    const STATE_PAY_ACCEPTED = 2;

    /** Order is cancelled. */
    const STATE_CANCELLED = 3;

    public $request_id;
    public $params;

    public function __construct($params)
    {
        $this->params = $params;
    }

    /**
     * Validates amount and account values.
     * @param array $params amount and account parameters to validate.
     * @return bool true - if validation passes
     * @throws PaycomException - if validation fails
     */
    public function validate(array $params)
    {

        $driver_id = $params['account']['driver_id'];

        $db = new DB();

        $rows = $db -> select("SELECT `id`, `status` FROM `tx_driver` WHERE id=$driver_id");

        if (count($rows)==0)
        {
            throw new PaycomException(
                $this->request_id,
                PaycomException::message(
                    'Водитель с такими ID не существует.',
                    'Bunday ID raqamli shofyor mavjud emas.',
                    'Driver\'s ID is not found'
                ),
                PaycomException::ERROR_INVALID_ACCOUNT,
                'driver_id'
            );    
        }
        else
        {
            if ($rows[0]["status"]==-1)
            {
                throw new PaycomException(
                    $this->request_id,
                    PaycomException::message(
                        'Водитель заблокированв',
                        'Haydovchi bloklangan',
                        'Driver is suspended'
                    ),
                    PaycomException::ERROR_BLOCKED,
                    'client_id'
                );       
            }
        }

        // todo: Validate amount, if failed throw error
        // for example, check amount is numeric
        if (!is_numeric($params['amount'])) {
            throw new PaycomException(
                $this->request_id,
                'Incorrect amount.',
                PaycomException::ERROR_INVALID_AMOUNT
            );
        }

        if ($params['amount']<50000 || $params['amount']>100000000) {
            throw new PaycomException(
                $this->request_id,
                PaycomException::message(
                    'Неверная сумма.',
                    'Summa notugri kursatilgan.',
                    'Incorrect order amount.'
                ),
                PaycomException::ERROR_INVALID_AMOUNT,
                'driver_id'
            );
        }

        // todo: Validate account, if failed throw error
        // assume, we should have order_id
        if (!isset($params['account']['driver_id']) && !is_numeric($params['account']['driver_id'])) {
            throw new PaycomException(
                $this->request_id,
                PaycomException::message(
                    'Неверный код ID водителя.',
                    'Id kod noto\'g\'ri kiritildi.',
                    'Incorrect driver ID.'
                ),
                PaycomException::ERROR_INVALID_ACCOUNT,
                'driver_id'
            );
        }

        // todo: Check is order available

        // assume, after find() $this will be populated with Order data
        // $this->find($params['account']['order_id']);

        // // for example, order state before payment should be 'waiting pay'
        // if ($this->state != self::STATE_WAITING_PAY) {
        //     throw new PaycomException(
        //         $this->request_id,
        //         'Order state is invalid.',
        //         PaycomException::ERROR_COULD_NOT_PERFORM
        //     );
        // }

        // // keep params for further use
        // $this->params = $params;

        return true;
    }

    /**
     * Find order by given parameters.
     * @param mixed $params parameters.
     * @return Order|Order[] found order or array of orders.
     */
    public function find($params)
    {
        // todo: Implement searching order(s) by given parameters, populate current instance with data

        $db = new DB();

        $result = $db->select("select client_id,amount from transaction where transactionID='".$params['id']."'");

        $this->id=$result[0]['client_id'];
        $this->amount=$result[0]['amount'];

        return $this;

    }

    /**
     * Change order's state to specified one.
     * @param int $state new state of the order
     * @return void
     */
    public function changeState($state)
    {
        // todo: Implement changing order state (reserve order after create transaction or free order after cancel)

        $db = new DB();

        $db->query("update jurnal set paid=".$this->amount.", paystate=".$state." where id=".$this->id);

    }

    /**
     * Check, whether order can be cancelled or not.
     * @return bool true - order is cancellable, otherwise false.
     */
    public function allowCancel()
    {
        // todo: Implement order cancelling allowance check
        
        return true;
    }
}