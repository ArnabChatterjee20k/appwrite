<?php
declare(strict_types=1);

namespace Tests\E2E\Services\Databases\VectorDB;

use Tests\E2E\Client;
use Tests\E2E\Scopes\ProjectCustom;
use Tests\E2E\Scopes\Scope;
use Tests\E2E\Scopes\SideServer;
use Utopia\Database\Database;
use Utopia\Database\Helpers\ID;
use Utopia\Database\Helpers\Permission;
use Utopia\Database\Helpers\Role;

class DatabasesCustomServerTest extends Scope
{
    use DatabasesBase;
    use ProjectCustom;
    use SideServer;

    public function testListDatabases(): array
    {
        $db1 = $this->client->call(Client::METHOD_POST, '/vectordb', [
            'content-type' => 'application/json',
            'x-appwrite-project' => $this->getProject()['$id'],
            'x-appwrite-key' => $this->getProject()['apiKey']
        ], [
            'databaseId' => ID::custom('first'),
            'name' => 'Test 1',
        ]);
        $this->assertEquals(201, $db1['headers']['status-code']);
        $this->assertEquals('Test 1', $db1['body']['name']);
        $this->assertEquals('vectordb', $db1['body']['type']);

        $db2 = $this->client->call(Client::METHOD_POST, '/vectordb', [
            'content-type' => 'application/json',
            'x-appwrite-project' => $this->getProject()['$id'],
            'x-appwrite-key' => $this->getProject()['apiKey']
        ], [
            'databaseId' => ID::custom('second'),
            'name' => 'Test 2',
        ]);
        $this->assertEquals(201, $db2['headers']['status-code']);
        $this->assertEquals('Test 2', $db2['body']['name']);
        $this->assertEquals('vectordb', $db2['body']['type']);

        $list = $this->client->call(Client::METHOD_GET, '/vectordb', [
            'content-type' => 'application/json',
            'x-appwrite-project' => $this->getProject()['$id'],
            'x-appwrite-key' => $this->getProject()['apiKey']
        ]);
        $this->assertEquals(200, $list['headers']['status-code']);
        $this->assertIsInt($list['body']['total']);
        $this->assertGreaterThanOrEqual(2, $list['body']['total']);
        $this->assertIsArray($list['body']['databases']);
        $this->assertArrayHasKey('$id', $list['body']['databases'][0]);
        $this->assertArrayHasKey('name', $list['body']['databases'][0]);
        $this->assertArrayHasKey('type', $list['body']['databases'][0]);

        return ['databaseId' => $db1['body']['$id']];
    }

    /**
     * @depends testListDatabases
     */
    public function testGetDatabase(array $data): array
    {
        $databaseId = $data['databaseId'];
        $res = $this->client->call(Client::METHOD_GET, '/vectordb/' . $databaseId, [
            'content-type' => 'application/json',
            'x-appwrite-project' => $this->getProject()['$id'],
            'x-appwrite-key' => $this->getProject()['apiKey']
        ]);
        $this->assertEquals(200, $res['headers']['status-code']);
        $this->assertEquals($databaseId, $res['body']['$id']);
        $this->assertEquals('Test 1', $res['body']['name']);
        $this->assertEquals('vectordb', $res['body']['type']);
        return ['databaseId' => $databaseId];
    }

    /**
     * @depends testListDatabases
     */
    public function testUpdateDatabase(array $data): array
    {
        $databaseId = $data['databaseId'];
        $res = $this->client->call(Client::METHOD_PUT, '/vectordb/' . $databaseId, [
            'content-type' => 'application/json',
            'x-appwrite-project' => $this->getProject()['$id'],
            'x-appwrite-key' => $this->getProject()['apiKey']
        ], [
            'name' => 'Test 1 Updated',
        ]);
        $this->assertEquals(200, $res['headers']['status-code']);
        $this->assertEquals('Test 1 Updated', $res['body']['name']);
        $this->assertEquals('vectordb', $res['body']['type']);
        return ['databaseId' => $databaseId];
    }

