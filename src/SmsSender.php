<?php

declare(strict_types=1);

namespace Baraja\Sms;


/**
 * Send SMS via https://sms-sluzba.cz
 */
final class SmsSender
{
	public const AUTH_MSG_LENGTH = 31;

	private string $apiUrl = 'https://smsgateapi.sluzba.cz/apipost30/sms';

	private string $login;

	private string $password;


	public function __construct(string $login, string $password)
	{
		$login = trim($login);
		$password = trim($password);
		if ($login === '' || $password === '') {
			throw new \InvalidArgumentException('Login or password can not be empty.');
		}

		$this->login = $login;
		$this->password = md5($password);
	}


	/**
	 * Send SMS message to given phone number.
	 * If phone number is not in valid format it will be formatted automatically.
	 */
	public function send(string $message, string $phoneNumber, int $defaultPrefix = 420): void
	{
		$context = stream_context_create([
			'http' => [
				'method' => 'POST',
				'header' => 'Content-type: application/x-www-form-urlencoded',
				'content' => http_build_query([
					'login' => $this->login,
					'act' => 'send',
					'msisdn' => $this->formatPhone($phoneNumber, $defaultPrefix),
					'msg' => $message = trim($message),
					'auth' => md5($this->password . $this->login . 'send' . substr($message, 0, self::AUTH_MSG_LENGTH)),
				]),
			],
		]);

		$response = (string) file_get_contents($this->apiUrl, false, $context);
		if ($response) {
			$this->processResponse($response);
		}
	}


	public function setApiUrl(string $apiUrl): void
	{
		$this->apiUrl = $apiUrl;
	}


	/**
	 * Normalize phone to basic format if pattern match.
	 *
	 * @param string $phone user input
	 * @param int $defaultPrefix use this prefix when number prefix does not exist
	 */
	private function formatPhone(string $phone, int $defaultPrefix): string
	{
		$phone = (string) preg_replace('/\s+/', '', $phone); // remove spaces

		if (preg_match('/^([\+0-9]+)/', $phone, $trimUnexpected)) { // remove user notice and unexpected characters
			$phone = (string) $trimUnexpected[1];
		}

		if (preg_match('/^\+(4\d{2})(\d{3})(\d{3})(\d{3})$/', $phone, $prefixParser)) { // +420 xxx xxx xxx
			$phone = '+' . $prefixParser[1] . ' ' . $prefixParser[2] . ' ' . $prefixParser[3] . ' ' . $prefixParser[4];
		} elseif (preg_match('/^\+(4\d{2})(\d+)$/', $phone, $prefixSimpleParser)) { // +420 xxx
			$phone = '+' . $prefixSimpleParser[1] . ' ' . $prefixSimpleParser[2];
		} elseif (preg_match('/^(\d{3})(\d{3})(\d{3})$/', $phone, $regularParser)) { // numbers only
			$phone = '+' . $defaultPrefix . ' ' . $regularParser[1] . ' ' . $regularParser[2] . ' ' . $regularParser[3];
		}

		return $phone;
	}


	private function processResponse(string $response): void
	{
		if (preg_match(
				'/<status>(?:(?:.|\s)*)<id>(?<code>\d+)<\/id><message>(?<message>.+?)<\/message>/',
				$response,
				$parser
			)
			&& (int) $parser['code'] > 299
		) {
			throw new CanNotSendSmsException(
				'Error #' . $parser['code'] . ': ' . $parser['message'],
				(int) $parser['code'],
			);
		}
	}
}
