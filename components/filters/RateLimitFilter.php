<?php

namespace app\components\filters;

use app\services\RateLimitService;
use Yii;
use yii\base\ActionFilter;
use yii\web\TooManyRequestsHttpException;

/**
 * Ограничение частоты запросов к действиям по IP (+ ID пользователя, если авторизован).
 *
 * 'rateLimit' => [
 *     'class' => RateLimitFilter::class,
 *     'only' => ['login'],
 *     'limits' => ['login' => [10, 600]], // [запросов, окно в секундах]
 *     'methods' => ['POST'],
 * ]
 */
class RateLimitFilter extends ActionFilter
{
    /** @var array<string, array{0:int, 1:int}> */
    public array $limits = [];
    /** Учитывать только эти HTTP-методы. */
    public array $methods = ['POST'];

    public function beforeAction($action): bool
    {
        $request = Yii::$app->request;
        if (!in_array($request->method, $this->methods, true) || !isset($this->limits[$action->id])) {
            return parent::beforeAction($action);
        }
        [$limit, $window] = $this->limits[$action->id];
        $key = $action->uniqueId . ':' . $request->userIP . ':' . (Yii::$app->user->id ?? 'guest');

        $service = Yii::$container->get(RateLimitService::class);
        if (!$service->hit($key, $limit, $window)) {
            throw new TooManyRequestsHttpException('Слишком много запросов. Попробуйте позже.');
        }
        return parent::beforeAction($action);
    }
}
