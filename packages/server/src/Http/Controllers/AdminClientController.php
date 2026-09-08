<?php

declare(strict_types=1);

namespace Bambamboole\LaravelOidc\Server\Http\Controllers;

use Bambamboole\LaravelOidc\Server\Clients\ClientAdministrationException;
use Bambamboole\LaravelOidc\Server\Clients\ClientAdministrator;
use Bambamboole\LaravelOidc\Server\Clients\ClientDefinition;
use Bambamboole\LaravelOidc\Server\Http\Middleware\AuthenticateAdminClient;
use Bambamboole\LaravelOidc\Server\Http\Requests\StoreClientRequest;
use Bambamboole\LaravelOidc\Server\Http\Requests\UpdateClientRequest;
use Bambamboole\LaravelOidc\Server\Http\Resources\ClientResource;
use Dedoc\Scramble\Attributes\Response as DocumentedResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Laravel\Passport\Client;

/**
 * The client administration API. Every action runs behind
 * {@see AuthenticateAdminClient}, which stashes the acting client id on the
 * request for the audit trail.
 */
class AdminClientController
{
    public function __construct(private readonly ClientAdministrator $clients) {}

    /**
     * List clients.
     *
     * Non-revoked, owner-less clients ordered by creation, cursor paginated.
     */
    #[DocumentedResponse(401, 'The bearer token is missing, invalid, expired, revoked, bound to a user, or addressed to another resource.', type: 'array{error: string}')]
    #[DocumentedResponse(403, 'The token or its client does not carry the admin scope.', type: 'array{error: string}')]
    public function index(Request $request): AnonymousResourceCollection
    {
        $request->validate(['per_page' => ['sometimes', 'integer', 'min:1', 'max:100']]);

        return ClientResource::collection(
            $this->clients->query()
                ->orderBy('created_at')
                ->orderBy('id')
                ->cursorPaginate($request->integer('per_page', 50)),
        );
    }

    /**
     * Create a client.
     *
     * Creates a confidential client. The response is the only place the
     * plaintext `client_secret` appears until it is rotated.
     */
    #[DocumentedResponse(400, 'The request body is not a JSON object.', type: 'array{message: string}')]
    #[DocumentedResponse(401, 'The bearer token is missing, invalid, expired, revoked, bound to a user, or addressed to another resource.', type: 'array{error: string}')]
    #[DocumentedResponse(403, 'The token or its client does not carry the admin scope.', type: 'array{error: string}')]
    public function store(StoreClientRequest $request): JsonResponse
    {
        $definition = (new ClientDefinition(name: ''))->with($request->definitionChanges());
        $created = $this->clients->create($definition, $this->actor($request));

        return (new ClientResource($created->client))->withSecret($created->plainSecret)->response()->setStatusCode(201);
    }

    /**
     * Get a client.
     */
    #[DocumentedResponse(404, 'The client does not exist, is revoked, or is owned by a user.', type: 'array{message: string}')]
    #[DocumentedResponse(401, 'The bearer token is missing, invalid, expired, revoked, bound to a user, or addressed to another resource.', type: 'array{error: string}')]
    #[DocumentedResponse(403, 'The token or its client does not carry the admin scope.', type: 'array{error: string}')]
    public function show(string $client): ClientResource
    {
        return new ClientResource($this->find($client));
    }

    /**
     * Update a client.
     *
     * Only the fields present in the body change; an empty object is a no-op.
     */
    #[DocumentedResponse(400, 'The request body is not a JSON object.', type: 'array{message: string}')]
    #[DocumentedResponse(404, 'The client does not exist, is revoked, or is owned by a user.', type: 'array{message: string}')]
    #[DocumentedResponse(409, 'The client is managed by an artisan command and cannot be modified through the API.', type: 'array{message: string}')]
    #[DocumentedResponse(401, 'The bearer token is missing, invalid, expired, revoked, bound to a user, or addressed to another resource.', type: 'array{error: string}')]
    #[DocumentedResponse(403, 'The token or its client does not carry the admin scope.', type: 'array{error: string}')]
    public function update(UpdateClientRequest $request, string $client): ClientResource
    {
        $model = $this->find($client);
        $definition = ClientDefinition::fromClient($model)->with($request->definitionChanges());

        return new ClientResource($this->administer(fn (): Client => $this->clients->update($model, $definition, $this->actor($request))));
    }

    /**
     * Revoke a client.
     *
     * Revokes the client and every token issued to it. A revoked client no
     * longer exists for this API.
     */
    #[DocumentedResponse(404, 'The client does not exist, is revoked, or is owned by a user.', type: 'array{message: string}')]
    #[DocumentedResponse(409, 'The client is managed by an artisan command, or it is the acting client itself.', type: 'array{message: string}')]
    #[DocumentedResponse(401, 'The bearer token is missing, invalid, expired, revoked, bound to a user, or addressed to another resource.', type: 'array{error: string}')]
    #[DocumentedResponse(403, 'The token or its client does not carry the admin scope.', type: 'array{error: string}')]
    public function destroy(Request $request, string $client): Response
    {
        $model = $this->find($client);
        $this->administer(fn () => $this->clients->revoke($model, $this->actor($request)));

        return response()->noContent();
    }

    /**
     * Rotate the client secret.
     *
     * Issues a new secret and returns it once. Tokens issued with the old
     * secret stay valid.
     */
    #[DocumentedResponse(404, 'The client does not exist, is revoked, or is owned by a user.', type: 'array{message: string}')]
    #[DocumentedResponse(409, 'The client is public and has no secret to rotate.', type: 'array{message: string}')]
    #[DocumentedResponse(401, 'The bearer token is missing, invalid, expired, revoked, bound to a user, or addressed to another resource.', type: 'array{error: string}')]
    #[DocumentedResponse(403, 'The token or its client does not carry the admin scope.', type: 'array{error: string}')]
    public function rotateSecret(Request $request, string $client): ClientResource
    {
        $model = $this->find($client);
        $rotated = $this->administer(fn () => $this->clients->rotateSecret($model, $this->actor($request)));

        return (new ClientResource($rotated->client))->withSecret($rotated->plainSecret);
    }

    private function find(string $clientId): Client
    {
        $client = $this->clients->find($clientId);

        if ($client === null) {
            abort(404, 'The client does not exist.');
        }

        return $client;
    }

    private function actor(Request $request): ?string
    {
        $actor = $request->attributes->get(AuthenticateAdminClient::ClientIdAttribute);

        return is_string($actor) ? $actor : null;
    }

    /**
     * @template T
     *
     * @param  callable(): T  $operation
     * @return T
     */
    private function administer(callable $operation): mixed
    {
        try {
            return $operation();
        } catch (ClientAdministrationException $exception) {
            abort(409, $exception->getMessage());
        }
    }
}
