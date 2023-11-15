<?php

namespace Symfony\Component\Mailer\Bridge\Azure\Transport;

final class AzureApiTransportArgs {
	private string $endpoint;
	private string $version;
	private string $key;

	private const DEFAULT_API_VERSION = '2021-10-01-preview';

	public function __construct(string $endpoint, string $key, string $version = null) {
		$this->endpoint = $endpoint;
		$this->key = $key;
		$this->version = $version ?? self::DEFAULT_API_VERSION;
	}

	function getEndpoint(): string {
		return $this->endpoint;
	}

	function getVersion(): string {
		return $this->version;
	}

	function getKey(): string {
		return $this->key;
	}
}
