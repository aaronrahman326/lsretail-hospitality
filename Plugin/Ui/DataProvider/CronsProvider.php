<?php

namespace Ls\Hospitality\Plugin\Ui\DataProvider;


/**
 * Class ProductDataProvider
 */
class CronsProvider
{

    /**
     * This is being used in Hospitality module, so do not change the structure of it.
     * @return mixed
     */
    public function afterReadCronFile(\Ls\Replication\Ui\DataProvider\CronsProvider $subject, $result)
    {
        try {
            $filePath        = $subject->moduleDirReader->getModuleDir('etc', 'Ls_Hospitality') . '/crontab.xml';
            $parsedArray     = $subject->parser->load($filePath)->xmlToArray();
            $hospitalityJobs[] = $parsedArray['config']['_value']['group'];
            // merge both data.
            return array_merge($hospitalityJobs, $result);
        } catch (\Exception $e) {
            // just return base data.
            return $result;
        }
    }
}