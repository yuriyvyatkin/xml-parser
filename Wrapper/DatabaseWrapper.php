<?php

declare(strict_types=1);

namespace Wrapper;

interface DatabaseWrapper
{
    // добавление новой записи в таблицу или обновление существующей
    public function updateOrCreate(array $fields): int;

    // удаление строк вне диапазона переданных id
    public function deleteNotInRange(array $IDsRange): bool;
}
