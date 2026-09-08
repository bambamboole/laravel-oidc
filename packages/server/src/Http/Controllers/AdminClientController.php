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
    public function store(StoreClientRequest $request): JsonResponse
    {
        $definition = (new ClientDefinition(name: ''))->with($request->definitionChanges());
        $created = $this->clients->create($definition, $this->actor($request));

        return (new ClientResource($created->client))->withSecret($created->plainSecret)->response()->setStatusCode(201);
    }

    /**
     * Get a client.
     */
    public function show(string $client): ClientResource
    {
        return new ClientResource($this->find($client));
    }

    /**
     * Update a client.
     *
     * Only the fields present in the body change; an empty object is a no-op.
     */
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
    public function rotateSecret(Request $request, string $client): ClientResource
    {
        $model = $this->find($client);
        $rotated = $this->administer(fn () => $this->clients->rotateSecret($model, $this->actor($request)));

        return (new ClientResource($rotated->client))->withSecret($rotated->plainSecret);
    }

    private function find(string $clientId): Client
    {
        return $this->clients->find($clientId) ?? abort(404, 'The client does not exist.');
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
