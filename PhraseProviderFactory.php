<?php

namespace Symfony\Component\Translation\Bridge\Phrase;

use Psr\Log\LoggerInterface;
use Symfony\Component\Translation\Exception\UnsupportedSchemeException;
use Symfony\Component\Translation\Loader\LoaderInterface;
use Symfony\Component\Translation\Provider\AbstractProviderFactory;
use Symfony\Component\Translation\Provider\Dsn;
use Symfony\Component\Translation\Provider\ProviderInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class PhraseProviderFactory extends AbstractProviderFactory
{
    private const HOST = 'api.phrase.com';

    /** @var LoaderInterface */
    private $loader;

    /** @var HttpClientInterface */
    private $client;

    /** @var LoggerInterface */
    private $logger;

    /** @var string */
    private $defaultLocale;

    public function __construct(HttpClientInterface $client, LoggerInterface $logger, string $defaultLocale, LoaderInterface $loader)
    {
        $this->client = $client;
        $this->logger = $logger;
        $this->defaultLocale = $defaultLocale;
        $this->loader = $loader;
    }


    public function create(Dsn $dsn): ProviderInterface
    {
        if ('phrase' !== $dsn->getScheme()) {
            throw new UnsupportedSchemeException($dsn, 'phrase', $this->getSupportedSchemes());
        }

        $endpoint = 'default' === $dsn->getHost() ? self::HOST : $dsn->getHost();
        $endpoint .= $dsn->getPort() ? ':'.$dsn->getPort() : '';
        $endpoint .= $dsn->getOption('version') ? '/'.$dsn->getOption('version', 'v2') : '';
        $endpoint = 'https://'.$endpoint.'/projects/'.$this->getUser($dsn).'/';

        $client = $this->client->withOptions([
            'base_uri' => $endpoint,
            'timeout' => $dsn->getOption('timeout', 3),
            'headers'=> [
                'Authorization' => 'token '. $this->getPassword($dsn)
            ],
        ]);

        return new PhraseProvider($client, $this->loader, $this->logger, $this->defaultLocale, $endpoint);
    }

    protected function getSupportedSchemes(): array
    {
        return ['phrase'];
    }
}
