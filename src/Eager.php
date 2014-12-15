<?php
/**
 * Piwik - free/libre analytics platform
 *
 * @link http://piwik.org
 * @license http://www.gnu.org/licenses/lgpl-3.0.html LGPL v3 or later
 *
 */
namespace Piwik\Cache;

use Piwik\Cache;
use Piwik\Cache\Backend;

/**
 * This cache uses one "cache" entry for all cache entries it contains.
 *
 * This comes handy for things that you need very often, nearly in every request. Instead of having to read eg.
 * a hundred caches from file we only load one file which contains the hundred cache ids. Should be used only for things
 * that you need very often and only for cache entries that are not too large to keep loading and parsing the single
 * cache entry fast.
 *
 * $cache = new Eager($backend, $storageId = 'eagercache');
 * // $cache->fetch('my'id')
 * // $cache->save('myid', 'test');
 *
 * // ... at some point or at the end of the request
 * $cache->persistCacheIfNeeded($lifeTime = 43200);
 */
class Eager
{
    /**
     * @var Backend
     */
    private $storage;
    private $storageId;
    private $content = array();
    private $isDirty = false;

    /**
     * Loads the cache entries from the given backend using the given storageId.
     *
     * @param Backend $storage
     * @param $storageId
     */
    public function __construct(Backend $storage, $storageId)
    {
        $this->storage = $storage;
        $this->storageId = $storageId;

        $content = $storage->doFetch($storageId);

        if (is_array($content)) {
            $this->content = $content;
        }
    }

    /**
     * Fetches an entry from the cache.
     *
     * Make sure to call the method {@link contains()} to verify whether there is actually any content saved under
     * this cache id.
     *
     * @param string $id The cache id.
     * @return int|float|string|boolean|array
     */
    public function fetch($id)
    {
        return $this->content[$id];
    }

    /**
     * Tests if an entry exists in the cache.
     *
     * @param string $id The cache id.
     * @return bool
     */
    public function contains($id)
    {
        return array_key_exists($id, $this->content);
    }

    /**
     * Puts data into the cache.
     *
     * @param string $id The cache id.
     * @param int|float|string|boolean|array $content
     * @return boolean
     */
    public function save($id, $content)
    {
        if (is_object($content)) {
            throw new \InvalidArgumentException('You cannot use this cache to cache an object, only arrays, strings and numbers. Have a look at Transient cache.');
            // for performance reasons we do currently not recursively search whether any array contains an object.
        }

        $this->content[$id] = $content;
        $this->isDirty = true;
        return true;
    }

    /**
     * Deletes one cache entry having the given id.
     *
     * @param string $id The cache id.
     * @return boolean TRUE if the cache actually contains this entry and if it was successfully deleted, FALSE otherwise.
     */
    public function delete($id)
    {
        if ($this->contains($id)) {
            $this->isDirty = true;
            unset($this->content[$id]);
            return true;
        }

        return false;
    }

    /**
     * Flushes all cache entries.
     *
     * @return bool returns always TRUE after the cache was flushed.
     */
    public function flushAll()
    {
        $this->storage->doDelete($this->storageId);

        $this->content = array();
        $this->isDirty = false;

        return true;
    }

    /**
     * Will persist all previously made changes if there were any.
     *
     * @param int $lifeTime  The cache lifetime in seconds.
     *                       If != 0, sets a specific lifetime for this cache entry (0 => infinite lifeTime).
     */
    public function persistCacheIfNeeded($lifeTime)
    {
        if ($this->isDirty) {
            $this->storage->doSave($this->storageId, $this->content, $lifeTime);
        }
    }

}
