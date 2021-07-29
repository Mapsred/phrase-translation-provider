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

    public function __construct(
        private HttpClientInterface $client,
        private LoggerInterface $logger,
        private LoaderInterface $loader
    ) {
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

        return new PhraseProvider($client, $this->loader, $this->logger, $endpoint);
    }

    protected function getSupportedSchemes(): array
    {
        return ['phrase'];
    }
}
