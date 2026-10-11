<?php

namespace app\widgets;

use app\assets\LightboxAsset;
use app\enums\PhotoStatus;
use app\models\PlantingPhoto;
use Yii;
use yii\base\Widget;
use yii\helpers\Html;
use yii\helpers\Url;

/**
 * Хронологическая лента фото с датами, просмотр в лайтбоксе.
 * В режиме владельца показываются и неодобренные фото с пометкой, кнопки «обложка»/«удалить».
 */
class PhotoTimelineWidget extends Widget
{
    /** @var PlantingPhoto[] */
    public array $photos = [];
    public bool $ownerMode = false;
    public ?int $mainPhotoId = null;
    public string $emptyText = 'Фотографий пока нет.';

    public function run(): string
    {
        if (!$this->photos) {
            return Html::tag('p', Html::encode($this->emptyText), ['class' => 'text-muted']);
        }
        LightboxAsset::register($this->view);
        $this->view->registerJs("if (window.GLightbox) { GLightbox({selector: '.photo-timeline a.glightbox'}); }");

        $f = Yii::$app->formatter;
        $items = [];
        foreach ($this->photos as $photo) {
            $date = $f->asDate($photo->taken_at, 'long');
            $img = Html::img($photo->getThumbUrl(), [
                'alt' => 'Фото от ' . $date,
                'loading' => 'lazy',
                'class' => 'img-fluid rounded',
            ]);
            $link = $photo->isProcessed()
                ? Html::a($img, $photo->getPublicUrl(), ['class' => 'glightbox', 'data-gallery' => 'tree-' . $photo->planting_id, 'data-title' => $date])
                : Html::tag('div', $img . Html::tag('span', 'Обрабатывается…', ['class' => 'photo-processing']), ['class' => 'position-relative']);

            $meta = Html::tag('div', Html::tag('i', '', ['class' => 'bi bi-calendar3']) . ' ' . $date, ['class' => 'small text-muted mt-1']);

            if ($this->ownerMode) {
                if (!$photo->isApproved()) {
                    $meta .= ' ' . StatusBadgeWidget::widget(['status' => $photo->status, 'enum' => PhotoStatus::class]);
                    if ($photo->moderation_comment) {
                        $meta .= Html::tag('div', Html::encode($photo->moderation_comment), ['class' => 'small text-danger']);
                    }
                }
                $buttons = [];
                if ($photo->isApproved() && (int)$photo->id !== (int)$this->mainPhotoId) {
                    $buttons[] = Html::a('<i class="bi bi-star"></i>', ['/photo/set-main', 'id' => $photo->id], [
                        'class' => 'btn btn-sm btn-outline-secondary', 'title' => 'Сделать обложкой', 'data-method' => 'post',
                    ]);
                }
                if ((int)$photo->id === (int)$this->mainPhotoId) {
                    $buttons[] = Html::tag('span', '<i class="bi bi-star-fill"></i> Обложка', ['class' => 'badge text-bg-success']);
                }
                $buttons[] = Html::a('<i class="bi bi-trash"></i>', ['/photo/delete', 'id' => $photo->id], [
                    'class' => 'btn btn-sm btn-outline-danger', 'title' => 'Удалить',
                    'data-method' => 'post', 'data-confirm' => 'Удалить фотографию?',
                ]);
                $meta .= Html::tag('div', implode(' ', $buttons), ['class' => 'mt-1 d-flex gap-1 align-items-center']);
            }

            $items[] = Html::tag('div', $link . $meta, ['class' => 'col-6 col-md-4 col-lg-3 photo-timeline__item']);
        }
        return Html::tag('div', implode("\n", $items), ['class' => 'row g-3 photo-timeline']);
    }
}
