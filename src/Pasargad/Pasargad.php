<?php

namespace Larabookir\Gateway\Pasargad;

use Illuminate\Support\Facades\Input;
use Larabookir\Gateway\Enum;
use SoapClient;
use Larabookir\Gateway\PortAbstract;
use Larabookir\Gateway\PortInterface;
use Symfony\Component\VarDumper\Dumper\DataDumperInterface;
use App\Enums\BankGatewayEnum;

class Pasargad extends PortAbstract implements PortInterface
{
	/**
	 * Url of parsian gateway web service
	 *
	 * @var string
	 */

	protected $checkTransactionUrl = 'https://pep.shaparak.ir/CheckTransactionResult.aspx';
	protected $verifyUrl = 'https://pep.shaparak.ir/VerifyPayment.aspx';
	protected $refundUrl = 'https://pep.shaparak.ir/doRefund.aspx';

	/**
	 * Address of gate for redirect
	 *
	 * @var string
	 */
	protected $gateUrl = 'https://pep.shaparak.ir/gateway.aspx';

	/**
     * {@inheritdoc}
     */
    public function setCurrencyToToman()
    {
        $this->is_toman = true;

        return $this;
    }

	/**
	 * {@inheritdoc}
	 */
	public function set($amount)
	{
		$this->amount = intval($amount);
		return $this;
	}

	/**
     * {@inheritdoc}
     */
     public function type($type)
     {
         $this->type = $type;

         return $this;
     }

    /**
     * {@inheritdoc}
     */
    public function setRequestId($request_id)
    {
        $this->request_id = $request_id;

        return $this;
	}

	/**
     * {@inheritdoc}
     */
    public function setUserId($user_id)
    {
        $this->user_id = $user_id;

        return $this;
    }

	/**
	 * {@inheritdoc}
	 */
	public function setPaymentableId($paymentable_id)
	{
			$this->paymentable_id = $paymentable_id;

			return $this;
	}

	/**
	 * {@inheritdoc}
	 */
	public function setPaymentableType($paymentable_type)
	{
			$this->paymentable_type = $paymentable_type;

			return $this;
	}

	/**
	 * {@inheritdoc}
	 */
	public function ready()
	{
		$this->sendPayRequest();

		return $this;
	}

	/**
	 * {@inheritdoc}
	 */
	public function redirect()
	{

        // $processor = new RSAProcessor($this->config->get('gateway.pasargad.certificate-path'),RSAKeyType::XMLFile);
        $processor = new RSAProcessor(storage_path('app/gateway/pasargad/'.$this->getBankAttr(BankGatewayEnum::PASARGAD, 'certificate-path')),RSAKeyType::XMLFile);

		$url = $this->gateUrl;
		$redirectUrl = $this->getCallback();
        $invoiceNumber = $this->transactionId();
        $amount = $this->amount;
        $terminalCode = $this->getBankAttr(BankGatewayEnum::PASARGAD, 'terminalId');
        $merchantCode = $this->getBankAttr(BankGatewayEnum::PASARGAD, 'merchantId');
        // $terminalCode = $this->config->get('gateway.pasargad.terminalId');
        // $merchantCode = $this->config->get('gateway.pasargad.merchantId');
        $timeStamp = date("Y/m/d H:i:s");
        $invoiceDate = date("Y/m/d H:i:s");
        $action = 1003;
        $data = "#". $merchantCode ."#". $terminalCode ."#". $invoiceNumber ."#". $invoiceDate ."#". $amount ."#". $redirectUrl ."#". $action ."#". $timeStamp ."#";
        $data = sha1($data,true);
        $data =  $processor->sign($data); // امضاي ديجيتال
        $sign =  base64_encode($data); // base64_encode

		return view('gateway::pasargad-redirector')->with(compact('url','redirectUrl','invoiceNumber','invoiceDate','amount','terminalCode','merchantCode','timeStamp','action','sign'));
	}

	/**
	 * {@inheritdoc}
	 */
	public function verify($transaction)
	{
		parent::verify($transaction);

		$this->verifyPayment();

		return $this;
	}

	/**
	 * Sets callback url
	 * @param $url
	 */
	function setCallback($url)
	{
		$this->callbackUrl = $url;
		return $this;
	}

	/**
	 * Gets callback url
	 * @return string
	 */
	function getCallback()
	{
		if (!$this->callbackUrl)
			$this->callbackUrl = $this->config->get('gateway.pasargad.callback-url');

		return $this->callbackUrl;
	}

	/**
	 * Send pay request to parsian gateway
	 *
	 * @return bool
	 *
	 * @throws ParsianErrorException
	 */
	protected function sendPayRequest()
	{
		$this->newTransaction();
	}

	/**
	 * Verify payment
	 *
	 * @throws ParsianErrorException
	 */
	protected function verifyPayment()
	{
        $processor = new RSAProcessor(storage_path('app/gateway/pasargad/'.$this->getBankAttr(BankGatewayEnum::PASARGAD, 'certificate-path')),RSAKeyType::XMLFile);
        $fields = array('invoiceUID' => Input::get('tref'));
        $result = Parser::post2https($fields,'https://pep.shaparak.ir/CheckTransactionResult.aspx');
        $check_array = Parser::makeXMLTree($result);
        if ($check_array['result'] == "True") {
            $fields = array(
                'MerchantCode' => $this->getBankAttr(BankGatewayEnum::PASARGAD, 'merchantId'),
                'TerminalCode' => $this->getBankAttr(BankGatewayEnum::PASARGAD, 'terminalId'),
                // 'MerchantCode' => $this->config->get('gateway.pasargad.merchantId'),
                // 'TerminalCode' => $this->config->get('gateway.pasargad.terminalId'),
                'InvoiceNumber' => $check_array['invoiceNumber'],
                'InvoiceDate' => Input::get('iD'),
                'amount' => $check_array['amount'],
                'TimeStamp' => date("Y/m/d H:i:s"),
                'sign' => '',
                );

            $data = "#" . $fields['MerchantCode'] . "#" . $fields['TerminalCode'] . "#" . $fields['InvoiceNumber'] ."#" . $fields['InvoiceDate'] . "#" . $fields['amount'] . "#" . $fields['TimeStamp'] ."#";
            $data = sha1($data, true);
            $data = $processor->sign($data);
            $fields['sign'] = base64_encode($data);
            $result = Parser::post2https($fields,"https://pep.shaparak.ir/VerifyPayment.aspx");
            $array = Parser::makeXMLTree($result);
            if ($array['actionResult']['result'] != "True") {
                $this->newLog(-1, Enum::TRANSACTION_FAILED_TEXT);
                $this->transactionFailed();
                throw new PasargadErrorException(Enum::TRANSACTION_FAILED_TEXT, -1);
            }
            $this->refId = $check_array['referenceNumber'];
            $this->transactionSetRefId();
            $this->trackingCode = Input::get('tref');
            $this->transactionSucceed();
            $this->newLog(0, Enum::TRANSACTION_SUCCEED_TEXT);
        } else {
            $this->newLog(-1, Enum::TRANSACTION_FAILED_TEXT);
            $this->transactionFailed();
            throw new PasargadErrorException(Enum::TRANSACTION_FAILED_TEXT, -1);
        }
	}
}
