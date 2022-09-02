<?php

declare(strict_types=1);

namespace Core;

use PDO;
use PDOStatement;
use Wrapper\DatabaseWrapper;

class Database implements DatabaseWrapper
{
    protected PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    private function getTableColumns(array $fields): array {
        return json_decode(
            str_replace(
                '-',
                '_',
                json_encode(array_keys($fields)
        )));
    }

    private function sanitizeValue(string $value): string {
        return trim(stripslashes(htmlspecialchars($value)));
    }

    private function convertValue(mixed $value): string|int|null
    {
        if (is_string($value)) {
            $sanitizedValue = $this->sanitizeValue($value);

            if (preg_match('/^\d+$/', $sanitizedValue)) {
                return (int) $sanitizedValue;
            }

            return $sanitizedValue;
        }

        return NULL;
    }

    private function getTableValues(array $fields): array {
        return array_map(
            fn($value) => $this->convertValue($value),
            array_values($fields)
        );
    }

    private function checkRequiredFields(array $fields): void {
        if (empty($fields['id'])) {
            echo "Ошибка! Отсутствует id записи таблицы '$this->tableName' c полями:" . PHP_EOL;
            foreach($fields as $key => $value) {
                echo "[$key] => $value" . PHP_EOL;
            }
            exit(1);
        }

        foreach($this->requiredFields as $requiredField) {
            if (empty($fields[$requiredField])) {
                echo 'Ошибка! Отсутствует обязательное значение для поля'
                    . " '$requiredField'"
                    . " записи c id = {$fields['id']}"
                    . " таблицы '$this->tableName'.";
                exit(1);
            }
        }
    }

    private function checkQueryError(PDOStatement $query): void
    {
        $errInfo = $query->errorInfo();

        if ($errInfo[0] !== PDO::ERR_NONE) {
            echo 'Ошибка при выполнении запроса: '
                . $errInfo[2];
            exit(1);
        }
    }

    private function query(string $sql, array $params = [])
    {
        $type = $params['sql_query_type'] ?? 'find';
        unset($params['sql_query_type']);

        $query = $this->db->prepare($sql);

        $query->execute($params);

        $this->checkQueryError($query);

        switch ($type) {
            case 'find':
                return $query;
            case 'insert':
                return $this->find((int) $this->db->lastInsertId());
            case 'update':
                return $this->find($params['id']);
            case 'delete':
                return $query->rowCount() > 0;
            default:
                echo "Ошибка! Неизвестный тип запроса '$type'.";
                exit(1);
        }
    }

    private function insert(array $tableColumns, array $values): array
    {
        $sql = "INSERT INTO $this->tableName ("
            . implode(', ', $tableColumns)
            . ") VALUES (:"
            . implode(', :', $tableColumns)
            . ")";

        $params = array_combine($tableColumns, $values);
        $params['sql_query_type'] = 'insert';

        return $this->query($sql, $params);
    }

    private function find(int $id): array
    {
        $sql = "SELECT * FROM $this->tableName WHERE $this->primaryField=:id";

        return $this->query($sql, ['id' => $id])->fetchAll()[0] ?? [];
    }

    private function getAllIDs(): array
    {
        $sql = "SELECT id FROM $this->tableName";

        return array_map(
            fn($value): int => $value['id'],
            $this->query($sql)->fetchAll()
        );
    }

    private function update(array $tableColumns, array $values): array
    {
        $params = array_combine($tableColumns, $values);
        $params['sql_query_type'] = 'update';

        $fieldsSet = implode(',', array_map(
            fn($value) => $value.'=:'.$value,
            $tableColumns,
        ));

        $sql = "UPDATE $this->tableName SET $fieldsSet WHERE $this->primaryField=:id";

        return $this->query($sql, $params);
    }

    public function updateOrCreate(array $fields): int {
        $tableColumns = $this->getTableColumns($fields);

        $tableValues = $this->getTableValues($fields);

        $convertedFields = array_combine($tableColumns, $tableValues);

        $this->checkRequiredFields($convertedFields);

        $id = $convertedFields['id'];

        $record = $this->find($id);

        if (empty($record)) {
            return $this->insert($tableColumns, $tableValues)['id'];
        }

        return $this->update($tableColumns, $tableValues)['id'];
    }

    public function deleteNotInRange(array $IDsRange): bool
    {
        $IDs = $this->getAllIDs();

        $IDsToDelete = array_diff($IDs, $IDsRange);

        $sql = "DELETE FROM $this->tableName WHERE id IN ("
            . implode(', ', $IDsToDelete)
            .')';

        $params = [
            'sql_query_type' => 'delete'
        ];

        return $this->query($sql, $params);
    }
}