    /**
     * @depends testListDatabases
     */
    public function testDeleteDatabase(array $data): void
    {
        $databaseId = $data['databaseId'];
        $del = $this->client->call(Client::METHOD_DELETE, '/vectordb/' . $databaseId, [
            'content-type' => 'application/json',
            'x-appwrite-project' => $this->getProject()['$id'],
            'x-appwrite-key' => $this->getProject()['apiKey']
        ]);
        $this->assertEquals(204, $del['headers']['status-code']);
        $this->assertEquals("", $del['body']);

        $get = $this->client->call(Client::METHOD_GET, '/vectordb/' . $databaseId, [
            'content-type' => 'application/json',
            'x-appwrite-project' => $this->getProject()['$id'],
            'x-appwrite-key' => $this->getProject()['apiKey']
        ]);
        $this->assertEquals(404, $get['headers']['status-code']);
    }

    public function testCollectionsCRUD(): array
    {
        // Create database for collections tests
        $database = $this->client->call(Client::METHOD_POST, '/vectordb', [
            'content-type' => 'application/json',
            'x-appwrite-project' => $this->getProject()['$id'],
            'x-appwrite-key' => $this->getProject()['apiKey']
        ], [
            'databaseId' => ID::unique(),
            'name' => 'Collections DB',
        ]);
        $this->assertEquals(201, $database['headers']['status-code']);
        $databaseId = $database['body']['$id'];

        // Create two collections
        $col1 = $this->client->call(Client::METHOD_POST, '/vectordb/' . $databaseId . '/collections', [
            'content-type' => 'application/json',
            'x-appwrite-project' => $this->getProject()['$id'],
            'x-appwrite-key' => $this->getProject()['apiKey']
        ], [
            'name' => 'Test 1',
            'collectionId' => ID::custom('first'),
            'permissions' => [
                Permission::read(Role::any()),
                Permission::create(Role::any()),
                Permission::update(Role::any()),
                Permission::delete(Role::any()),
            ],
            'documentSecurity' => true,
            'dimensions' => 3,
        ]);
        $this->assertEquals(201, $col1['headers']['status-code']);

        $col2 = $this->client->call(Client::METHOD_POST, '/vectordb/' . $databaseId . '/collections', [
            'content-type' => 'application/json',
            'x-appwrite-project' => $this->getProject()['$id'],
            'x-appwrite-key' => $this->getProject()['apiKey']
        ], [
            'name' => 'Test 2',
            'collectionId' => ID::custom('second'),
            'permissions' => [
                Permission::read(Role::any()),
                Permission::create(Role::any()),
                Permission::update(Role::any()),
                Permission::delete(Role::any()),
            ],
            'documentSecurity' => true,
            'dimensions' => 3,
        ]);
        $this->assertEquals(201, $col2['headers']['status-code']);

        // List collections
        $list = $this->client->call(Client::METHOD_GET, '/vectordb/' . $databaseId . '/collections', [
            'content-type' => 'application/json',
            'x-appwrite-project' => $this->getProject()['$id'],
            'x-appwrite-key' => $this->getProject()['apiKey']
        ]);
        $this->assertEquals(200, $list['headers']['status-code']);
        $this->assertIsInt($list['body']['total']);
        $this->assertGreaterThanOrEqual(2, $list['body']['total']);
        $this->assertIsArray($list['body']['collections']);
        $this->assertArrayHasKey('$id', $list['body']['collections'][0]);
        $this->assertArrayHasKey('name', $list['body']['collections'][0]);
        $this->assertArrayHasKey('dimensions', $list['body']['collections'][0]);

        // Get collection
        $get = $this->client->call(Client::METHOD_GET, '/vectordb/' . $databaseId . '/collections/' . $col1['body']['$id'], [
            'content-type' => 'application/json',
            'x-appwrite-project' => $this->getProject()['$id'],
            'x-appwrite-key' => $this->getProject()['apiKey']
        ]);
        $this->assertEquals(200, $get['headers']['status-code']);
        $this->assertEquals($col1['body']['$id'], $get['body']['$id']);
        $this->assertEquals('Test 1', $get['body']['name']);
        $this->assertEquals(3, $get['body']['dimensions']);

        // Update collection (name only)
        $upd = $this->client->call(Client::METHOD_PUT, '/vectordb/' . $databaseId . '/collections/' . $col1['body']['$id'], [
            'content-type' => 'application/json',
            'x-appwrite-project' => $this->getProject()['$id'],
            'x-appwrite-key' => $this->getProject()['apiKey']
        ], [
            'name' => 'Test 1 Updated',
        ]);
        $this->assertEquals(200, $upd['headers']['status-code']);
        $this->assertEquals('Test 1 Updated', $upd['body']['name']);

        // Delete collection
        $del = $this->client->call(Client::METHOD_DELETE, '/vectordb/' . $databaseId . '/collections/' . $col2['body']['$id'], [
            'content-type' => 'application/json',
            'x-appwrite-project' => $this->getProject()['$id'],
            'x-appwrite-key' => $this->getProject()['apiKey']
        ]);
        $this->assertEquals(204, $del['headers']['status-code']);
        $this->assertEquals("", $del['body']);

        return [
            'databaseId' => $databaseId,
            'collectionId' => $col1['body']['$id'],
        ];
    }

