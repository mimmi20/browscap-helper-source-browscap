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
use Symfony\Component\Finder\Finder;
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
     * @var null
     */
    private $logger = null;

    /**
     * @var \Psr\Cache\CacheItemPoolInterface|null
     */
    private $cache = null;

    /**
     * @param \Psr\Log\LoggerInterface          $logger
     * @param \Psr\Cache\CacheItemPoolInterface $cache
     */
    public function __construct(LoggerInterface $logger, CacheItemPoolInterface $cache)
    {
        $this->logger = $logger;
        $this->cache  = $cache;
    }

    /**
     * @param int $limit
     *
     * @return string[]
     */
    public function getUserAgents($limit = 0)
    {
        $counter = 0;

        foreach ($this->loadFromPath() as $row) {
            if ($limit && $counter >= $limit) {
                return;
            }

            yield trim($row['ua']);
            ++$counter;
        }
    }

    /**
     * @return \UaResult\Result\Result[]
     */
    public function getTests()
    {
        foreach ($this->loadFromPath() as $row) {
            $agent   = trim($row['ua']);
            $request = (new GenericRequestFactory())->createRequestFromString($agent);

            if (array_key_exists('Browser_Type', $row['properties'])) {
                try {
                    $browserType = (new BrowserTypeMapper())->mapBrowserType($this->cache, $row['properties']['Browser_Type']);
                } catch (NotFoundException $e) {
                    $this->logger->critical($e);
                    $browserType = null;
                }
            } else {
                $this->logger->error('The browser type is missing for UA "' . $agent . '"');
                $browserType = null;
            }

            if (array_key_exists('Browser_Maker', $row['properties'])) {
                try {
                    $browserMaker = (new CompanyLoader($this->cache))->loadByName($row['properties']['Browser_Maker']);
                } catch (NotFoundException $e) {
                    $this->logger->critical($e);
                    $browserMaker = null;
                }
            } else {
                $this->logger->error('The browser maker is missing for UA "' . $agent . '"');
                $browserMaker = null;
            }

            if (array_key_exists('Browser_Bits', $row['properties'])) {
                $bits = $row['properties']['Browser_Bits'];
            } else {
                $this->logger->error('The browser bits are missing for UA "' . $agent . '"');
                $bits = null;
            }

            if (array_key_exists('Browser_Modus', $row['properties'])) {
                $modus = $row['properties']['Browser_Modus'];
            } else {
                $this->logger->error('The browser modus is missing for UA "' . $agent . '"');
                $modus = null;
            }

            $browser = new Browser(
                $row['properties']['Browser'],
                $browserMaker,
                (new BrowserVersionMapper())->mapBrowserVersion($row['properties']['Version'], $row['properties']['Browser']),
                $browserType,
                $bits,
                $modus
            );

            if (array_key_exists('Device_Maker', $row['properties'])) {
                try {
                    $deviceMaker = (new CompanyLoader($this->cache))->loadByName($row['properties']['Device_Maker']);
                } catch (NotFoundException $e) {
                    $this->logger->critical($e);
                    $deviceMaker = null;
                }
            } else {
                $this->logger->error('The device maker is missing for UA "' . $agent . '"');
                $deviceMaker = null;
            }

            if (array_key_exists('Device_Brand_Name', $row['properties'])) {
                try {
                    $deviceBrand = (new CompanyLoader($this->cache))->loadByBrandName($row['properties']['Device_Brand_Name']);
                } catch (NotFoundException $e) {
                    $this->logger->critical($e);
                    $deviceBrand = null;
                }
            } else {
                $this->logger->error('The device brand name is missing for UA "' . $agent . '"');
                $deviceBrand = null;
            }

            if (array_key_exists('Device_Type', $row['properties'])) {
                try {
                    $deviceType = (new DeviceTypeMapper())->mapDeviceType($this->cache, $row['properties']['Device_Type']);
                } catch (NotFoundException $e) {
                    $this->logger->critical($e);
                    $deviceType = null;
                }
            } else {
                $this->logger->error('The device type is missing for UA "' . $agent . '"');
                $deviceType = null;
            }

            if (array_key_exists('Device_Code_Name', $row['properties'])) {
                $codeName = $row['properties']['Device_Code_Name'];
            } else {
                $this->logger->error('The device code name is missing for UA "' . $agent . '"');
                $codeName = null;
            }

            if (array_key_exists('Device_Name', $row['properties'])) {
                $deviceName = $row['properties']['Device_Name'];
            } else {
                $this->logger->error('The device name is missing for UA "' . $agent . '"');
                $deviceName = null;
            }

            if (array_key_exists('Device_Pointing_Method', $row['properties'])) {
                $pointing = $row['properties']['Device_Pointing_Method'];
            } else {
                $this->logger->error('The device pointing method is missing for UA "' . $agent . '"');
                $pointing = null;
            }

            $device = new Device(
                $codeName,
                $deviceName,
                $deviceMaker,
                $deviceBrand,
                $deviceType,
                $pointing
            );

            if (array_key_exists('Platform_Maker', $row['properties'])) {
                try {
                    $platformMaker = (new CompanyLoader($this->cache))->loadByName($row['properties']['Platform_Maker']);
                } catch (NotFoundException $e) {
                    $this->logger->critical($e);
                    $platformMaker = null;
                }
            } else {
                $this->logger->error('The platform maker is missing for UA "' . $agent . '"');
                $platformMaker = null;
            }

            $platform = new Os(
                $row['properties']['Platform'],
                null,
                $platformMaker
            );

            if (array_key_exists('Platform_Maker', $row['properties'])) {
                try {
                    $engineMaker = (new CompanyLoader($this->cache))->loadByName($row['properties']['RenderingEngine_Maker']);
                } catch (NotFoundException $e) {
                    $this->logger->critical($e);
                    $engineMaker = null;
                }
            } else {
                $this->logger->error('The engine maker is missing for UA "' . $agent . '"');
                $engineMaker = null;
            }

            $engine = new Engine(
                $row['properties']['RenderingEngine_Name'],
                $engineMaker,
                (new EngineVersionMapper())->mapEngineVersion($row['properties']['RenderingEngine_Version'])
            );

            yield $agent => new Result($request, $device, $platform, $browser, $engine);
        }
    }

    /**
     * @return array[]
     */
    private function loadFromPath()
    {
        $path = 'vendor/browscap/browscap/tests/fixtures/issues';

        if (!file_exists($path)) {
            return;
        }

        $this->logger->info('    reading path ' . $path);

        $allTests = [];
        $finder   = new Finder();
        $finder->files();
        $finder->name('*.php');
        $finder->ignoreDotFiles(true);
        $finder->ignoreVCS(true);
        $finder->sortByName();
        $finder->ignoreUnreadableDirs();
        $finder->in($path);

        foreach ($finder as $file) {
            /** @var \Symfony\Component\Finder\SplFileInfo $file */
            if (!$file->isFile()) {
                continue;
            }

            if ('php' !== $file->getExtension()) {
                continue;
            }

            $filepath = $file->getPathname();

            $this->logger->info('    reading file ' . str_pad($filepath, 100, ' ', STR_PAD_RIGHT));
            $dataFile = include $filepath;

            foreach ($dataFile as $row) {
                if (!array_key_exists('ua', $row)) {
                    continue;
                }

                $agent = trim($row['ua']);

                if (array_key_exists($agent, $allTests)) {
                    continue;
                }

                yield $row;
                $allTests[$agent] = 1;
            }
        }
    }
}
