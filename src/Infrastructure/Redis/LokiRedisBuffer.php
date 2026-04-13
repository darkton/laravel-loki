<?php

namespace Zie\Loki\Infrastructure\Redis;

use Illuminate\Support\Facades\Redis;
use Zie\Loki\Contracts\LokiBufferInterface;
use Zie\Loki\DTOs\LogEntryDTO;
use Zie\Loki\Exceptions\LokiBufferException;

class LokiRedisBuffer implements LokiBufferInterface
{
    /**
     * Lua script for atomic read-and-trim operation.
     *
     * Reads all current entries from the list and trims exactly those N elements,
     * leaving any entries that arrived after the LRANGE untouched.
     *
     * This eliminates the race condition between LRANGE and DEL:
     * - LRANGE reads N entries (indices 0..N-1).
     * - LTRIM key N -1 removes only those N entries, keeping index N onwards intact.
     *
     * Redis executes Lua scripts atomically — no other command can interleave.
     */
    private const LUA_ATOMIC_READ_AND_TRIM = <<<'LUA'
local key     = KEYS[1]
local entries = redis.call('LRANGE', key, 0, -1)
local count   = #entries
if count > 0 then
    redis.call('LTRIM', key, count, -1)
end
return entries
LUA;

    public function __construct(
        private readonly string $redisKey,
        private readonly string $connection,
        private readonly int    $batchSize,
        private readonly bool   $filterInternal = false,
    ) {}

    /**
     * @throws LokiBufferException
     */
    public function push(LogEntryDTO $entry): void
    {
        try {
            $encoded = json_encode($entry->toArray());

            if ($encoded === false) {
                throw LokiBufferException::writeFailed($this->redisKey, 'JSON encode failed');
            }

            Redis::connection($this->connection)->rpush($this->redisKey, $encoded);
        } catch (LokiBufferException $e) {
            throw $e;
        } catch (\Exception $e) {
            throw LokiBufferException::writeFailed($this->redisKey, $e->getMessage());
        }
    }

    /**
     * Atomically read and remove all entries from the buffer.
     *
     * Uses a Lua script to ensure no entries are lost between the read and
     * the removal — any entry pushed after the LRANGE remains in the list.
     *
     * @return array<int, array<int, array>> Batches of decoded log entry arrays.
     *
     * @throws LokiBufferException
     */
    public function readAndClearBatches(): array
    {
        try {
            $redis   = Redis::connection($this->connection);
            $entries = $redis->eval(self::LUA_ATOMIC_READ_AND_TRIM, 1, $this->redisKey);

            if (empty($entries)) {
                return [];
            }

            return $this->decodeAndChunk((array) $entries);
        } catch (LokiBufferException $e) {
            throw $e;
        } catch (\Exception $e) {
            throw LokiBufferException::readFailed($this->redisKey, $e->getMessage());
        }
    }

    /**
     * Read all entries without removing them (read-only, no side effects).
     *
     * @return array<int, array<int, array>> Batches of decoded log entry arrays.
     *
     * @throws LokiBufferException
     */
    public function readBatches(): array
    {
        try {
            $entries = Redis::connection($this->connection)->lrange($this->redisKey, 0, -1);

            if (empty($entries)) {
                return [];
            }

            return $this->decodeAndChunk((array) $entries);
        } catch (LokiBufferException $e) {
            throw $e;
        } catch (\Exception $e) {
            throw LokiBufferException::readFailed($this->redisKey, $e->getMessage());
        }
    }

    /**
     * @throws LokiBufferException
     */
    public function clearBuffer(): void
    {
        try {
            Redis::connection($this->connection)->del($this->redisKey);
        } catch (\Exception $e) {
            throw LokiBufferException::writeFailed($this->redisKey, $e->getMessage());
        }
    }

    /**
     * Create a timestamped backup of the buffer in Redis (TTL: 30 days).
     *
     * @throws LokiBufferException
     */
    public function backupBuffer(): string
    {
        $backupKey = $this->redisKey . ':backup:' . time();

        try {
            $redis   = Redis::connection($this->connection);
            $entries = $redis->lrange($this->redisKey, 0, -1);

            if (! empty($entries)) {
                foreach ($entries as $entry) {
                    $redis->rpush($backupKey, $entry);
                }
                $redis->expire($backupKey, 2592000); // 30 days
            }

            return $backupKey;
        } catch (\Exception $e) {
            throw LokiBufferException::writeFailed($backupKey, $e->getMessage());
        }
    }

    public function count(): int
    {
        try {
            return (int) Redis::connection($this->connection)->llen($this->redisKey);
        } catch (\Exception) {
            return 0;
        }
    }

    /**
     * Decode JSON entries, apply internal-log filtering, and split into batches.
     *
     * @param  string[]  $entries  Raw JSON strings from Redis.
     *
     * @return array<int, array<int, array>>
     *
     * @throws LokiBufferException
     */
    private function decodeAndChunk(array $entries): array
    {
        $logs = [];

        foreach ($entries as $entry) {
            $decoded = json_decode($entry, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                throw LokiBufferException::invalidJson($entry, 0);
            }

            // Skip internal Loki logs when filterInternal is enabled
            if ($this->filterInternal
                && isset($decoded['context']['loki_internal'])
                && $decoded['context']['loki_internal'] === true
            ) {
                continue;
            }

            $logs[] = $decoded;
        }

        return array_chunk($logs, $this->batchSize);
    }

    public function getRedisKey(): string
    {
        return $this->redisKey;
    }

    public function getConnection(): string
    {
        return $this->connection;
    }
}
