<?php

namespace tests\unit\validators;

use app\validators\UrlListValidator;
use Codeception\Test\Unit;
use yii\base\DynamicModel;

class UrlListValidatorTest extends Unit
{
    private function validate(mixed $links, int $max = 50): DynamicModel
    {
        $m = new DynamicModel(['links' => $links]);
        $m->addRule('links', UrlListValidator::class, ['max' => $max, 'skipOnEmpty' => false]);
        $m->validate();
        return $m;
    }

    public function testValidListIsNormalized(): void
    {
        $m = $this->validate([
            ['url' => 'vk.com/forest', 'label' => ' Команда '],
            ['url' => 'https://t.me/forest', 'label' => ''],
            ['url' => '', 'label' => 'пустая строка отбрасывается'],
            ['url' => 'https://vk.com/another'],
        ]);
        $this->assertFalse($m->hasErrors(), implode(';', $m->getErrorSummary(true)));
        $this->assertCount(3, $m->links);
        $this->assertSame('https://vk.com/forest', $m->links[0]['url']);
        $this->assertSame('Команда', $m->links[0]['label']);
        $this->assertNull($m->links[1]['label']);
    }

    public function testForbiddenSchemes(): void
    {
        foreach (['javascript:alert(1)', ' JaVaScRiPt:alert(1)', 'data:text/html,1', 'file:///etc/passwd', 'ftp://example.com'] as $url) {
            $m = $this->validate([['url' => $url]]);
            $this->assertTrue($m->hasErrors('links'), $url);
        }
    }

    public function testLimit(): void
    {
        $links = array_map(static fn($i) => ['url' => "https://example.com/{$i}"], range(1, 4));
        $this->assertTrue($this->validate($links, 3)->hasErrors('links'));
        $this->assertFalse($this->validate($links, 4)->hasErrors('links'));
    }

    public function testEmptyIsAllowed(): void
    {
        $m = $this->validate(null);
        $this->assertFalse($m->hasErrors());
        $this->assertSame([], $m->links);
    }
}
