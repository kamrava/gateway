<?php
namespace Larabookir\Gateway;

use Illuminate\Support\Facades\Request;
use Larabookir\Gateway\Enum;
use Carbon\Carbon;

abstract class PortAbstract
{
	/**
	 * Transaction id
	 *
	 * @var null|int
	 */
	protected $transactionId = null;

	/**
	 * Transaction row in database
	 */
	protected $transaction = null;

	/**
	 * Customer card number
	 *
	 * @var string
	 */
	protected $cardNumber = '';

	/**
	 * @var Config
	 */
	protected $config;
	/**
	 * @var NewConfig
	 */
	protected $new_config;

	/**
	 * Port id
	 *
	 * @var int
	 */
	protected $portName;

	/**
	 * Reference id
	 *
	 * @var string
	 */
	protected $refId;

	/**
	 * Is Currency Set To Toman?
	 *
	 * @var boolean
	 */
	protected $is_toman;

	/**
	 * Amount
	 *
	 * @var int
	 */
	protected $amount;

	/**
	 * Payment Type
	 *
	 * @var string
	 */
	 protected $type;

    /**
     * Payment request_id
     *
     * @var string
     */
	protected $request_id;

    /**
     * Payment request_id
     *
     * @var integer
     */
    protected $user_id;

		/**
     * Payment paymentable_id
     *
     * @var string
     */
    protected $paymentable_id;

		/**
     * Payment paymentable_type
     *
     * @var string
     */
    protected $paymentable_type;

	/**
	 * callback URL
	 *
	 * @var url
	 */
	protected $callbackUrl;

	/**
	 * Tracking code payment
	 *
	 * @var string
	 */
	protected $trackingCode;

	/**
	 * Initialize of class
	 *
	 * @param Config $config
	 * @param DataBaseManager $db
	 * @param int $port
	 */
	function __construct()
	{
		$this->db = app('db');
	}

	/** bootstraper */
	function boot(){

	}

	function setConfig($config)
	{
		$this->config = $config;
	}

	function setNewConfig($config)
	{
		$this->new_config = $config;
	}

	/**
	 * @return mixed
	 */
	function getTable()
	{
		return $this->db->table($this->config->get('gateway.table'));
	}

	/**
	 * @return mixed
	 */
	function getLogTable()
	{
		return $this->db->table($this->config->get('gateway.table') . '_logs');
	}

	/**
	 * Get port id, $this->port
	 *
	 * @return int
	 */
	function getPortName()
	{
		return $this->portName;
	}

	/**
	 * Get port id, $this->port
	 *
	 * @return int
	 */
	function setPortName($name)
	{
		if($name == Enum::ZARINPAL) {
			$this->is_toman = true;
		}
		$this->portName = $name;
	}

	/**
	 * Return card number
	 *
	 * @return string
	 */
	function cardNumber()
	{
		return $this->cardNumber;
	}

	/**
	 * Return tracking code
	 */
	function trackingCode()
	{
		return $this->trackingCode;
	}

	/**
	 * Get transaction id
	 *
	 * @return int|null
	 */
	function transactionId()
	{
		return $this->transactionId;
	}

	/**
	 * Return reference id
	 */
	function refId()
	{
		return $this->refId;
	}

	/**
	 * Sets price
	 * @param $price
	 * @return mixed
	 */
	function price($price)
	{
		return $this->is_toman ? $this->set($price) : $this->set($price * 10);
	}

	/**
	 * get price
	 */
	function getPrice()
	{
		return $this->is_toman ? $this->amount : $this->amount / 10;
	}

	/**
	 * Sets type
	 * @param $type
	 * @return mixed
	 */
	 function type($type)
	 {
		 return $this->set($type);
	 }

	/**
	 * get type
	 */
	 function getType()
	 {
		 return $this->type;
	 }

    /**
     * Sets request_id
     * @param $request_id
     * @return mixed
     */
    function setRequestId($request_id)
    {
        return $this->set($request_id);
	}

    /**
     * Sets user_id
     * @param $user_id
     * @return mixed
     */
    function setUserId($user_id)
    {
        return $this->set($user_id);
	}

    /**
     * get request_id
     */
    function getRequestId()
    {
        return $this->request_id;
	}

    /**
     * get user_id
     */
    function getUserId()
    {
        return $this->user_id;
    }

	/**
	 * Sets paymentable_id
	 * @param $paymentable_id
	 * @return mixed
	 */
	function setPaymentableId($paymentable_id)
    {
        return $this->set($paymentable_id);
    }

    /**
     * get paymentable_id
     */
    function getPaymentableId()
    {
        return $this->paymentable_id;
    }

	/**
	 * Sets paymentable_type
	 * @param $paymentable_type
	 * @return mixed
	 */
	function setPaymentableType($paymentable_type)
    {
        return $this->set($paymentable_type);
    }

