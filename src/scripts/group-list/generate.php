<?php

use PhpOffice\PhpSpreadsheet\IOFactory;
use Src\Config\AppConfig;
use Src\Config\SheetProcessingConfig;
use Src\Models\Group;
use Src\Models\Sheet;
use Src\Support\Helpers;
use Src\Support\Security;

$contentTemplate =
'<?php

return {{groupNames}};
';

$links = Helpers::getScheduleFilesLinks();

$config = AppConfig::getInstance();
foreach ($config->samples as $sample) {
    $samplePath = ROOT . '/public/samples/' . $sample;
    $links[] = [
        'uri' => $samplePath,
    ];
}

$groupNames = [];
foreach ($links as $link) {
    if (Helpers::isExternalLink($link['uri'])) {
        $scheduleLink = Security::sanitizeString($link['uri'], true);
        $scheduleLink = Helpers::sanitizeScheduleLink($scheduleLink);

        $data = Helpers::httpGet($scheduleLink);

        if (empty($data)) {
            continue;
        }

        $temp = tmpfile();
        fwrite($temp, $data);

        $filePath = stream_get_meta_data($temp)['uri'];
    } else {
        $filePath = $link['uri'];
    }

    try {
        $reader = IOFactory::createReaderForFile($filePath)->setReadDataOnly(true);
        $spreadsheet = $reader->load($filePath);
    } catch(\PhpOffice\PhpSpreadsheet\Reader\Exception $e) {
        continue;
    }

    var_dump("Process [{$link['uri']}]...");

    foreach ($spreadsheet->getAllSheets() as $worksheet) {
        $sheet = new Sheet($worksheet, new SheetProcessingConfig([
            'processGroups' => false,
        ]));

        /** @var Group $group */
        foreach ($sheet->getGroups() as $group) {
            $groupNames[] = $group->getName();
        }
    }

    var_dump('...done');
}

var_dump('All links and files processed');

$groupListFile = ROOT . '/src/Config/group-list.php';

$existingGroups = require $groupListFile;
$groupNames = array_merge($groupNames, $existingGroups);

$groupNames = array_filter($groupNames);
$groupNames = array_unique($groupNames);
sort($groupNames);
$groupNames = array_values($groupNames);

$written = file_put_contents(
    $groupListFile,
    str_replace('{{groupNames}}', var_export($groupNames, true), $contentTemplate)
);

if ($written) {
    var_dump('File successfully generated.');
} else {
    var_dump('FILE GENERATION ERROR!');
}