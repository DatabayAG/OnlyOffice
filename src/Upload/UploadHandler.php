<?php

/**
 * This file is part of ILIAS, a powerful learning management system
 * published by ILIAS open source e-Learning e.V.
 *
 * ILIAS is licensed with the GPL-3.0,
 * see https://www.gnu.org/licenses/gpl-3.0.en.html
 * You should have received a copy of said license along with the
 * source code, too.
 *
 * If this is not the case or you just want to try ILIAS, you'll find
 * us at:
 * https://www.ilias.de
 * https://github.com/ILIAS-eLearning
 *
 *********************************************************************/

declare(strict_types=1);

namespace ILIAS\Plugin\OnlyOffice\Upload;

use ilCtrlInterface;
use ILIAS\DI\Container;
use ILIAS\UI\Component\Input\Field\UploadHandler as UploadHandlerInterface;
use ILIAS\FileUpload\Handler\FileInfoResult;
use ILIAS\FileUpload\Handler\BasicFileInfoResult;
use Closure;
use ILIAS\LegalDocuments\Value\DocumentContent;
use ILIAS\Data\Result\Ok;
use ILIAS\Data\Result;
use ilObjOnlyOfficeGUI;

class UploadHandler implements UploadHandlerInterface
{
    private Container $dic;
    private ilCtrlInterface $ctrl;

    public function __construct(
    ) {
        global $DIC;
        $this->dic = $DIC;
        $this->ctrl = $this->dic->ctrl();
    }

    public function getFileIdentifierParameterName(): string
    {
        return UploadHandlerInterface::DEFAULT_FILE_ID_PARAMETER;
    }

    public function getUploadURL(): string
    {
        return $this->ctrl->getLinkTargetByClass(ilObjOnlyOfficeGUI::class, "uploadFile");
    }

    public function getFileRemovalURL(): string
    {
        return "";
    }

    public function getExistingFileInfoURL(): string
    {
        return "";
    }

    public function getInfoForExistingFiles(array $file_ids): array
    {
        return [];
    }

    public function getInfoResult(string $identifier): ?FileInfoResult
    {
        return new BasicFileInfoResult(
            $this->getFileIdentifierParameterName(),
            $identifier, "", 20, "");
    }

    public function supportsChunkedUploads(): bool
    {
        return false;
    }
}
