<?php

namespace app\controllers;

use app\components\sms\smsaero\SmsAeroMessage;
use Yii;
use yii\filters\VerbFilter;
use yii\web\Controller;
use yii\web\NotFoundHttpException;
use yii\web\Response;

/**
 * Приём статусов доставки SMS от SMS Aero (POST на callbackUrl).
 * У SMS Aero нет подписи запросов, поэтому адрес защищён секретным токеном SMSAERO_CALLBACK_TOKEN.
 * SMS Aero повторяет запрос в течение суток, пока не получит HTTP 200.
 */
class SmsCallbackController extends Controller
{
    public $enableCsrfValidation = false;

    public function behaviors(): array
    {
        return [
            'verbs' => ['class' => VerbFilter::class, 'actions' => ['index' => ['post']]],
        ];
    }

    public function actionIndex(string $token): array
    {
        Yii::$app->response->format = Response::FORMAT_JSON;

        $expected = (string)($_ENV['SMSAERO_CALLBACK_TOKEN'] ?? '');
        if ($expected === '' || !hash_equals($expected, $token)) {
            throw new NotFoundHttpException();
        }

        $request = Yii::$app->request;
        $data = $request->getBodyParams();
        if (!$data) {
            $data = (array)json_decode((string)$request->getRawBody(), true);
        }
        $message = SmsAeroMessage::fromArray($data);

        $text = sprintf('SMS Aero callback: #%d — %s (%s)', $message->id, $message->statusLabel(), $message->extendStatus);
        $message->isFailed() ? Yii::warning($text, 'sms') : Yii::info($text, 'sms');

        return ['success' => true];
    }
}
