<?php

declare(strict_types=1);
namespace App\Service;

use Doctrine\DBAL\Connection;
use Symfony\AI\Platform\PlatformInterface;
use Symfony\AI\Platform\Bridge\Gemini\Embeddings;
use Symfony\AI\Platform\Capability;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final readonly class BillEmbeddings
{
    public const MODEL = 'gemini-embedding-2';
    public function __construct(private Connection $db,
        #[Autowire(service: 'ai.platform.gemini')] private PlatformInterface $platform,
        private HttpClientInterface $http,
        #[Autowire(env: 'GEMINI_API_KEY')] private string $key) {}

    public function register(string $text): string
    {
        $id = hash('sha256', self::MODEL.':1024:'.$text);
        $this->db->executeStatement('INSERT INTO rsun_ai_embedding (id,model,text) VALUES (?,?,?) ON CONFLICT DO NOTHING', [$id,self::MODEL,$text]);
        return $id;
    }

    /** Persist before paid calls; interrupted or failed attempts require explicit inspection. */
    public function embed(array $ids): void
    {
        $this->db->executeQuery('SELECT pg_advisory_lock(2026112016)');
        try {
            $rows = [];
            foreach (array_unique($ids) as $id) {
                $row = $this->db->fetchAssociative('SELECT * FROM rsun_ai_embedding WHERE id=?', [$id]);
                if (!$row) throw new \InvalidArgumentException('Unknown embedding');
                if ('complete' === $row['status']) continue;
                if ('new' !== $row['status']) throw new \RuntimeException('Embedding requires inspection: '.$id.' ('.$row['status'].')');
                $rows[] = $row;
            }
            if (!$rows) return;
            $counts = [];
            foreach ($rows as $row) {
                $counts[] = $this->http->request('POST', 'https://generativelanguage.googleapis.com/v1beta/models/'.self::MODEL.':countTokens', [
                    'headers' => ['x-goog-api-key' => $this->key],
                    'json' => ['contents' => [['role' => 'user', 'parts' => [['text' => $row['text']]]]]],
                ]);
            }
            foreach ($rows as $i => $row) {
                $tokens = $counts[$i]->toArray()['totalTokens'];
                if ($tokens > 7000) throw new \RuntimeException('Chunk exceeds token budget: '.$row['id']);
                $this->db->update('rsun_ai_embedding', ['tokens' => $tokens], ['id' => $row['id']]);
            }
            foreach ($rows as $row) $this->db->update('rsun_ai_embedding', ['status' => 'pending'], ['id' => $row['id']]);
            try {
                $model = new Embeddings(self::MODEL, [Capability::INPUT_TEXT, Capability::EMBEDDINGS], ['dimensions' => 1024]);
                $result = $this->platform->invoke($model, array_column($rows, 'text'))->getResult();
                $vectors = $result->getContent();
                if (count($vectors) !== count($rows)) throw new \RuntimeException('Vector count mismatch');
                $this->db->transactional(function () use ($rows, $vectors): void {
                    foreach ($rows as $i => $row) {
                        $vector = $vectors[$i]->getData();
                        if (count($vector) !== 1024) throw new \RuntimeException('Vector dimensions mismatch');
                        $this->db->executeStatement("UPDATE rsun_ai_embedding SET embedding=?::vector,status='complete',updated_at=now() WHERE id=?", [json_encode($vector, JSON_THROW_ON_ERROR), $row['id']]);
                    }
                });
            } catch (\Throwable $error) {
                foreach ($rows as $row) $this->db->update('rsun_ai_embedding', ['status' => 'failed', 'error' => $error::class], ['id' => $row['id']]);
                throw $error;
            }
        } finally { $this->db->executeQuery('SELECT pg_advisory_unlock(2026112016)'); }
    }

    public function query(string $text): string
    {
        $id = $this->register($text);
        $this->embed([$id]);
        return $this->db->fetchOne('SELECT embedding::text FROM rsun_ai_embedding WHERE id=?', [$id]);
    }
}
