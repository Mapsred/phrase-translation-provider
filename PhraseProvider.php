<?php

namespace Symfony\Component\Translation\Bridge\Phrase;

use Psr\Log\LoggerInterface;
use Symfony\Component\Translation\Exception\ProviderException;
use Symfony\Component\Translation\Loader\LoaderInterface;
use Symfony\Component\Translation\Provider\ProviderInterface;
use Symfony\Component\Translation\TranslatorBag;
use Symfony\Component\Translation\TranslatorBagInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class PhraseProvider implements ProviderInterface
{
    private $client;
    private $loader;
    private $logger;
    private $defaultLocale;
    private $endpoint;

    public function __construct(HttpClientInterface $client, LoaderInterface $loader, LoggerInterface $logger, string $defaultLocale, string $endpoint)
    {
        $this->client = $client;
        $this->loader = $loader;
        $this->logger = $logger;
        $this->defaultLocale = $defaultLocale;
        $this->endpoint = $endpoint;
    }


    public function __toString(): string
    {
        return sprintf('phrase://%s', $this->endpoint);
    }

    public function write(TranslatorBagInterface $translatorBag): void
    {
        // TODO handle locale creation
        foreach ($translatorBag->getCatalogues() as $catalogue) {
            foreach ($catalogue->all() as $domain => $translations) {
                $existingKeys = $this->getExistingKeys($domain, $catalogue->getLocale());

                $notExistingKeys = array_keys(array_diff_key($translations, $existingKeys));
                $createdIds = $this->pushNewKeys($notExistingKeys, $domain);

                $existingKeys = array_merge($existingKeys, array_combine($notExistingKeys, $createdIds));
                $translations = $this->getKeyTranslation($existingKeys, $translations);
                $this->pushNewTranslations($translations, $catalogue->getLocale(), $domain);
            }
        }
    }

    public function read(array $domains, array $locales): TranslatorBag
    {
        $domains = $domains ?: ['messages'];
        $translatorBag = new TranslatorBag();

        $responses = [];
        foreach ($locales as $locale) {
            foreach ($domains as $domain) {
                $responses[] = [
                    'response' => $this->client->request('GET', "locales/$locale/download", [
                        'query' => [
                            'file_format' => 'symfony_xliff',
                            //'tags' => $domain, // TODO in igraal
                        ],
                    ]),
                    'locale' => $locale,
                    'domain' => $domain,
                ];
            }
        }

        foreach ($responses as $response) {
            $locale = $response['locale'];
            $domain = $response['domain'];
            $response = $response['response'];

            if (404 === $response->getStatusCode()) {
                $this->logger->warning(sprintf('Locale "%s" for domain "%s" does not exist in Phrase.', $locale, $domain));
                continue;
            }

            $responseContent = $response->getContent(false);

            if (200 !== $response->getStatusCode()) {
                throw new ProviderException('Unable to read the Phrase response: '.$responseContent, $response);
            }

            $translatorBag->addCatalogue($this->loader->load($responseContent, $locale, $domain));
        }


        return $translatorBag;
    }

    public function delete(TranslatorBagInterface $translatorBag): void
    {
        throw new \Error(sprintf('Method %s not implemented', __METHOD__));
    }

    private function getExistingKeys(string $domain, string $locale, int $page = 1, array &$results = []): array
    {
        $queryParams = ['page' => $page, 'per_page' => 5];

        $response = $this->client->request('GET', 'keys', ['query' => $queryParams]);
        $results = array_merge($results, array_combine(
            array_column($response->toArray(), 'name'),
            array_column($response->toArray(), 'id'),
        ));

        $header = $response->getHeaders()['link'][0];
        $headerLinks = explode(',', $header);

        $links = [];
        foreach ($headerLinks as $headerLink) {
            if (true === str_contains($headerLink, 'rel=next')) {
                preg_match('/<https:\/\/.*\?page=([0-9]+)/', $headerLink, $matches);

                return $this->getExistingKeys($domain, $locale, $matches[1], $results);
            }

        }

        return $results;
    }

    private function getKeyTranslation(array $existingKeys, array $translations): array
    {
        $final = [];
        foreach ($translations as $key => $translation) {
            $final[$existingKeys[$key]] = $translation;
        }

        return $final;
    }

    private function pushNewKeys(array $keys, string $domain): array
    {
        $responses = $createdIds = [];

        foreach ($keys as $key) {
            $responses[$key] = $this->client->request('POST', 'keys', [
                'body' => ['name' => strtolower(trim($key))],
            ]);
        }

        foreach ($responses as $key => $response) {
            if (201 !== $response->getStatusCode()) {
                $this->logger->error(sprintf('Unable to add new translation key "%s" to Phrase: (status code: "%s") "%s".', $key, $response->getStatusCode(), $response->getContent(false)));
            } else {
                $createdIds[] = $response->toArray(false)['id'];
            }
        }

        return $createdIds;
    }

    private function pushNewTranslations(array $translations, string $locale, string $domain): void
    {
        $responses = [];
        foreach ($translations as $key => $value) {
            $responses[$key] = $this->client->request('POST', 'translations', [
                'json' => ['key_id' => $key, 'locale_id' => $locale, 'content' => $value],
            ]);
        }

        foreach ($responses as $key => $response) {
            if (201 !== $response->getStatusCode()) {
                $this->logger->error(sprintf('Unable to add new translation "%s" to Phrase: (status code: "%s") "%s".', $key, $response->getStatusCode(), $response->getContent(false)));
            }
        }
    }
}
