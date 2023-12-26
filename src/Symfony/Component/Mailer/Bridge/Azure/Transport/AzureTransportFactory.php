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

use Symfony\Component\Mailer\Exception\IncompleteDsnException;
use Symfony\Component\Mailer\Exception\UnsupportedSchemeException;
use Symfony\Component\Mailer\Transport\AbstractTransportFactory;
use Symfony\Component\Mailer\Transport\Dsn;
use Symfony\Component\Mailer\Transport\TransportInterface;

final class AzureTransportFactory extends AbstractTransportFactory {
	private const SupportedSchemes = [
		'azure', 'azure+api'
	];
	
	public function create(Dsn $dsn): TransportInterface {
		$scheme = $dsn->getScheme();

		if (!\in_array($scheme, self::SupportedSchemes, true))
			throw new UnsupportedSchemeException($dsn, 'azure', $this->getSupportedSchemes());

		$endpoint = $dsn->getHost();
		$key = $dsn->getPassword();
		$version = $dsn->getOption('api-version');

		if (!$endpoint || !$key)
			throw new IncompleteDsnException();//$dsn, 'azure', $this->getSupportedSchemes());
		
		$args = new AzureApiTransportArgs($endpoint, $key, $version);

		return new AzureApiTransport($args, $this->client, $this->dispatcher, $this->logger);
	}

	protected function getSupportedSchemes(): array {
		return ['azure', 'azure+api'];
	}
}