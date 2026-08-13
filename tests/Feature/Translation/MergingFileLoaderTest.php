<?php

declare(strict_types=1);

use App\Support\Translation\MergingFileLoader;
use Illuminate\Filesystem\Filesystem;

beforeEach(function (): void {
    app()->setLocale('pt_BR');
});

it('resolves flat keys from the group root file', function (): void {
    expect(__('app.name'))->toBe('Coordena');
});

it('resolves dot notation into a merged subdirectory file', function (): void {
    // app/auth.php lives in a subdirectory, yet dot notation reaches it.
    expect(__('app.auth.login.title'))->toBe('Entre na sua conta');
});

it('returns the full merged tree for a group', function (): void {
    $tree = trans('app');

    expect($tree)
        ->toBeArray()
        ->toHaveKey('name')          // from lang/pt_BR/app.php
        ->toHaveKey('auth')          // from lang/pt_BR/app/auth.php
        ->toHaveKey('common');       // from lang/pt_BR/app/common.php
});

it('resolves whole-string messages from the json file', function (): void {
    expect(__('Team created.'))->toBe('Equipe criada.');
});

it('keeps framework translations working in pt_BR', function (): void {
    expect(__('auth.failed'))->toBe('Essas credenciais não correspondem aos nossos registros.');
});

it('merges nested subdirectories recursively', function (): void {
    $base = sys_get_temp_dir().'/mfl_'.uniqid();
    $files = new Filesystem;

    // lang/pt_BR/shop.php          -> shop.title
    // lang/pt_BR/shop/tenant/products.php -> shop.tenant.products.label
    $files->makeDirectory("{$base}/pt_BR/shop/tenant", 0755, true, true);
    $files->put("{$base}/pt_BR/shop.php", "<?php return ['title' => 'Loja'];");
    $files->put("{$base}/pt_BR/shop/tenant/products.php", "<?php return ['label' => 'Produtos'];");

    $loader = new MergingFileLoader($files, [$base]);
    $lines = $loader->load('pt_BR', 'shop');

    expect($lines)
        ->toBe([
            'title' => 'Loja',
            'tenant' => [
                'products' => [
                    'label' => 'Produtos',
                ],
            ],
        ]);

    $files->deleteDirectory($base);
});