    /**
     * @depends testCollectionsCRUD
     */
    public function testUpdateCollectionMore(array $data): array
    {
        $databaseId = $data['databaseId'];
        $collectionId = $data['collectionId'];

        // Update collection name and dimensions
        $upd = $this->client->call(Client::METHOD_PUT, '/vectordb/' . $databaseId . '/collections/' . $collectionId, [
            'content-type' => 'application/json',
            'x-appwrite-project' => $this->getProject()['$id'],
            'x-appwrite-key' => $this->getProject()['apiKey']
        ], [
            'name' => 'Test 1 Renamed',
            'dimensions' => 4,
        ]);
        $this->assertEquals(200, $upd['headers']['status-code']);
        $this->assertEquals('Test 1 Renamed', $upd['body']['name']);
        $this->assertEquals(4, $upd['body']['dimensions']);

        // Read back to confirm
        $get = $this->client->call(Client::METHOD_GET, '/vectordb/' . $databaseId . '/collections/' . $collectionId, [
            'content-type' => 'application/json',
            'x-appwrite-project' => $this->getProject()['$id'],
            'x-appwrite-key' => $this->getProject()['apiKey']
        ]);
        $this->assertEquals(200, $get['headers']['status-code']);
        $this->assertEquals('Test 1 Renamed', $get['body']['name']);
        $this->assertEquals(4, $get['body']['dimensions']);

        return $data;
    }

    /**
     * @depends testCollectionsCRUD
     */
    public function testUpdateCollectionEnabledFlag(array $data): array
    {
        $databaseId = $data['databaseId'];
        $collectionId = $data['collectionId'];

        // Disable collection
        $disable = $this->client->call(Client::METHOD_PUT, '/vectordb/' . $databaseId . '/collections/' . $collectionId, [
            'content-type' => 'application/json',
            'x-appwrite-project' => $this->getProject()['$id'],
            'x-appwrite-key' => $this->getProject()['apiKey']
        ], [
            'name' => 'Updated',
            'enabled' => false,
        ]);
        $this->assertEquals(200, $disable['headers']['status-code']);
        $this->assertFalse($disable['body']['enabled']);

        // Re-enable collection
        $enable = $this->client->call(Client::METHOD_PUT, '/vectordb/' . $databaseId . '/collections/' . $collectionId, [
            'content-type' => 'application/json',
            'x-appwrite-project' => $this->getProject()['$id'],
            'x-appwrite-key' => $this->getProject()['apiKey']
        ], [
            'name' => 'Updated',
            'enabled' => true,
        ]);
        $this->assertEquals(200, $enable['headers']['status-code']);
        $this->assertTrue($enable['body']['enabled']);

        return $data;
    }

