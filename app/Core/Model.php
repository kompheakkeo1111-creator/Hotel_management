<?php
namespace App\Core;

use PDO;

/**
 * Base model. Wraps the shared PDO connection from config.php.
 */
abstract class Model
{
    /** @var PDO */
    protected $db;

    public function __construct()
    {
        $this->db = getDB();
    }
}
