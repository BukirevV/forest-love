<?php

namespace tests\unit\services;

use app\services\SocialNetworkResolver;
use Codeception\Test\Unit;

class SocialNetworkResolverTest extends Unit
{
    private SocialNetworkResolver $resolver;

    protected function _before(): void
    {
        $this->resolver = new SocialNetworkResolver([
            'vk.com' => 'vk', 't.me' => 'telegram', 'youtube.com' => 'youtube', 'youtu.be' => 'youtube', 'ok.ru' => 'ok',
        ]);
    }

    /**
     * @dataProvider urls
     */
    public function testResolve(string $url, string $expected): void
    {
        $this->assertSame($expected, $this->resolver->resolve($url));
    }

    public static function urls(): array
    {
        return [
            ['https://vk.com/id1', 'vk'],
            ['https://m.vk.com/club1', 'vk'],
            ['https://www.vk.com/club1', 'vk'],
            ['http://t.me/channel', 'telegram'],
            ['https://music.youtube.com/watch?v=1', 'youtube'],
            ['https://youtu.be/abc', 'youtube'],
            ['ok.ru/profile/1', 'ok'],
            ['https://my-forest.org', 'site'],
            ['https://notvk.com', 'site'],
            ['мусор', 'site'],
        ];
    }

    public function testIconFallback(): void
    {
        $this->assertSame('bi bi-globe2', SocialNetworkResolver::iconClass('unknown'));
        $this->assertSame('bi bi-telegram', SocialNetworkResolver::iconClass('telegram'));
    }
}
