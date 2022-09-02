<?php

declare(strict_types=1);

use Models\Offer;

require_once('autoload.php');

// Принимаем путь до файла
echo 'Парсер XML-выгрузки запущен.'. PHP_EOL;

$query = PHP_EOL
    . 'Введите, пожалуйста, путь до файла с расширением xml'
    . PHP_EOL . '(например: ./folder/file.xml):'
    . PHP_EOL . '> ';

$error = 'Ошибка! Введён некорректный путь.'
    . PHP_EOL
    . 'Обратите внимание, недопустимые знаки: \, :, *, ?, ", <, >, |.'
    . PHP_EOL
    . PHP_EOL;

$filePathPattern = '^./[^<>:"\\|?*]+.xml$';

$input = '';

do {
    if ($input !== '') {
        fwrite(STDERR, $error);
    }

    echo $query;

    $input = trim(fgets(STDIN));

    if ($input === '') {
        $input = './data_light.xml';
        break;
    }

    $inputIsValid = mb_ereg_match($filePathPattern, $input);
} while ($inputIsValid === false);

echo "Выбран путь до файла: $input" . PHP_EOL;

// Считываем данные из файла
$dataset = simplexml_load_string(file_get_contents($input)) ?? die('Ошибка загрузки файла.');

$offers = $dataset->offers[0];

// Подключаем БД и создаём экземпляр модели Offer
$db = new PDO('sqlite:db.sqlite');
$db->setAttribute(
    PDO::ATTR_DEFAULT_FETCH_MODE,
    PDO::FETCH_ASSOC
);

$offer = new Offer($db);

// Парсим, сохраняя id каждой новой/отредактированной записи
$IDs = [];

foreach($offers as $item) {
    $id = $offer->updateOrCreate((array) $item);

    $IDs[] = $id;
}

// Удаляем все записи из БД, которых нет в XML
$offer->deleteNotInRange($IDs);

echo 'Парсинг файла успешно завершён.';