    /**
     * get paymentable_type
     */
    function getPaymentableType()
    {
        return $this->paymentable_type;
	}
	
	public function getBankAttr($bank_name, $attr)
    {
        return $this->new_config->where('name', $bank_name)->first()->jsonData($attr);
    }

	/**
	 * Return result of payment
	 * If result is done, return true, otherwise throws an related exception
	 *
	 * This method must be implements in child class
	 *
	 * @param object $transaction row of transaction in database
	 *
	 * @return $this
	 */
	function verify($transaction)
	{
		$amount = $this->is_toman ? intval($transaction->price) : intval($transaction->price * 10);
		$this->transaction = $transaction;
		$this->transactionId = $transaction->id;
		$this->amount = $amount;
		$this->type = $transaction->type;
		$this->refId = $transaction->ref_id;
		$this->user_id = $transaction->user_id;
		$this->request_id = $transaction->request_id;
		$this->paymentable_id = $transaction->paymentable_id;
		$this->paymentable_type = $transaction->paymentable_type;
	}

	function getTimeId()
	{
		$genuid = function(){
			return substr(str_pad(str_replace('.','', microtime(true)),12,0),0,12);
		};
		$uid=$genuid();
		while ($this->getTable()->whereId($uid)->first())
			$uid = $genuid();
		return $uid;
	}

	/**
	 * Insert new transaction to poolport_transactions table
	 *
	 * @return int last inserted id
	 */
	protected function newTransaction()
	{
		$uid = $this->getTimeId();
		$price = $this->is_toman ? $this->amount : $this->amount / 10;
		$this->transactionId = $this->getTable()->insert([
			'id'      => $uid,
			'user_id' => $this->user_id,
			'request_id' => $this->request_id,
			'paymentable_id' => $this->paymentable_id,
			'paymentable_type' => $this->paymentable_type,
			'port'    => $this->getPortName(),
			'price'   => $price,
			'type'    => $this->type,
			'status'  => Enum::TRANSACTION_INIT,
			'ip'      => request()->getClientIp(),
			'created_at' => Carbon::now(),
			'updated_at' => Carbon::now(),
		]) ? $uid : null;
		return $this->transactionId;
	}

	/**
	 * Commit transaction
	 * Set status field to success status
	 *
	 * @return bool
	 */
	protected function transactionSucceed()
	{
		return $this->getTable()->whereId($this->transactionId)->update([
			'status' => Enum::TRANSACTION_SUCCEED,
			'tracking_code' => $this->trackingCode,
			'card_number' => $this->cardNumber,
			'payment_date' => Carbon::now(),
			'updated_at' => Carbon::now(),
		]);
	}

	/**
	 * Failed transaction
	 * Set status field to error status
	 *
	 * @return bool
	 */
	protected function transactionFailed()
	{
		return $this->getTable()->whereId($this->transactionId)->update([
			'status' => Enum::TRANSACTION_FAILED,
			'updated_at' => Carbon::now(),
		]);
	}

	/**
	 * Update transaction refId
	 *
	 * @return void
	 */
	protected function transactionSetRefId()
	{
		return $this->getTable()->whereId($this->transactionId)->update([
			'ref_id' => $this->refId,
			'updated_at' => Carbon::now(),
		]);

	}

	/**
	 * New log
	 *
	 * @param string|int $statusCode
	 * @param string $statusMessage
	 */
	protected function newLog($statusCode, $statusMessage)
	{
		return $this->getLogTable()->insert([
			'payment_id' => $this->transactionId,
			'result_code' => $statusCode,
			'result_message' => $statusMessage,
			'log_date' => Carbon::now(),
		]);
	}

	/**
	 * Add query string to a url
	 *
	 * @param string $url
	 * @param array $query
	 * @return string
	 */
	protected function makeCallback($url, array $query)
	{
		return $this->url_modify(array_merge($query, ['_token' => csrf_token()]), url($url));
	}

	/**
	 * manipulate the Current/Given URL with the given parameters
	 * @param $changes
	 * @param  $url
	 * @return string
	 */
	protected function url_modify($changes, $url)
	{
		// Parse the url into pieces
		$url_array = parse_url($url);

		// The original URL had a query string, modify it.
		if (!empty($url_array['query'])) {
			parse_str($url_array['query'], $query_array);
			$query_array = array_merge($query_array, $changes);
		} // The original URL didn't have a query string, add it.
		else {
			$query_array = $changes;
		}

		return (!empty($url_array['scheme']) ? $url_array['scheme'] . '://' : null) .
		(!empty($url_array['host']) ? $url_array['host'] : null) .
		$url_array['path'] . '?' . http_build_query($query_array);
	}
}
