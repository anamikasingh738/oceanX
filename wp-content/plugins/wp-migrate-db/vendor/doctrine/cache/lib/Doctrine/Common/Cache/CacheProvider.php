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
 * and is licensed under the MIT license. For more information, see
 * <http://www.doctrine-project.org>.
 */
namespace DeliciousBrains\WPMDB\Container\Doctrine\Common\Cache;

/**
 * Base class for cache provider implementations.
 *
 * @since  2.2
 * @author Benjamin Eberlei <kontakt@beberlei.de>
 * @author Guilherme Blanco <guilhermeblanco@hotmail.com>
 * @author Jonathan Wage <jonwage@gmail.com>
 * @author Roman Borschel <roman@code-factory.org>
 * @author Fabio B. Silva <fabio.bat.silva@gmail.com>
 */
abstract class CacheProvider implements Cache, FlushableCache, ClearableCache, MultiGetCache
{
    const DOCTRINE_NAMESPACE_CACHEKEY = 'DoctrineNamespaceCacheKey[%s]';
    /**
     * The namespace to prefix all cache ids with.
     *
     * @var string
     */
    private $namespace = '';
    /**
     * The namespace version.
     *
     * @var integer|null
     */
    private $namespaceVersion;
    /**
     * Sets the namespace to prefix all cache ids with.
     *
     * @param string $namespace
     *
     * @return void
     */
    public function setNamespace($namespace)
    {
        $this->namespace = (string) $namespace;
        $this->namespaceVersion = null;
    }
    /**
     * Retrieves the namespace that prefixes all cache ids.
     *
     * @return string
     */
    public function getNamespace()
    {
        return $this->namespace;
    }
    /**
     * {@inheritdoc}
     */
    public function fetch($id)
    {
        return $this->doFetch($this->getNamespacedId($id));
    }
    /**
     * {@inheritdoc}
     */
    public function fetchMultiple(array $keys)
    {
        // note: the array_combine() is in place to keep an association between our $keys and the $namespacedKeys
        $namespacedKeys = \array_combine($keys, \array_map(array($this, 'getNamespacedId'), $keys));
        $items = $this->doFetchMultiple($namespacedKeys);
        $foundItems = array();
        // no internal array function supports this sort of mapping: needs to be iterative
        // this filters and combines keys in one pass
        foreach ($namespacedKeys as $requestedKey => $namespacedKey) {
            if (isset($items[$namespacedKey])) {
                $foundItems[$requestedKey] = $items[$namespacedKey];
            }
        }
        return $foundItems;
    }
    /**
     * {@inheritdoc}
     */
    public function contains($id)
    {
        return $this->doContains($this->getNamespacedId($id));
    }
    /**
     * {@inheritdoc}
     */
    public function save($id, $data, $lifeTime = 0)
    {
        return $this->doSave($this->getNamespacedId($id), $data, $lifeTime);
    }
    /**
     * {@inheritdoc}
     */
    public function delete($id)
    {
        return $this->doDelete($this->getNamespacedId($id));
    }
    /**
     * {@inheritdoc}
     */
    public function getStats()
    {
        return $this->doGetStats();
    }
    /**
     * {@inheritDoc}
     */
    public function flushAll()
    {
        return $this->doFlush();
    }
    /**
     * {@inheritDoc}
     */
    public function deleteAll()
    {
        $namespaceCacheKey = $this->getNamespaceCacheKey();
        $namespaceVersion = $this->getNamespaceVersion() + 1;
        $this->namespaceVersion = $namespaceVersion;
        return $this->doSave($namespaceCacheKey, $namespaceVersion);
    }
    /**
     * Prefixes the passed id with the configured namespace value.
     *
     * @param string $id The id to namespace.
     *
     * @return string The namespaced id.
     */
    private function getNamespacedId($id)
    {
        $namespaceVersion = $this->getNamespaceVersion();
        return \sprintf('%s[%s][%s]', $this->namespace, $id, $namespaceVersion);
    }
    /**
     * Returns the namespace cache key.
     *
     * @return string
     */
    private function getNamespaceCacheKey()
    {
        return \sprintf(self::DOCTRINE_NAMESPACE_CACHEKEY, $this->namespace);
    }
    /**
     * Returns the namespace version.
     *
     * @return integer
     */
    private function getNamespaceVersion()
    {
        if (null !== $this->namespaceVersion) {
            return $this->namespaceVersion;
        }
        $namespaceCacheKey = $this->getNamespaceCacheKey();
        $namespaceVersion = $this->doFetch($namespaceCacheKey);
        if (\false === $namespaceVersion) {
            $namespaceVersion = 1;
            $this->doSave($namespaceCacheKey, $namespaceVersion);
        }
        $this->namespaceVersion = $namespaceVersion;
        return $this->namespaceVersion;
    }
    /**
     * Default implementation of doFetchMultiple. Each driver that supports multi-get should owerwrite it.
     *
     * @param array $keys Array of keys to retrieve from cache
     * @return array Array of values retrieved for the given keys.
     */
    protected function doFetchMultiple(array $keys)
    {
        $returnValues = array();
        foreach ($keys as $index => $key) {
            if (\false !== ($item = $this->doFetch($key))) {
                $returnValues[$key] = $item;
            }
        }
        return $returnValues;
    }
    /**
     * Fetches an entry from the cache.
     *
     * @param string $id The id of the cache entry to fetch.
     *
     * @return string|boolean The cached data or FALSE, if no cache entry exists for the given id.
     */
    protected abstract function doFetch($id);
    /**
     * Tests if an entry exists in the cache.
     *
     * @param string $id The cache id of the entry to check for.
     *
     * @return boolean TRUE if a cache entry exists for the given cache id, FALSE otherwise.
     */
    protected abstract function doContains($id);
    /**
     * Puts data into the cache.
     *
     * @param string $id       The cache id.
     * @param string $data     The cache entry/data.
     * @param int    $lifeTime The lifetime. If != 0, sets a specific lifetime for this
     *                           cache entry (0 => infinite lifeTime).
     *
     * @return boolean TRUE if the entry was successfully stored in the cache, FALSE otherwise.
     */
    protected abstract function doSave($id, $data, $lifeTime = 0);
    /**
     * Deletes a cache entry.
     *
     * @param string $id The cache id.
     *
     * @return boolean TRUE if the cache entry was successfully deleted, FALSE otherwise.
     */
    protected abstract function doDelete($id);
    /**
     * Flushes all cache entries.
     *
     * @return boolean TRUE if the cache entry was successfully deleted, FALSE otherwise.
     */
    protected abstract function doFlush();
    /**
     * Retrieves cached information from the data store.
     *
     * @since 2.2
     *
     * @return array|null An associative array with server's statistics if available, NULL otherwise.
     */
    protected abstract function doGetStats();
}
