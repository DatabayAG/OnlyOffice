<?php

namespace srag\Plugins\OnlyOffice\StorageService\Infrastructure\File;

use srag\Plugins\OnlyOffice\StorageService\Infrastructure\Common\UUID;
use srag\Plugins\OnlyOffice\StorageService\DTO\File;

class ilDBFileRepository implements FileRepository
{
    public function create(UUID $file_uuid, int $obj_id, string $title, string $file_type, string $mime_type): void
    {
        $file_AR = new FileAR();
        $file_AR->setUUID($file_uuid);
        $file_AR->setObjId($obj_id);
        $file_AR->setTitle($title);
        $file_AR->setFileType($file_type);
        $file_AR->setMimeType($mime_type);
        $file_AR->create();
    }

    public function getFile(int $obj_id): ?File
    {
        $file_ar = FileAR::where(['obj_id' => $obj_id])->first();
        if (is_null($file_ar)) {
            return null;
        }
        return $this->buildFileFromAR($file_ar);
    }

    protected function buildFileFromAR(FileAR $ar): File
    {
        $uuid = $ar->getUUID();
        $obj_id = $ar->getObjId();
        $title = $ar->getTitle();
        $file_type = $ar->getFileType();
        $mime_type = $ar->getMimeType();
        return new File($uuid, $obj_id, $title, $file_type, $mime_type);

    }

    public function getAR(int $file_id): \ActiveRecord
    {
        return FileAR::where(['obj_id' => $file_id])->first();
    }

    public function getAllFiles(): array
    {
        $ars = FileAR::get();
        $result = [];
        /** @var FileAR $ar */
        foreach ($ars as $ar) {
            array_push($result, $this->buildFileFromAR($ar));
        }
        return $result;
    }
}
