<?php

namespace srag\Plugins\OnlyOffice\Utils;

class FileSanitizer
{
    public static function sanitizeFileName(string $fileNameToSanitize): string
    {
        $sanitized_file_name = $fileNameToSanitize;
        $sanitized_file_name = preg_replace('/[ä]+/', 'ae', $sanitized_file_name);
        $sanitized_file_name = preg_replace('/[ü]+/', 'ue', $sanitized_file_name);
        $sanitized_file_name = preg_replace('/[ö]+/', 'oe', $sanitized_file_name);
        $sanitized_file_name = preg_replace('/[ß]+/', 'ss', $sanitized_file_name);
        $sanitized_file_name = preg_replace('/[\s]+/', '_', $sanitized_file_name);
        return preg_replace('/[^a-zA-Z0-9\-_]+/', '', $sanitized_file_name);
    }
}
