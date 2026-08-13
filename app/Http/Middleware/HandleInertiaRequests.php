<?php

namespace App\Http\Middleware;

use App\Enums\TeamPermission;
use App\Models\User;
use App\Support\WhatsappTerms;
use Callcocam\WhatsAppCloud\Support\ArrayCredentials;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $user = $request->user();

        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'auth' => [
                'user' => $user,
            ],
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
            'currentTeam' => fn () => $user?->currentTeam ? $user->toUserTeam($user->currentTeam) : null,
            'teams' => fn () => $user?->toUserTeams(includeCurrent: true) ?? [],
            'permissions' => fn (): array => $user?->currentTeam
                ? $user->permissionNamesForTeam($user->currentTeam)->mapWithKeys(fn (string $name): array => [$name => true])->all()
                : [],
            'cargos' => fn (): array => $user?->currentTeam
                ? $user->cargosForTeam($user->currentTeam)->map(fn ($cargo): array => ['key' => $cargo->key, 'name' => $cargo->name])->values()->all()
                : [],
            'translations' => fn (): array => [
                'app' => trans('app'),
                'auth' => trans('auth'),
                'passwords' => trans('passwords'),
                'pagination' => trans('pagination'),
                'validation' => trans('validation'),
            ],
            'whatsapp' => fn (): ?array => $this->whatsappState($user),
            'locale' => app()->getLocale(),
        ];
    }

    /**
     * Reusable WhatsApp connection state for the current team, consumed by the
     * settings connection card and anywhere WhatsApp-dependent options are gated.
     *
     * @return array{apiEnabled: bool, connected: bool, canManage: bool, canManageTemplates: bool, termsAccepted: bool, metaConfigured: bool, usesSharedNumber: bool, verifiedName: string|null, qualityRating: string|null, messagingLimit: string|null}|null
     */
    protected function whatsappState(?User $user): ?array
    {
        $team = $user?->currentTeam;

        if ($team === null) {
            return null;
        }

        $connection = $team->whatsappConnection;
        $canManage = $user->hasTeamPermission($team, TeamPermission::ManageWhatsapp);
        $metaConfigured = $connection?->hasCloudCredentials() ?? false;

        // The team can still send without its own number when a shared instance
        // number is configured (whatsapp-cloud.default) — the resolver falls back
        // to it. Mirror that here so the UI reflects the real state.
        $sharedConfigured = ArrayCredentials::fromArray((array) config('whatsapp-cloud.default')) !== null;

        return [
            'apiEnabled' => $team->usesWhatsappApi(),
            'connected' => $connection?->isConnected() ?? false,
            'canManage' => $canManage,
            // Painel de templates da WABA compartilhada — restrito pelo gate
            // manage-whatsapp-templates (allowlist), não pela permissão do time.
            'canManageTemplates' => $user->can('manage-whatsapp-templates'),
            'termsAccepted' => $canManage && WhatsappTerms::accepted($user, $team),
            // Estado do número oficial da Meta (Cloud API).
            'metaConfigured' => $metaConfigured,
            // Sem número próprio, mas enviando pelo número compartilhado do Coordena.
            'usesSharedNumber' => ! $metaConfigured && $sharedConfigured,
            'verifiedName' => $connection?->verified_name,
            'qualityRating' => $connection?->quality_rating,
            'messagingLimit' => $connection?->messaging_limit,
        ];
    }
}
