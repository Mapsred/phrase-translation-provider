<?php

namespace Symfony\Component\Translation\Bridge\Phrase\Tests;

use Symfony\Component\Translation\Bridge\Phrase\PhraseProviderFactory;
use Symfony\Component\Translation\Provider\ProviderFactoryInterface;
use Symfony\Component\Translation\Test\ProviderFactoryTestCase;

class PhraseProviderFactoryTest extends ProviderFactoryTestCase
{
    public function supportsProvider(): iterable
    {
        yield [true, 'phrase://PROJECT_ID:API_TOKEN@default'];
        yield [false, 'somethingElse://PROJECT_ID:API_TOKEN@default'];
    }

    public function createProvider(): iterable
    {
        yield [
            'phrase://https://api.phrase.com/v2/projects/PROJECT_ID/',
            'phrase://PROJECT_ID:ACCESS_TOKEN@default?version=v2',
        ];
    }

    public function unsupportedSchemeProvider(): iterable
    {
        yield ['somethingElse://API_TOKEN@default'];
    }

    public function incompleteDsnProvider(): iterable
    {
        yield ['phrase://default'];
    }

    public function createFactory(): ProviderFactoryInterface
    {
        return new PhraseProviderFactory($this->getClient(), $this->getLogger(), $this->getDefaultLocale(), $this->getLoader());
    }
}
