<?php

namespace Models;

use Core\Database;

class Offer extends Database
{
    protected string $tableName = 'offers';
    protected string $primaryField = 'id';
    protected array $requiredFields = ['mark', 'model', 'year', 'body_type', 'engine_type', 'transmission', 'gear_type'];
}
