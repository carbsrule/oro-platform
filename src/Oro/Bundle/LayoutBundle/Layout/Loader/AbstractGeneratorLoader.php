<?php

namespace Oro\Bundle\LayoutBundle\Layout\Loader;

use Oro\Bundle\LayoutBundle\Layout\Generator\Condition\ConditionCollection;
use Oro\Bundle\LayoutBundle\Layout\Generator\LayoutUpdateGeneratorInterface;
use Oro\Bundle\LayoutBundle\Layout\Extension\Context\RouteContextConfigurator;
use Oro\Bundle\LayoutBundle\Layout\Generator\Condition\SimpleContextValueComparisonCondition;

abstract class AbstractGeneratorLoader implements LoaderInterface
{
    const CLASS_PREFIX = '__Oro_Layout_Update_';

    /** @var LayoutUpdateGeneratorInterface */
    private $generator;

    /** @var bool */
    private $debug;

    /** @var string */
    private $cache;

    /** @var array */
    protected $loaded = [];

    /**
     * @param LayoutUpdateGeneratorInterface $generator
     * @param bool                           $debug
     * @param string                         $cache
     */
    public function __construct(LayoutUpdateGeneratorInterface $generator, $debug, $cache)
    {
        $this->generator = $generator;
        $this->debug     = $debug;
        $this->cache     = $cache;
    }

    /**
     * {@inheritdoc}
     */
    public function load(FileResource $resource)
    {
        $name      = $resource->getFilename();
        $className = $this->generateClassName($name);

        if (isset($this->loaded[$className])) {
            return $this->loaded[$className];
        }

        // TODO generate cache file in dev mode, but check mTime each request
        if (!class_exists($className, false)) {
            if (false === $cache = $this->getCacheFilename($name)) {
                eval('?>' . $this->doGenerate($className, $resource));
            } else {
                if (!is_file($cache)) {
                    $this->writeCacheFile($cache, $this->doGenerate($className, $resource));
                }

                require_once $cache;
            }
        }

        return $this->loaded[$className] = new $className($this);
    }

    /**
     * @param string       $className
     * @param FileResource $resource
     *
     * @return string
     */
    protected function doGenerate($className, FileResource $resource)
    {
        $conditionCollection = new ConditionCollection();

        if ($resource instanceof RouteFileResource) {
            $conditionCollection->append(
                new SimpleContextValueComparisonCondition(
                    RouteContextConfigurator::PARAM_NAME,
                    '===',
                    $resource->getRouteName()
                )
            );
        }

        return $this->getGenerator()
            ->generate($className, $this->loadResourceGeneratorData($resource), $conditionCollection);
    }

    /**
     * @param FileResource $resource
     *
     * @return array|string
     */
    abstract protected function loadResourceGeneratorData(FileResource $resource);

    /**
     * @return LayoutUpdateGeneratorInterface
     */
    protected function getGenerator()
    {
        return $this->generator;
    }

    /**
     * @return boolean
     */
    protected function isDebug()
    {
        return $this->debug;
    }

    /**
     * @return string
     */
    protected function getCache()
    {
        return $this->cache;
    }

    /**
     * @param string $name
     *
     * @return string
     */
    protected function generateClassName($name)
    {
        return static::CLASS_PREFIX . hash('sha256', $this->normalizeName($name));
    }

    /**
     * @param string $name
     *
     * @return bool|string
     */
    protected function getCacheFilename($name)
    {
        if ($this->isDebug() || (!$this->getCache())) {
            return false;
        }

        $class = substr($this->generateClassName($name), strlen(static::CLASS_PREFIX));

        return str_replace(
            '/',
            DIRECTORY_SEPARATOR,
            sprintf(
                '%s/%s/%s/%s.php',
                $this->getCache(),
                substr($class, 0, 2),
                substr($class, 2, 2),
                substr($class, 4)
            )
        );
    }

    /**
     * @param string $file
     * @param string $content
     */
    protected function writeCacheFile($file, $content)
    {
        $dir = dirname($file);
        if (!is_dir($dir)) {
            if (false === @mkdir($dir, 0777, true) && !is_dir($dir)) {
                throw new \RuntimeException(sprintf('Unable to create the cache directory (%s).', $dir));
            }
        } elseif (!is_writable($dir)) {
            throw new \RuntimeException(sprintf('Unable to write in the cache directory (%s).', $dir));
        }

        $tmpFile = tempnam($dir, basename($file));
        if (false !== @file_put_contents($tmpFile, $content)) {
            // rename does not work on Win32 before 5.2.6
            if (@rename($tmpFile, $file) || (@copy($tmpFile, $file) && unlink($tmpFile))) {
                @chmod($file, 0666 & ~umask());

                return;
            }
        }

        throw new \RuntimeException(sprintf('Failed to write cache file "%s".', $file));
    }

    /**
     * @param string $name
     *
     * @return string
     */
    protected function normalizeName($name)
    {
        return preg_replace('#/{2,}#', '/', strtr((string)$name, '\\', '/'));
    }
}
