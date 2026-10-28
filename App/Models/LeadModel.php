<?php

namespace App\Models;

use App\Database;

class LeadModel
{
    private Database $db;

    public function __construct(?Database $db = null)
    {
        if ($db !== null) {
            $this->db = $db;
        }
    }

    private function getDb(): Database
    {
        if (!isset($this->db)) {
            $this->db = Database::getInstance();
        }
        return $this->db;
    }

    /**
     * Create a new lead.
     *
     * @param array $data Contains 'name', 'email', 'phone', 'company', 'message'
     * @return int Inserted lead ID
     */
    public function create(array $data): int
    {
        $sql = "INSERT INTO leads (name, email, phone, company, message, created_at)
                VALUES (:name, :email, :phone, :company, :message, :created_at)";

        $params = [
            ':name'       => $data['name'] ?? '',
            ':email'      => $data['email'] ?? '',
            ':phone'      => $data['phone'] ?? null,
            ':company'    => $data['company'] ?? null,
            ':message'    => $data['message'] ?? null,
            ':created_at' => date('Y-m-d H:i:s'),
        ];

        $db = $this->getDb();
        $stmt = $db->prepare($sql);
        $stmt->execute($params);

        return (int)$db->lastInsertId();
    }

    /**
     * Find a lead by ID.
     *
     * @param int $id
     * @return array|false
     */
    public function find(int $id): array|false
    {
        $sql = "SELECT * FROM leads WHERE id = :id LIMIT 1";
        return $this->getDb()->fetch($sql, [':id' => $id]);
    }

    /**
     * Find all leads.
     *
     * @return array
     */
    public function all(): array
    {
        $sql = "SELECT * FROM leads ORDER BY created_at DESC";
        return $this->getDb()->fetchAll($sql);
    }
}
