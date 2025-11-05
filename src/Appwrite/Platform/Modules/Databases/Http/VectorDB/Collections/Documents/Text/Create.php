<?php

namespace Appwrite\Platform\Modules\Databases\Http\VectorDB\Collections\Documents\Text;

use Appwrite\Platform\Modules\Databases\Http\Databases\Collections\Documents\Action as CreateDocumentAction;
use Appwrite\SDK\AuthType;
use Appwrite\Auth\Auth;
use Appwrite\SDK\ContentType;
use Appwrite\SDK\Method;
use Appwrite\SDK\Parameter;
use Appwrite\SDK\Response as SDKResponse;
use Appwrite\Utopia\Response as UtopiaResponse;
use Utopia\Database\Document;
use Utopia\Database\Database;
use Utopia\Database\Validator\Authorization;
use Appwrite\Extend\Exception;
use Utopia\Database\Validator\UID;
use Utopia\Swoole\Response as SwooleResponse;
use Utopia\Database\Validator\ObjectValidator;
use Utopia\Validator\ArrayList;
// Agent is dynamically provided via container; avoid strict type to pass lints

class Create extends CreateDocumentAction
{
    public static function getName(): string
    {
        return 'createVectorDBDocument';
    }

    protected function getResponseModel(): string
    {
        return UtopiaResponse::MODEL_EMBEDDING;
    }

    protected function getBulkResponseModel(): string
    {
        return UtopiaResponse::MODEL_DOCUMENT_LIST;
    }

    public function __construct()
    {
        $this
            ->setHttpMethod(self::HTTP_REQUEST_METHOD_POST)
            ->setHttpPath('/v1/vectordb/:databaseId/collections/:collectionId/text')
            ->desc('Create Text Embedding')
            ->groups(['api', 'database'])
            ->label('scope', 'documents.read')
            ->label('resourceType', RESOURCE_TYPE_DATABASES)
            ->label('audits.event', 'embedding.create')
            ->label('audits.resource', 'database/{request.databaseId}/collection/{request.collectionId}')
            ->label('abuse-key', 'ip:{ip},method:{method},url:{url},userId:{userId}')
            ->label('abuse-limit', APP_LIMIT_WRITE_RATE_DEFAULT * 2)
            ->label('abuse-time', APP_LIMIT_WRITE_RATE_PERIOD_DEFAULT)
            ->label('sdk', [
                new Method(
                    namespace: 'vectorDB',
                    group: $this->getSdkGroup(),
                    name: 'createTextEmbedding',
                    desc: 'Create Text Embedding',
                    description: '/docs/references/vectordb/create-document.md',
                    auth: [AuthType::SESSION, AuthType::KEY, AuthType::JWT],
                    responses: [
                        new SDKResponse(
                            code: SwooleResponse::STATUS_CODE_OK,
                            model: $this->getResponseModel(),
                        )
                    ],
                    contentType: ContentType::JSON,
                    parameters: [
                        new Parameter('databaseId', optional: false),
                        new Parameter('collectionId', optional: false),
                        new Parameter('data', optional: false),
                    ]
                )
            ])
            ->param('databaseId', '', new UID(), 'Database ID.')
            ->param('collectionId', '', new UID(), 'Collection ID. You can create a new collection using the Database service [server integration](https://appwrite.io/docs/server/databases#databasesCreateCollection). Make sure to define attributes before creating documents.')
            ->param('data', [], new ArrayList(new ObjectValidator()), 'Array of items to embed. Each item should contain text and embeddingModel.', false)
            ->inject('response')
            ->inject('embeddingAgent')
            ->inject('dbForProject')
            ->inject('getDatabasesDB')
            ->callback($this->action(...));
    }

    public function action(string $databaseId, string $collectionId, array $data, UtopiaResponse $response, $embeddingAgent, Database $dbForProject, callable $getDatabasesDB): void
    {
        $isAPIKey = Auth::isAppUser(Authorization::getRoles());
        $isPrivilegedUser = Auth::isPrivilegedUser(Authorization::getRoles());

        $database = Authorization::skip(fn () => $dbForProject->getDocument('databases', $databaseId));
        if ($database->isEmpty() || (!$database->getAttribute('enabled', false) && !$isAPIKey && !$isPrivilegedUser)) {
            throw new Exception(Exception::DATABASE_NOT_FOUND);
        }

        $collection = Authorization::skip(fn () => $dbForProject->getDocument('database_' . $database->getSequence(), $collectionId));
        if ($collection->isEmpty() || (!$collection->getAttribute('enabled', false) && !$isAPIKey && !$isPrivilegedUser)) {
            throw new Exception($this->getParentNotFoundException());
        }

        $item = $data[0] ?? [];
        $text = $item['text'] ?? '';
        $model = $item['embeddingModel'] ?? ($item['embeddigModel'] ?? 'embeddinggemma');

        $vector = $embeddingAgent->embeddings($text, $model);

        $payload = new Document([
            'model' => $model,
            'dimensions' => 768,
            'embeddings' => $vector,
        ]);

        $response
            ->setStatusCode(SwooleResponse::STATUS_CODE_OK)
            ->dynamic($payload, $this->getResponseModel());
    }
}
