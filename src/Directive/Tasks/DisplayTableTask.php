<?php

declare(strict_types=1);

namespace AndyDefer\BestPractices\Directive\Tasks;

use AndyDefer\BestPractices\Collections\TypedRecords;
use AndyDefer\BestPractices\Directive\Records\DisplayTableRecord;

class DisplayTableTask
{
    public function execute(DisplayTableRecord $record): void
    {
        $headers = $this->convertToTypedRecords($record->headers);
        $rows = $this->convertToTypedRecords($record->rows);

        $headers->assertAllOfType('string');

        $widths = [];
        foreach ($headers as $i => $header) {
            $widths[$i] = strlen($header);
            foreach ($rows as $row) {
                $widths[$i] = max($widths[$i], strlen((string) ($row[$i] ?? '')));
            }
        }

        echo $this->formatRow($headers, $widths)."\n";
        echo $this->formatSeparator($widths)."\n";

        foreach ($rows as $row) {
            echo $this->formatRow($row, $widths)."\n";
        }
    }

    private function convertToTypedRecords(array $data): TypedRecords
    {
        $result = new TypedRecords('string', 'int', 'float');
        foreach ($data as $item) {
            if (is_array($item)) {
                $nested = new TypedRecords('string');
                foreach ($item as $value) {
                    $nested->add((string) $value);
                }
                $result->add($nested);
            } else {
                $result->add((string) $item);
            }
        }

        return $result;
    }

    private function formatRow(TypedRecords $row, array $widths): string
    {
        $parts = [];
        $rowArray = $row->toArray();

        foreach ($widths as $i => $width) {
            $value = $rowArray[$i] ?? '';
            if ($value instanceof TypedRecords) {
                $value = implode(', ', $value->toArray());
            }
            $parts[] = str_pad((string) $value, $width);
        }

        return '| '.implode(' | ', $parts).' |';
    }

    private function formatSeparator(array $widths): string
    {
        $parts = [];
        foreach ($widths as $width) {
            $parts[] = str_repeat('-', $width);
        }

        return '|-'.implode('-|-', $parts).'-|';
    }
}
