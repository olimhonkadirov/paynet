<?php
namespace Paycom;

class Transaction
{
    /** Transaction expiration time in milliseconds. 43 200 000 ms = 12 hours. */
    const TIMEOUT = 43200000;

    const STATE_CREATED = 1;
    const STATE_COMPLETED = 4;
    const STATE_CANCELLED = 2;
    const STATE_CANCELLED_AFTER_COMPLETE = -2;

    const REASON_RECEIVERS_NOT_FOUND = 1;
    const REASON_PROCESSING_EXECUTION_FAILED = 2;
    const REASON_EXECUTION_FAILED = 3;
    const REASON_CANCELLED_BY_TIMEOUT = 4;
    const REASON_FUND_RETURNED = 5;
    const REASON_UNKNOWN = 10;

    /** @var string Paycom transaction id. */
    public $transaction_id;

    /** @var int Paycom transaction time as is without change. */
    public $paycom_time;

    /** @var string Paycom transaction time as date and time string. */
    public $paycom_time_datetime;

    /** @var int Transaction id in the merchant's system. */
    public $id;

    /** @var string Transaction create date and time in the merchant's system. */
    public $create_time;

    public $time_in_stamp;

    /** @var string Transaction perform date and time in the merchant's system. */
    public $perform_time;

    /** @var string Transaction cancel date and time in the merchant's system. */
    public $cancel_time;

    /** @var int Transaction state. */
    public $state;

    /** @var int Transaction cancelling reason. */
    public $reason;

    /** @var int Amount value in coins, this is service or product price. */
    public $amount;

    /** @var string Pay receivers. Null - owner is the only receiver. */
    public $receivers;

    // additional fields:
    // - to identify order or product, for example, code of the order
    // - to identify client, for example, account id or phone number

    /** @var string Code to identify the order or service for pay. */
    public $driver_id;

    /**
     * Saves current transaction instance in a data store.
     * @return void
     */
    public function save()
    {
        $db = new DB();
        $insert_id = $db->insert("INSERT IGNORE INTO `paynet_transactions`
                                  SET transaction_id='".$this->transaction_id."',
                                  create_time='".$this->create_time."',
                                  timeinstamp='".$this->time_in_stamp."',
                                  state=".self::STATE_CREATED.", 
                                  driver_id=".$this->driver_id.", 
                                  amount=".$this->amount);
        $this->id = $insert_id;
    }

    /**
     * Cancels transaction with the specified reason.
     * @param int $reason cancelling reason.
     * @return void
     */
    public function cancel()
    {
        // todo: Implement transaction cancelling on data store

        // todo: Populate $cancel_time with value
        $this->cancel_time = date('Y-m-d H:i:s');

        // todo: Change $state to cancelled (-1 or -2) according to the current state
        // Scenario: CreateTransaction -> CancelTransaction
        if ($this->state == self::STATE_CREATED) {
            $this->state = self::STATE_CANCELLED;
        }

        $db = new DB();

        $db->query("UPDATE paynet_transactions
                            SET `state`=".$this->state.",
                            `cancel_time`='".$this->cancel_time."', 
                            `canceltimeinstamp`='".FORMAT::datetime2timestamp($this->cancel_time)."' where id=".$this->id);

        // todo: Update transaction on data store
    }

    /**
     * Determines whether current transaction is expired or not.
     * @return bool true - if current instance of the transaction is expired, false - otherwise.
     */
    public function isExpired()
    {
        // todo: Implement transaction expiration check
        // for example, if transaction is active and passed TIMEOUT milliseconds after its creation, then it is expired
        return $this->state == self::STATE_CREATED && $this->create_time - Format::timestamp(true) > self::TIMEOUT;
    }

    /**
     * Find transaction by given parameters.
     * @param mixed $params parameters
     * @return Transaction|Transaction[]
     */
    public function find($transaction_id)
    {
        // todo: Implement searching transaction by id, populate current instance with data and return it
        // todo: Implement searching transactions by given parameters and return list of transactions

        // Possible features:
        // Search transaction by product/order id that specified in $params
        // Search transactions for a given period of time that specified in $params

        //$this->id = $params['id'];
        // $method = 0 : CheckPerformTransaction
        // $method = 1 : CheckTransaction
        // $method = 2 : CreateTransaction
        // $method = 3 : PerformTransaction
        // $method = 4 : CancelTransaction
        $db = new DB();

        $row = $db->select("select * from paynet_transactions where transaction_id=".$transaction_id);
        
        if (count($row)>0)
        {
            $this->id = $row[0]['id'];
            $this->state = $row[0]['state'];
            $this->create_time = $row[0]['create_time'];
            return $this;
        }
        else
        {
            return null;
        }
    }

    /**
     * Gets list of transactions for the given period including period boundaries.
     * @param int $from_date start of the period in timestamp.
     * @param int $to_date end of the period in timestamp.
     * @return array list of found transactions converted into report format for send as a response.
     */
    public function report($from_date, $to_date)
    {
        $from_date = Format::timestamp2datetime($from_date);
        $to_date = Format::timestamp2datetime($to_date);

        // container to hold rows/document from data store
        $rows = [];

        // todo: Retrieve transactions for the specified period from data store

        // assume, here we have $rows variable that is populated with transactions from data store
        // normalize data for response
        $result = [];
        foreach ($rows as $row) {
            $result[] = [
                'id' => $row['paycom_transaction_id'], // paycom transaction id
                'time' => 1 * $row['paycom_time'], // paycom transaction timestamp as is
                'amount' => 1 * $row['amount'],
                'account' => [
                    'client_id' => $row['client_id'], // account parameters to identify client/order/service
                    // ... additional parameters may be listed here, which are belongs to the account
                ],
                'create_time' => Format::datetime2timestamp($row['create_time']),
                'perform_time' => Format::datetime2timestamp($row['perform_time']),
                'cancel_time' => Format::datetime2timestamp($row['cancel_time']),
                'transaction' => $row['id'],
                'state' => 1 * $row['state'],
                'reason' => isset($row['reason']) ? 1 * $row['reason'] : null,
                'receivers' => $row['receivers']
            ];
        }

        return $result;
    }
}