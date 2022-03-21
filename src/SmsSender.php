<?php

declare(strict_types=1);

namespace Baraja\Sms;


use Baraja\PhoneNumber\PhoneNumberFormatter;
use Nette\Utils\Strings;

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
		if (class_exists(Strings::class)) {
			$message = Strings::toAscii($message);
		}
		$message = trim($message);
		$context = stream_context_create(
			[
				'http' => [
					'method' => 'POST',
					'header' => 'Content-type: application/x-www-form-urlencoded',
					'content' => http_build_query(
						[
							'login' => $this->login,
							'act' => 'send',
							'msisdn' => PhoneNumberFormatter::fix($phoneNumber, $defaultPrefix),
							'msg' => $message,
							'auth' => md5(
								$this->password
								. $this->login
								. 'send'
								. substr($message, 0, self::AUTH_MSG_LENGTH),
							),
						],
					),
				],
			],
		);

		$response = (string) file_get_contents($this->apiUrl, false, $context);
		if ($response) {
			$this->processResponse($response);
		}
	}


	public function setApiUrl(string $apiUrl): void
	{
		$this->apiUrl = $apiUrl;
	}


	private function processResponse(string $response): void
	{
		if (preg_match(
			'/<status>(?:.|\s)*<id>(?<code>\d+)<\/id><message>(?<message>.+?)<\/message>/',
			$response,
			$parser,
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
