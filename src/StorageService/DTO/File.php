<?php

namespace ILIAS\Plugin\OnlyOffice\StorageService\DTO;

use ILIAS\Plugin\OnlyOffice\Enum\FileCreationType;
use ILIAS\Plugin\OnlyOffice\StorageService\Infrastructure\Common\UUID;

class File
{
    protected UUID $uuid;
    protected int $obj_id;
    protected string $title;
    protected string $file_type;
    protected string $mime_type;

    public static function determineDocType(string $extension): FileCreationType
    {
        return match ($extension) {
            "pptx", "fodp", "odp", "otp", "pot", "potm", "potx", "pps", "ppsm", "ppsx", "ppt", "pptm" => FileCreationType::PRESENTATION,
            "xlsx", "csv", "fods", "ods", "ots", "xls", "xlsm", "xlt", "xltm", "xltx" => FileCreationType::TABLE,
            "doc", "docx", "dotx", "fb2", "odt", "ott", "rtf", "txt", "pdf", "pdf/a", "html", "epub", "xps", "djvu", "xml", "docxf", "oform" => FileCreationType::TEXT,
            default => FileCreationType::NONE // Should never be reached.
        };

    }

    public function __construct(UUID $uuid, int $obj_id, string $title, string $file_type, string $mime_type)
    {
        $this->uuid = $uuid;
        $this->title = $title;
        $this->file_type = $file_type;
        $this->obj_id = $obj_id;
        $this->mime_type = $mime_type;
    }

    public function getUuid(): UUID
    {
        return $this->uuid;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function getObjId(): int
    {
        return $this->obj_id;
    }

    public function getFileType(): string
    {
        return $this->file_type;
    }

    public function getFileUuid(): UUID
    {
        return $this->uuid;
    }

    public function getMimeType(): string
    {
        return $this->mime_type;
    }

}
