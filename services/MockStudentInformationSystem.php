<?php

require_once __DIR__ . "/StudentInformationSystemInterface.php";

/**
 * Local stand-in for Garden University's external Student Information System.
 */
class MockStudentInformationSystem implements StudentInformationSystemInterface
{
    /** @var array<string, array<string, mixed>> */
    private array $students;

    public function __construct(?string $dataFile = null)
    {
        $dataFile = $dataFile ?? dirname(__DIR__) . "/mockSisStudents.php";

        if (!is_file($dataFile)) {
            throw new RuntimeException("Mock Student Information System data is unavailable.");
        }

        $students = require $dataFile;
        if (!is_array($students)) {
            throw new RuntimeException("Mock Student Information System data is invalid.");
        }

        $this->students = $students;
    }

    public function findStudent(string $gardenStudentId): ?array
    {
        $normalizedId = strtoupper(trim($gardenStudentId));
        return $this->students[$normalizedId] ?? null;
    }
}