    public function testUpdateDatabaseNameAndEnabled(): void
    {
        // Create isolated database for this test to avoid ordering conflicts
        $create = $this->client->call(Client::METHOD_POST, '/vectordb', [
            'content-type' => 'application/json',
            'x-appwrite-project' => $this->getProject()['$id'],
            'x-appwrite-key' => $this->getProject()['apiKey']
        ], [
            'databaseId' => ID::unique(),
            'name' => 'Update DB',
        ]);
        $this->assertEquals(201, $create['headers']['status-code']);
        $databaseId = $create['body']['$id'];

        // Update name
        $rename = $this->client->call(Client::METHOD_PUT, '/vectordb/' . $databaseId, [
            'content-type' => 'application/json',
            'x-appwrite-project' => $this->getProject()['$id'],
            'x-appwrite-key' => $this->getProject()['apiKey']
        ], [
            'name' => 'Test DB Renamed',
        ]);
        $this->assertEquals(200, $rename['headers']['status-code']);
        $this->assertEquals('Test DB Renamed', $rename['body']['name']);

        // Toggle enabled off then on
        $disable = $this->client->call(Client::METHOD_PUT, '/vectordb/' . $databaseId, [
            'content-type' => 'application/json',
            'x-appwrite-project' => $this->getProject()['$id'],
            'x-appwrite-key' => $this->getProject()['apiKey']
        ], [
            'name' => 'Test DB Renamed',
            'enabled' => false,
        ]);
        $this->assertEquals(200, $disable['headers']['status-code']);
        $this->assertFalse($disable['body']['enabled']);

        $enable = $this->client->call(Client::METHOD_PUT, '/vectordb/' . $databaseId, [
            'content-type' => 'application/json',
            'x-appwrite-project' => $this->getProject()['$id'],
            'x-appwrite-key' => $this->getProject()['apiKey']
        ], [
            'name' => 'Test DB Renamed',
            'enabled' => true,
        ]);
        $this->assertEquals(200, $enable['headers']['status-code']);
        $this->assertTrue($enable['body']['enabled']);

        // Cleanup
        $del = $this->client->call(Client::METHOD_DELETE, '/vectordb/' . $databaseId, [
            'content-type' => 'application/json',
            'x-appwrite-project' => $this->getProject()['$id'],
            'x-appwrite-key' => $this->getProject()['apiKey']
        ]);
        $this->assertEquals(204, $del['headers']['status-code']);
    }

    /**
     * @depends testCollectionsCRUD
     */
    public function testRecreateIndex(array $data): void
    {
        $databaseId = $data['databaseId'];
        $collectionId = $data['collectionId'];

        // Create a new index variant
        $create = $this->client->call(Client::METHOD_POST, "/vectordb/{$databaseId}/collections/{$collectionId}/indexes", [
            'content-type' => 'application/json',
            'x-appwrite-project' => $this->getProject()['$id'],
            'x-appwrite-key' => $this->getProject()['apiKey']
        ], [
            'key' => 'embedding_euclidean_v2',
            'type' => Database::INDEX_HNSW_EUCLIDEAN,
            'attributes' => ['embeddings']
        ]);
        $this->assertEquals(202, $create['headers']['status-code']);

        // Ensure it exists
        $get = $this->client->call(Client::METHOD_GET, "/vectordb/{$databaseId}/collections/{$collectionId}/indexes/embedding_euclidean_v2", [
            'content-type' => 'application/json',
            'x-appwrite-project' => $this->getProject()['$id'],
            'x-appwrite-key' => $this->getProject()['apiKey']
        ]);
        $this->assertEquals(200, $get['headers']['status-code']);
        $this->assertEquals('embedding_euclidean_v2', $get['body']['key']);

        // Delete it
        $del = $this->client->call(Client::METHOD_DELETE, "/vectordb/{$databaseId}/collections/{$collectionId}/indexes/embedding_euclidean_v2", [
            'content-type' => 'application/json',
            'x-appwrite-project' => $this->getProject()['$id'],
            'x-appwrite-key' => $this->getProject()['apiKey']
        ]);
        $this->assertEquals(204, $del['headers']['status-code']);
    }

