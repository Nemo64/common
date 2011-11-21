<?php
/*
 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS
 * "AS IS" AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT
 * LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR
 * A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT
 * OWNER OR CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL,
 * SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT
 * LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES; LOSS OF USE,
 * DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON ANY
 * THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT
 * (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE
 * OF THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
 *
 * This software consists of voluntary contributions made by many individuals
 * and is licensed under the LGPL. For more information, see
 * <http://www.doctrine-project.org>.
*/

namespace Doctrine\Common\Persistence\Mapping\Driver;

/**
 * The Symfony File Locator makes a bunch of simplifying assumptions compared
 * to the DefaultFileLocator and introduces a global mapping file that contain
 * several mappings.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 * @author Benjamin Eberlei <kontakt@beberlei.de>
 * @license MIT
 */
class SymfonyFileLocator implements FileLocator
{
    /**
     * The paths where to look for mapping files.
     *
     * @var array
     */
    protected $paths = array();

    /**
     * A map of mapping directory path to namespace prefix used to expand class shortnames.
     *
     * @var array
     */
    protected $prefixes = array();

    /**
     * Global file with one or many entities defined in.
     *
     * Attention: Having lots of entities defined in this file can have a pretty
     * negative effect on performance.
     *
     * @var string
     */
    protected $globalBasename;

    /**
     * Cache for all class names in the global base name.
     *
     * @var array
     */
    protected $classCache;

    /**
     * File extension that is searched for.
     *
     * @var string
     */
    protected $fileExtension;

    public function __construct($prefixes)
    {
        $this->addNamespacePrefixes($prefixes);
    }

    public function addNamespacePrefixes($prefixes)
    {
        $this->prefixes = array_merge($this->prefixes, $prefixes);
        $this->paths = array_merge($this->paths, array_keys($prefixes));
    }

    public function getNamespacePrefixes()
    {
        return $this->prefixes;
    }

    public function fileExists($className)
    {
        if (null === $this->classCache) {
            $this->initialize();
        }

        // The mapping is defined in the global mapping file
        if (isset($this->classCache[$className])) {
            return false;
        }

        try {
            $this->_findMappingFile($className);

            return false;
        } catch (MappingException $e) {
            return true;
        }
    }

    public function getAllClassNames()
    {
        if (null === $this->classCache) {
            $this->initialize();
        }

        $classes = array();

        if ($this->paths) {
            foreach ((array) $this->paths as $path) {
                if (!is_dir($path)) {
                    throw MappingException::fileMappingDriversRequireConfiguredDirectoryPath($path);
                }

                $iterator = new \RecursiveIteratorIterator(
                    new \RecursiveDirectoryIterator($path),
                    \RecursiveIteratorIterator::LEAVES_ONLY
                );

                foreach ($iterator as $file) {
                    $fileName = $file->getBasename($this->fileExtension);

                    if ($fileName == $file->getBasename() || $fileName == $this->globalBasename) {
                        continue;
                    }

                    // NOTE: All files found here means classes are not transient!
                    if (isset($this->prefixes[$path])) {
                        $classes[] = $this->prefixes[$path].'\\'.str_replace('.', '\\', $fileName);
                    } else {
                        $classes[] = str_replace('.', '\\', $fileName);
                    }
                }
            }
        }

        return array_merge($classes, array_keys($this->classCache));
    }

    protected function initialize()
    {
        $this->classCache = array();
        if (null !== $this->globalBasename) {
            foreach ($this->paths as $path) {
                if (is_file($file = $path.'/'.$this->globalBasename.$this->fileExtension)) {
                    $this->classCache = array_merge($this->classCache, $this->loadMappingFile($file));
                }
            }
        }
    }

    public function findMappingFile($className)
    {
        if (null === $this->classCache) {
            $this->initialize();
        }

        $defaultFileName = str_replace('\\', '.', $className).$this->fileExtension;
        foreach ($this->paths as $path) {
            if (!isset($this->prefixes[$path])) {
                if (is_file($path.DIRECTORY_SEPARATOR.$defaultFileName)) {
                    return $path.DIRECTORY_SEPARATOR.$defaultFileName;
                }

                continue;
            }

            $prefix = $this->prefixes[$path];

            if (0 !== strpos($className, $prefix.'\\')) {
                continue;
            }

            $filename = $path.'/'.strtr(substr($className, strlen($prefix)+1), '\\', '.').$this->fileExtension;
            if (is_file($filename)) {
                return $filename;
            }

            throw MappingException::mappingFileNotFound($className, $filename);
        }

        throw MappingException::mappingFileNotFound($className, substr($className, strrpos($className, '\\') + 1).$this->fileExtension);
    }
}
