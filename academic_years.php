<?php
declare(strict_types=1);

function available_academic_years(PDO $db): array
{
    $currentYear = (int) date('Y');
    $lastStartYear = max(2025, $currentYear + 1);
    $insert = $db->prepare(
        'INSERT IGNORE INTO academic_years (start_year, end_year)
         VALUES (:start_year, :end_year)'
    );

    for ($startYear = 2025; $startYear <= $lastStartYear; $startYear++) {
        $insert->execute([
            'start_year' => $startYear,
            'end_year' => $startYear + 1,
        ]);
    }

    $years = $db->query(
        'SELECT start_year, end_year
         FROM academic_years
         WHERE start_year >= 2025
         ORDER BY start_year ASC'
    )->fetchAll();

    return array_map(
        static fn (array $year): string => $year['start_year'] . '-' . $year['end_year'],
        $years
    );
}

function recent_graduation_years(): array
{
    $currentStartYear = (int) date('Y') - 1;
    $years = [];

    for ($startYear = $currentStartYear; $startYear > $currentStartYear - 10; $startYear--) {
        $years[] = $startYear . '-' . ($startYear + 1);
    }

    return $years;
}
