<?php
declare(strict_types=1);

use Src\Config\AppConfig;
use Src\Exceptions\TerminateException;
use Src\Support\Helpers;
use Src\Support\Security;

$fileName = Security::filterInputString(INPUT_GET, 'f');
$fileName = Security::sanitizeCsvFilename($fileName);

if ($fileName === '') {
    throw new TerminateException('GET param "f" is required');
}

$visitsStorageFile = dirname(AppConfig::getInstance()->visitsStorageFileTemplate)."/$fileName";

@unlink($visitsStorageFile);

Helpers::goToLocation('/system/visits');