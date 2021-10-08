<?php

namespace Src\Models;

use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use Src\Config\Config;
use Src\Config\ExcelConfig;
use Src\Config\SheetProcessingConfig;
use Src\Support\Collection;
use Src\Support\Str;

class Sheet
{
    private Worksheet $worksheet;
    private Config $config;
    private ExcelConfig $excelConfig;
    private SheetProcessingConfig $sheetProcessingConfig;

    private bool $isProcessed = false;

    private ?string $groupColumn = null;
    private bool $hasMendeleeva4 = false;

    private Collection $groups;

    /**
     * @param Worksheet $worksheet
     * @param SheetProcessingConfig $sheetProcessingConfig
     */
    public function __construct(Worksheet $worksheet, SheetProcessingConfig $sheetProcessingConfig)
    {
        $this->worksheet                = clone $worksheet;

        $this->config                   = Config::getInstance();
        $this->sheetProcessingConfig    = $sheetProcessingConfig;
        $this->excelConfig              = new ExcelConfig($this);

        $this->groups                   = new Collection();

        $this->init();
        $this->process();
    }

    /**
     * Start Sheet processing:
     * recognize and add Groups.
     */
    private function process()
    {
        if (!$this->isProcessable()) {
            return;
        }

        $columns = $this->getColumnsRange();
        $rows = $this->getRowsRange();

        $conf = &$this->sheetProcessingConfig;
        $hasFilterByGroup = $conf->studentsGroup !== null;

        foreach ($columns as $column) {
            // Optimization: we are already found and processed selected group.
            if ($hasFilterByGroup && $this->isGroupColumnFound()) {
                break;
            }

            $groupName = $this->getGroupNameByColumn($column);

            // Apply filter by student's group
            if ($hasFilterByGroup && $conf->studentsGroup !== $groupName) {
                continue;
            }

            if ($hasFilterByGroup) {
                $this->setGroupColumn($column);
            }

            $this->addGroup($column, $groupName, $rows);
        }

        // All done, mark sheet as processed
        $this->isProcessed = true;
    }

    public function getGroups()
    {
        return $this->groups;
    }

    public function getWorksheet(): Worksheet
    {
        return $this->worksheet;
    }

    public function getTitle()
    {
        return trim($this->worksheet->getTitle());
    }

    /**
     * @param string $coordinate
     * @param bool $rawValue
     * @return string
     */
    public function getCellValue(string $coordinate, bool $rawValue = false): string
    {
        $cellValue = (string) $this->worksheet->getCell($coordinate);

        if ($rawValue) {
            return $cellValue;
        }

        return trim($cellValue);
    }

    /**
     * @return bool
     */
    private function isProcessable(): bool
    {
        return $this->excelConfig->isProcessable();
    }

    /**
     * @return bool
     */
    public function isProcessed():bool
    {
        return $this->isProcessed;
    }

    public function getTimeColumn(): ?string
    {
        return $this->excelConfig->timeCol;
    }

    /**
     * @return bool
     */
    public function isGroupColumnFound(): bool
    {
        return $this->groupColumn !== null;
    }

    private function setGroupColumn(string $column)
    {
        $this->groupColumn = $column;
    }