    /**
     * @depends testCollectionsCRUD
     */
    public function testIndexesCRUD(array $data): void
    {
        $databaseId = $data['databaseId'];
        $collectionId = $data['collectionId'];

        // Create indexes
        $eu = $this->client->call(Client::METHOD_POST, "/vectordb/{$databaseId}/collections/{$collectionId}/indexes", [
            'content-type' => 'application/json',
            'x-appwrite-project' => $this->getProject()['$id'],
            'x-appwrite-key' => $this->getProject()['apiKey']
        ], [
            'key' => 'embedding_euclidean',
            'type' => Database::INDEX_HNSW_EUCLIDEAN,
            'attributes' => ['embeddings']
        ]);
        $this->assertEquals(202, $eu['headers']['status-code']);

        $dot = $this->client->call(Client::METHOD_POST, "/vectordb/{$databaseId}/collections/{$collectionId}/indexes", [
            'content-type' => 'application/json',
            'x-appwrite-project' => $this->getProject()['$id'],
            'x-appwrite-key' => $this->getProject()['apiKey']
        ], [
            'key' => 'embedding_dot',
            'type' => Database::INDEX_HNSW_DOT,
            'attributes' => ['embeddings']
        ]);
        $this->assertEquals(202, $dot['headers']['status-code']);

        $cos = $this->client->call(Client::METHOD_POST, "/vectordb/{$databaseId}/collections/{$collectionId}/indexes", [
            'content-type' => 'application/json',
            'x-appwrite-project' => $this->getProject()['$id'],
            'x-appwrite-key' => $this->getProject()['apiKey']
        ], [
            'key' => 'embedding_cosine',
            'type' => Database::INDEX_HNSW_COSINE,
            'attributes' => ['embeddings']
        ]);
        $this->assertEquals(202, $cos['headers']['status-code']);

        // List indexes
        $list = $this->client->call(Client::METHOD_GET, "/vectordb/{$databaseId}/collections/{$collectionId}/indexes", [
            'content-type' => 'application/json',
            'x-appwrite-project' => $this->getProject()['$id'],
            'x-appwrite-key' => $this->getProject()['apiKey']
        ]);
        $this->assertEquals(200, $list['headers']['status-code']);
        $this->assertIsArray($list['body']['indexes']);
        $keys = array_map(fn($i) => $i['key'], $list['body']['indexes']);
        $this->assertContains('embedding_euclidean', $keys);
        $this->assertContains('embedding_dot', $keys);
        $this->assertContains('embedding_cosine', $keys);

        // Get index by key
        $get = $this->client->call(Client::METHOD_GET, "/vectordb/{$databaseId}/collections/{$collectionId}/indexes/embedding_euclidean", [
            'content-type' => 'application/json',
            'x-appwrite-project' => $this->getProject()['$id'],
            'x-appwrite-key' => $this->getProject()['apiKey']
        ]);
        $this->assertEquals(200, $get['headers']['status-code']);
        $this->assertEquals('embedding_euclidean', $get['body']['key']);
        $this->assertEquals(Database::INDEX_HNSW_EUCLIDEAN, $get['body']['type']);

        // Delete index
        $del = $this->client->call(Client::METHOD_DELETE, "/vectordb/{$databaseId}/collections/{$collectionId}/indexes/embedding_dot", [
            'content-type' => 'application/json',
            'x-appwrite-project' => $this->getProject()['$id'],
            'x-appwrite-key' => $this->getProject()['apiKey']
        ]);
        $this->assertEquals(204, $del['headers']['status-code']);
        sleep(4);
        // Ensure it's gone
        $getMissing = $this->client->call(Client::METHOD_GET, "/vectordb/{$databaseId}/collections/{$collectionId}/indexes/embedding_dot", [
            'content-type' => 'application/json',
            'x-appwrite-project' => $this->getProject()['$id'],
            'x-appwrite-key' => $this->getProject()['apiKey']
        ]);
        $this->assertEquals(404, $getMissing['headers']['status-code']);
    }
}
