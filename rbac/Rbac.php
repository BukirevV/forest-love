<?php

namespace app\rbac;

/**
 * Имена ролей и разрешений (ТЗ 15).
 */
final class Rbac
{
    public const ROLE_USER = 'user';
    public const ROLE_MODERATOR = 'moderator';
    public const ROLE_ADMIN = 'admin';

    public const PLANTING_CREATE = 'plantingCreate';
    public const PLANTING_UPDATE_OWN = 'plantingUpdateOwn';
    public const PLANTING_DELETE_OWN = 'plantingDeleteOwn';
    public const PHOTO_UPLOAD_OWN = 'photoUploadOwn';
    public const PAYMENT_CREATE_OWN = 'paymentCreateOwn';
    public const MODERATE = 'moderate';
    public const MANAGE_SPECIES = 'manageSpecies';
    public const MANAGE_USERS = 'manageUsers';
    public const MANAGE_ADS = 'manageAds';
    public const MANAGE_PAYMENTS = 'managePayments';
    public const MANAGE_SETTINGS = 'manageSettings';

    public const PERMISSIONS = [
        self::PLANTING_CREATE => 'Создание карточки',
        self::PLANTING_UPDATE_OWN => 'Редактирование своей карточки',
        self::PLANTING_DELETE_OWN => 'Удаление своей карточки',
        self::PHOTO_UPLOAD_OWN => 'Догрузка фото в свою карточку',
        self::PAYMENT_CREATE_OWN => 'Оплата именного размещения своей карточки',
        self::MODERATE => 'Доступ к очереди модерации и решениям',
        self::MANAGE_SPECIES => 'Справочник пород',
        self::MANAGE_USERS => 'Пользователи и роли',
        self::MANAGE_ADS => 'Рекламодатели, кампании, размещения',
        self::MANAGE_PAYMENTS => 'Платежи',
        self::MANAGE_SETTINGS => 'Настройки',
    ];

    public const ROLES = [
        self::ROLE_USER => 'Пользователь',
        self::ROLE_MODERATOR => 'Модератор',
        self::ROLE_ADMIN => 'Администратор',
    ];
}
