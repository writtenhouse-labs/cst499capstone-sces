<?php

/**
 * Stub interface for looking up Garden University student records in the Student Information System.
 * A production implementation can replace the mock without changing SCES.
 */
interface StudentInformationSystemInterface
{
    /**
     * @return array<string, mixed>|null Official student record, or null when not found.
     */
    public function findStudent(string $gardenStudentId): ?array;
}