    /**
     * Resolve Excel config, detect "Has Mendeleeva 4 house".
     */
    private function init()
    {
        $firstColumn = 'A';
        $firstRow = 1;

        $highestColRow = $this->worksheet->getHighestRowAndColumn();
        $highestColumn = $highestColRow['column'];
        $highestRow = $highestColRow['row'];

        $columns = $this->generateColumnsRange($firstColumn, $highestColumn);
        $rows = $this->generateRowsRange($firstRow, $highestRow);

        $excelConfig = &$this->excelConfig;

        foreach ($columns as $column) {
            foreach ($rows as $row) {
                $coordinate = $column.$row;

                $rawCellValue = $this->getCellValue($coordinate, true);
                $cellValue = trim($rawCellValue);

                /*
                 * Resolve Excel config
                 */

                if (!$excelConfig->isProcessable()) {
                    $cleanCellValue = Str::lower(Str::replaceManySpacesWithOne($cellValue));

                    if (in_array($cleanCellValue, $this->config->dayWords)) {
                        $excelConfig->dayCol           = $column;
                        $excelConfig->groupNamesRow    = $row;
                        $excelConfig->firstScheduleRow = nextRow($excelConfig->groupNamesRow);
                    } elseif (in_array($cleanCellValue, $this->config->timeWords)) {
                        $excelConfig->timeCol          = $column;
                        $excelConfig->firstGroupCol    = nextColumn($excelConfig->timeCol);
                        $excelConfig->groupNamesRow    = $row;
                        $excelConfig->firstScheduleRow = nextRow($excelConfig->groupNamesRow);
                    } else if (isClassHourLesson($rawCellValue)) {
                        $excelConfig->classHourLessonColumn = $column;
                    }
                }

                /*
                 * Detect "Has Mendeleeva 4 house"
                 */

                if ($this->sheetProcessingConfig->forceMendeleeva4) {
                    $this->hasMendeleeva4 = true;
                }

                if (!$this->hasMendeleeva4 && $cellValue) {
                    if (
                        Str::contains(Str::lower($cellValue), 'менделеева') &&
                        Str::contains($cellValue, '4')
                    ) {
                        $this->hasMendeleeva4 = true;
                    }
                }
            }
        }

        $excelConfig->lastGroupCol = $highestColumn;
        $excelConfig->lastScheduleRow = $highestRow;
        if ($excelConfig->classHourLessonColumn === null) {
            $excelConfig->classHourLessonColumn = false;
        }
    }

    /**
     * Get processable (potentially with lessons)
     * columns range.
     *
     * @return array
     */
    private function getColumnsRange(): array
    {
        $start = $this->excelConfig->firstGroupCol;
        $end = $this->excelConfig->lastGroupCol;

        return $this->generateColumnsRange($start, $end);
    }

    /**
     * Get processable (potentially with lessons)
     * rows range.
     *
     * @return array
     */
    private function getRowsRange(): array
    {
        $start = $this->excelConfig->firstScheduleRow;
        $end = $this->excelConfig->lastScheduleRow;

        return $this->generateRowsRange($start, $end);
    }

    private function containsClassHourLesson(string $rawCellValue): bool
    {
        if (empty($rawCellValue)) {
            return false;
        }

        return $this->formatClassHourLesson($rawCellValue) === 'Классный час';
    }

    private function formatClassHourLesson(string $rawCellValue): string
    {
        $lesson = trim($rawCellValue);

        if (empty($lesson)) {
            return '';
        }

        $space = ' ';
        $uniqueChar = '|';

        $spacesCount = 10;
        $replacementPerformed = false;
        do {
            $lesson = str_replace(str_repeat($space, $spacesCount), $uniqueChar, $lesson, $count);

            if ($count > 0) {
                $replacementPerformed = true;
            }

            $spacesCount--;
        } while($count === 0 && $spacesCount > 0);

        $lesson = Str::removeSpaces($lesson);

        if ($replacementPerformed) {
            $lesson = str_replace($uniqueChar, $space, $lesson);
        }

        $lesson = Str::lower($lesson);
        return Str::ucfirst($lesson);
    }

    /**
     * @param string $start
     * @param string $end
     * @return array
     */
    private function generateColumnsRange(string $start, string $end): array
    {
        $end++;
        $letters = [];
        while ($start !== $end) {
            $letters[] = $start++;
        }
        return $letters;
    }

    /**
     * @param int $start
     * @param int $end
     * @return array
     */
    private function generateRowsRange(int $start, int $end): array
    {
        return range($start, $end);
    }

    public function getGroupNameByColumn(string $column)
    {
        return $this->getCellValue($column.$this->excelConfig->groupNamesRow);
    }

    /**
     * @param string $groupColumn
     * @param string $groupName
     * @param array $rows
     */
    public function addGroup(string $groupColumn, string $groupName, array $rows)
    {
        $group = new Group($groupColumn, $groupName, $this);
        $group->process($rows);

        $this->groups->put($groupColumn, $group);
    }

    /**
     * @return bool
     */
    public function hasClassHourLessonColumn(): bool
    {
        return !empty($this->excelConfig->classHourLessonColumn);
    }

    /**
     * @return string|null
     */
    public function getClassHourLessonColumn(): ?string
    {
        return empty($this->excelConfig->classHourLessonColumn) ? null : $this->excelConfig->classHourLessonColumn;
    }
}