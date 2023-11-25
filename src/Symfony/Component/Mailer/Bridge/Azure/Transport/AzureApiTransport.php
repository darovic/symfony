<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Mailer\Bridge\Azure\Transport;

use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\Exception\HttpTransportException;
use Symfony\Component\Mailer\Header\MetadataHeader;
use Symfony\Component\Mailer\Header\TagHeader;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\AbstractApiTransport;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Symfony\Contracts\HttpClient\Exception\DecodingExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

final class AzureApiTransport extends AbstractApiTransport {

	private string $endpoint;
	private string $version;
	private string $key;

	private const QUERY_PATH_TEMPLATE = '/emails:send?api-version=%s';
	private const REQUEST_STRING_TEMPLATE = "%s\n%s\n%s;%s;%s";
	private const AUTH_TEMPLATE = 'HMAC-SHA256 SignedHeaders=x-ms-date;host;x-ms-content-sha256&Signature=%s';

	public function __construct(
		AzureApiTransportArgs $args,
		HttpClientInterface $client = null,
		EventDispatcherInterface $dispatcher = null,
		LoggerInterface $logger = null
	) {
		$this->endpoint = $args->getEndpoint();
		$this->key = $args->getKey();
		$this->version = $args->getVersion();
		assert($client !== null);

		parent::__construct($client, $dispatcher, $logger);
	}

	public function doSendApi(SentMessage $sentMessage, Email $email, Envelope $envelope): ResponseInterface {
		$payload = $this->constructPayload($email, $envelope);
		$headers = $this->constructHeaders($payload, $email);

		$options = [
			'body' => \json_encode($payload),
			'headers' => $headers,
		];

		$response = $this->client->request('POST', $this->constructURL(), $options);

		try {
			$status = $response->getStatusCode();
		} catch (TransportExceptionInterface $e) {
			throw new HttpTransportException('Couldn\'t connect to Azure', $response, 0, $e);
		}

		if ($status !== 202) {
			try {
				$result = $response->toArray(false);
				throw new HttpTransportException('Unable to send an email (' . $result['error']['code'] . '): ' . $result['error']['message'], $response, $status);
			} catch (DecodingExceptionInterface $e) {
				throw new HttpTransportException('Unable to send an email: ' . $response->getContent(false) . sprintf(' (code %d).', $status), $response, 0, $e);
			}
		}

		$sentMessage->setMessageId(\json_decode($response->getContent(false), true)['id']);

		return $response;
	}

	private function constructPayload(Email $email, Envelope $envelope): array {
		$stringifyAddress = function (Address $address) {
			$stringifiedAddress = ['address' => $address->getAddress()];

			if ($address->getName())
				$stringifiedAddress['displayName'] = $address->getName();

			return $stringifiedAddress;
		};

		$payload = [
			'content' => [
				'html' => $email->getHtmlBody(),
				'plainText' => $email->getTextBody(),
				'subject' => $email->getSubject(),
			],
			'recipients' => [
				'to' => \array_map($stringifyAddress, $this->getRecipients($email, $envelope)),
				'cc' => \array_map($stringifyAddress, $email->getCc()),
				'bcc' => \array_map($stringifyAddress, $email->getBcc()),
			],
			'senderAddress' => $envelope->getSender()->getAddress(),
			// 'attachments' => $this->constructAttachmentsPayload($email),
			// 'headers' => $this->constructHeaders(),
			'userEngagementTrackingDisabled' => true,
		];

		return $payload;
	}

	private function constructURL(): string {
		return \sprintf(
			'%s%s%s',
			strchr($this->endpoint, 'https://') ? '' : 'https://',
			$this->endpoint,
			$this->constructQueryPath()
		);
	}

	private function constructQueryPath(): string {
		return \sprintf($this::QUERY_PATH_TEMPLATE, $this->version);
	}

	private function hashData(string $data): string {
		$hashed = \hash('SHA256', $data, true);

		return \base64_encode($hashed);
	}

	private function constructHeaders(array $payload, Email $email): array {
		$timestamp = (new \DateTime('now', new \DateTimeZone('UTC')))->format(\DateTimeInterface::RFC7231);

		$query = $this->constructQueryPath();
		$host = \str_replace('https://', '', $this->endpoint);
		$contentHash = $this->hashData(\json_encode($payload));

		$signature = $this->constructSignature($query, $timestamp, $host, $contentHash);

		$headers = [
			'Content-Type' => 'application/json',
			'Authorization' => $this->constructAuthHeader($signature),
			'x-ms-date' => $timestamp,
			'x-ms-content-sha256' => $contentHash,
		];

		return $headers;
	}

	private function constructSignature(string $query, string $timestamp, string $host, string $hash): string {
		$key = \base64_decode($this->key);
		$data = \sprintf($this::REQUEST_STRING_TEMPLATE, 'POST', $query, $timestamp, $host, $hash);

		$hash = \hash_hmac('SHA256', \mb_convert_encoding($data, 'UTF-8'), $key, true);
		return \base64_encode($hash);
	}

	private function constructAuthHeader(string $signature): string {
		return \sprintf($this::AUTH_TEMPLATE, $signature);
	}

	public function __toString(): string {
		return \sprintf('azure+api://%s', $this->endpoint);
	}
}
