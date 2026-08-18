<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\Users\Concerns;

use App\Models\User;

trait AdminUserPasswordUpdateTestHelpers
{
    use GrantsUsersSectionPermissions;

    protected function configureArrayCacheForThrottle(): void
    {
        config(['cache.default' => 'array']);
        $this->app->forgetInstance('cache');
        $this->app->forgetInstance('cache.store');
        $this->app->forgetInstance(\Illuminate\Contracts\Cache\Repository::class);
        $this->app->forgetInstance(\Illuminate\Cache\RateLimiter::class);
    }

    protected function passwordUpdateUrl(User $target): string
    {
        return route('admin.user.password.update', ['user' => $target->id]);
    }

    protected function makePasswordTarget(array $attributes = []): User
    {
        return User::factory()->create(array_merge([
            'partner_id' => $this->partner->id,
            'role_id'    => $this->adminRoleId(),
            'name'       => 'Цель',
            'lastname'   => 'Пароля',
            'password'   => 'current-pass-8',
        ], $attributes));
    }

    /**
     * @return list<array{method: string, url: string, data?: array<string, mixed>}>
     */
    protected function passwordUpdateHttpMethods(User $target): array
    {
        $url = $this->passwordUpdateUrl($target);

        return [
            ['method' => 'GET', 'url' => $url],
            ['method' => 'PUT', 'url' => $url, 'data' => ['password' => 'new-pass-88']],
            ['method' => 'PATCH', 'url' => $url, 'data' => ['password' => 'new-pass-88']],
            ['method' => 'DELETE', 'url' => $url],
            ['method' => 'POST', 'url' => $url, 'data' => ['password' => 'new-pass-88']],
        ];
    }
}
