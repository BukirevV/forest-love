<?php

namespace app\helpers;

use yii\helpers\HtmlPurifier;

/**
 * Дополнительные хелперы вывода.
 */
class Html extends \yii\bootstrap5\Html
{
    /**
     * Очистка HTML маркетингового текста рекламы.
     */
    public static function purifyMarketing(?string $html): string
    {
        return HtmlPurifier::process((string)$html, [
            'HTML.Allowed' => 'p,br,b,strong,i,em,u,ul,ol,li,a[href|title],h3,h4,blockquote,img[src|alt|width|height]',
            'Attr.AllowedFrameTargets' => ['_blank'],
            'HTML.TargetBlank' => true,
            'HTML.Nofollow' => true,
            'URI.AllowedSchemes' => ['http' => true, 'https' => true, 'mailto' => true],
        ]);
    }

    /**
     * Бейдж статуса по enum.
     */
    public static function statusBadge(\BackedEnum|string|null $status, string $enumClass = ''): string
    {
        if (is_string($status) && $enumClass !== '') {
            $status = $enumClass::tryFrom($status);
        }
        if (!$status instanceof \BackedEnum) {
            return '';
        }
        /** @var \app\enums\EnumLabelsTrait $status */
        return self::tag('span', self::encode($status->label()), ['class' => 'badge text-bg-' . $status->cssClass()]);
    }
}
