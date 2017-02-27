<?php
/**
 * This file is part of the browscap-helper-source-browscap package.
 *
 * Copyright (c) 2016-2017, Thomas Mueller <mimmi20@live.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types = 1);
namespace BrowscapHelper\Source;

use BrowserDetector\Loader\NotFoundException;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Output\OutputInterface;
use UaDataMapper\BrowserTypeMapper;
use UaDataMapper\BrowserVersionMapper;
use UaDataMapper\DeviceTypeMapper;
use UaDataMapper\EngineVersionMapper;
use UaResult\Browser\Browser;
use UaResult\Company\CompanyLoader;
use UaResult\Device\Device;
use UaResult\Engine\Engine;
use UaResult\Os\Os;
use UaResult\Result\Result;
use Wurfl\Request\GenericRequestFactory;

/**
 * Class DirectorySource
 *
 * @author  Thomas Mueller <mimmi20@live.de>
 */
class BrowscapSource implements SourceInterface
{
    /**
     * @var \Symfony\Component\Console\Output\OutputInterface
     */
    private $output = null;

    /**
     * @var null
     */
    private $logger = null;

    /**
     * @var \Psr\Cache\CacheItemPoolInterface|null
     */
    private $cache = null;

    /**
     * @param \Psr\Log\LoggerInterface                          $logger
     * @param \Symfony\Component\Console\Output\OutputInterface $output
     * @param \Psr\Cache\CacheItemPoolInterface                 $cache
     */
    public function __construct(LoggerInterface $logger, OutputInterface $output, CacheItemPoolInterface $cache)
    {
        $this->logger = $logger;
        $this->output = $output;
        $this->cache  = $cache;
    }

    /**
     * @param int $limit
     *
     * @return string[]
     */
    public function getUserAgents($limit = 0)
    {
        $counter   = 0;
        $allAgents = [];

        foreach ($this->loadFromPath() as $dataFile) {
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
     * @return \UaResult\Result\Result[]
     */
    public function getTests()
    {
        $allTests = [];

        foreach ($this->loadFromPath() as $dataFile) {
            foreach ($dataFile as $row) {
                if (!array_key_exists('ua', $row)) {
                    continue;
                }

                if (array_key_exists($row['ua'], $allTests)) {
                    continue;
                }

                $request = (new GenericRequestFactory())->createRequestForUserAgent($row['ua']);

                try {
                    $browserType = (new BrowserTypeMapper())->mapBrowserType($this->cache, $row['properties']['Browser_Type']);
                } catch (NotFoundException $e) {
                    $this->logger->critical($e);
                    $browserType = null;
                }

                try {
                    $browserMaker = (new CompanyLoader($this->cache))->load($row['properties']['Browser_Maker']);
                } catch (NotFoundException $e) {
                    $this->logger->critical($e);
                    $browserMaker = null;
                }

                $browser = new Browser(
                    $row['properties']['Browser'],
                    $browserMaker,
                    (new BrowserVersionMapper())->mapBrowserVersion($row['properties']['Version'], $row['properties']['Browser']),
                    $browserType,
                    $row['properties']['Browser_Bits'],
                    false,
                    false,
                    $row['properties']['Browser_Modus']
                );

                try {
                    $deviceMaker = (new CompanyLoader($this->cache))->load($row['properties']['Device_Maker']);
                } catch (NotFoundException $e) {
                    $this->logger->critical($e);
                    $deviceMaker = null;
                }

                try {
                    $deviceBrand = (new CompanyLoader($this->cache))->load($row['properties']['Device_Brand_Name']);
                } catch (NotFoundException $e) {
                    $this->logger->critical($e);
                    $deviceBrand = null;
                }

                try {
                    $deviceType = (new DeviceTypeMapper())->mapDeviceType($this->cache, $row['properties']['Device_Type']);
                } catch (NotFoundException $e) {
                    $this->logger->critical($e);
                    $deviceType = null;
                }

                $device = new Device(
                    $row['properties']['Device_Code_Name'],
                    $row['properties']['Device_Name'],
                    $deviceMaker,
                    $deviceBrand,
                    $deviceType,
                    $row['properties']['Device_Pointing_Method']
                );

                try {
                    $platformMaker = (new CompanyLoader($this->cache))->load($row['properties']['Platform_Maker']);
                } catch (NotFoundException $e) {
                    $this->logger->critical($e);
                    $platformMaker = null;
                }

                $platform = new Os(
                    $row['properties']['Platform'],
                    null,
                    $platformMaker
                );

                try {
                    $engineMaker = (new CompanyLoader($this->cache))->load($row['properties']['RenderingEngine_Maker']);
                } catch (NotFoundException $e) {
                    $this->logger->critical($e);
                    $engineMaker = null;
                }

                $engine = new Engine(
                    $row['properties']['RenderingEngine_Name'],
                    $engineMaker,
                    (new EngineVersionMapper())->mapEngineVersion($row['properties']['RenderingEngine_Version'])
                );

                yield $row['ua'] => new Result($request, $device, $platform, $browser, $engine);
                $allTests[$row['ua']] = 1;
            }
        }
    }

    /**
     * @return array
     */
    private function loadFromPath()
    {
        $path = 'vendor/browscap/browscap/tests/fixtures/issues';

        if (!file_exists($path)) {
            return;
        }

        $this->output->writeln('    reading path ' . $path);

        $iterator = new \RecursiveDirectoryIterator($path);

        foreach (new \RecursiveIteratorIterator($iterator) as $file) {
            /** @var $file \SplFileInfo */
            if (!$file->isFile()) {
                continue;
            }

            $filepath = $file->getPathname();

            $this->output->writeln('    reading file ' . str_pad($filepath, 100, ' ', STR_PAD_RIGHT), false);
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
