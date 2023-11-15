<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Mailer\Bridge\Azure\Tests\Transport;

use Psr\Log\NullLogger;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\Mailer\Bridge\Azure\Transport\AzureApiTransport;
use Symfony\Component\Mailer\Bridge\Azure\Transport\AzureApiTransportArgs;
use Symfony\Component\Mailer\Bridge\Azure\Transport\AzureTransportFactory;
use Symfony\Component\Mailer\Test\TransportFactoryTestCase;
use Symfony\Component\Mailer\Transport\Dsn;
use Symfony\Component\Mailer\Transport\TransportFactoryInterface;

class AzureTransportFactoryTest extends TransportFactoryTestCase {
    private const ENDPOINT = 'my-acs-resource.communication.azure.com';

	public function getFactory(): TransportFactoryInterface {
		return new AzureTransportFactory(null, new MockHttpClient(), new NullLogger());
	}

	public static function supportsProvider(): iterable {
		yield [
			new Dsn('azure', 'default'),
			true
		];
		
		yield [
			new Dsn('azure+api', 'default'),
			true
		];
	}

	public static function createProvider(): iterable {
		$client = new MockHttpClient();
		$logger = new NullLogger();
		
		yield [
			new Dsn('azure+api', self::ENDPOINT, null, self::PASSWORD),
			new AzureApiTransport(new AzureApiTransportArgs(self::ENDPOINT, self::PASSWORD), $client, null, $logger)
		];

		yield [
			new Dsn('azure+api', 'default', null, self::PASSWORD),
			new AzureApiTransport(new AzureApiTransportArgs('default', self::PASSWORD), $client, null, $logger)
		];
	}

	public static function unsupportedSchemeProvider(): iterable
	{
		yield [
			new Dsn('acs', self::ENDPOINT, self::USER, self::PASSWORD),
			'The "acs" scheme is not supported; supported schemes for mailer "azure" are: "azure", "azure+api".'
		];
	}

	public static function incompleteDsnProvider(): iterable
	{
		yield [new Dsn('azure+api', '')];
		yield [new Dsn('azure+api', 'default')];

		yield [new Dsn('azure+api', '', self::USER)];
		yield [new Dsn('azure+api', 'default', self::USER)];

		yield [new Dsn('azure+api', '', null, self::PASSWORD)];
	}
}
