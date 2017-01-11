<?php

namespace BrowscapHelper\Source;

use Monolog\Logger;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Class DirectorySource
 *
 * @author  Thomas Mueller <mimmi20@live.de>
 */
class BrowscapSource implements SourceInterface
{
    /**
     * @param \Monolog\Logger                                   $logger
     * @param \Symfony\Component\Console\Output\OutputInterface $output
     * @param int                                               $limit
     *
     * @return \Generator
     */
    public function getUserAgents(Logger $logger, OutputInterface $output, $limit = 0)
    {
        $counter   = 0;
        $allAgents = [];

        foreach ($this->loadFromPath($output) as $dataFile) {
            if ($limit && $counter >= $limit) {
                return;
            }

            foreach ($dataFile as $row) {
                if ($limit && $counter >= $limit) {
                    return;
                }

                if (!array_key_exists('ua', $row)) {
                    continue;
                }

                if (array_key_exists($row['ua'], $allAgents)) {
                    continue;
                }

                yield $row['ua'];
                $allAgents[$row['ua']] = 1;
                ++$counter;
            }
        }
    }

    /**
     * @param \Monolog\Logger                                   $logger
     * @param \Symfony\Component\Console\Output\OutputInterface $output
     *
     * @return \Generator
     */
    public function getTests(Logger $logger, OutputInterface $output)
    {
        $allTests = [];

        foreach ($this->loadFromPath($output) as $dataFile) {
            foreach ($dataFile as $row) {
                if (!array_key_exists('ua', $row)) {
                    continue;
                }

                if (array_key_exists($row['ua'], $allTests)) {
                    continue;
                }

                $test = [
                    'ua'         => $row['ua'],
                    'properties' => [
                        'Browser_Name'            => $row['properties']['Browser'],
                        'Browser_Type'            => $row['properties']['Browser_Type'],
                        'Browser_Bits'            => $row['properties']['Browser_Bits'],
                        'Browser_Maker'           => $row['properties']['Browser_Maker'],
                        'Browser_Modus'           => $row['properties']['Browser_Modus'],
                        'Browser_Version'         => $row['properties']['Version'],
                        'Platform_Codename'       => $row['properties']['Platform'],
                        'Platform_Marketingname'  => null,
                        'Platform_Version'        => $row['properties']['Platform_Version'],
                        'Platform_Bits'           => $row['properties']['Platform_Bits'],
                        'Platform_Maker'          => $row['properties']['Platform_Maker'],
                        'Platform_Brand_Name'     => null,
                        'Device_Name'             => $row['properties']['Device_Name'],
                        'Device_Maker'            => $row['properties']['Device_Maker'],
                        'Device_Type'             => $row['properties']['Device_Type'],
                        'Device_Pointing_Method'  => $row['properties']['Device_Pointing_Method'],
                        'Device_Dual_Orientation' => null,
                        'Device_Code_Name'        => $row['properties']['Device_Code_Name'],
                        'Device_Brand_Name'       => $row['properties']['Device_Brand_Name'],
                        'RenderingEngine_Name'    => $row['properties']['RenderingEngine_Name'],
                        'RenderingEngine_Version' => $row['properties']['RenderingEngine_Version'],
                        'RenderingEngine_Maker'   => $row['properties']['RenderingEngine_Maker'],
                    ],
                ];

                yield [$row['ua'] => $test];
                $allTests[$row['ua']] = 1;
            }
        }
    }

    /**
     * @param \Symfony\Component\Console\Output\OutputInterface $output
     *
     * @return \Generator
     */
    private function loadFromPath(OutputInterface $output = null)
    {
        $path = 'vendor/browscap/browscap/tests/fixtures/issues';

        if (!file_exists($path)) {
            return;
        }

        $output->writeln('    reading path ' . $path);

        $iterator = new \RecursiveDirectoryIterator($path);

        foreach (new \RecursiveIteratorIterator($iterator) as $file) {
            /** @var $file \SplFileInfo */
            if (!$file->isFile()) {
                continue;
            }

            $filepath = $file->getPathname();

            $output->write('    reading file ' . str_pad($filepath, 100, ' ', STR_PAD_RIGHT), false);
            switch ($file->getExtension()) {
                case 'php':
                    yield include $filepath;
                    break;
                default:
                    // do nothing here
                    break;
            }
        }
    }
}
