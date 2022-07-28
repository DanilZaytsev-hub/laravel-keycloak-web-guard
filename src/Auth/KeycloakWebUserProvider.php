<?php

namespace Vizir\KeycloakWebGuard\Auth;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Auth\UserProvider;
use Vizir\KeycloakWebGuard\Models\KeycloakUser;
use Illuminate\Contracts\Hashing\Hasher as HasherContract;
use Illuminate\Auth\EloquentUserProvider;

class KeycloakWebUserProvider implements UserProvider
{
    /**
     * The user model.
     *
     * @var string
     */
    protected $model;

    /**
     * The fallback eloquent user provider.
     *
     * @var EloquentUserProvider
     */
    protected $eloquent;

    /**
     * The Constructor
     *
     * @param string $model
     */
    public function __construct(HasherContract $hasher, $model)
    {
        $this->model = $model;
        $this->eloquent = new EloquentUserProvider($hasher, $model);
    }

    /**
     * Retrieve a user by the given credentials.
     *
     * @param  array  $credentials
     * @return \Illuminate\Contracts\Auth\Authenticatable|null
     */
    public function retrieveByCredentials(array $credentials)
    {
        $syncAttributes = config('keycloak-web.sync_attributes');
        $userData = [];
        foreach ($syncAttributes as $modelAttribute => $keycloakField) {
            $userData[$modelAttribute] = $credentials[$keycloakField] !== '' ? $credentials[$keycloakField] : null;
        }
        $user = $this->eloquent->retrieveByCredentials(['email' => $userData['email']]);
        
        if (!$user) {
            $class = '\\'.ltrim($this->model, '\\');
            $user = new $class($userData);
            $user->save();
        } else {
            foreach ($userData as $key => $value) {
                $user->{$key} = $value;
            }
            $user->save();
        }

        return $user;
    }

    /**
     * Retrieve a user by their unique identifier.
     *
     * @param  mixed  $identifier
     * @return \Illuminate\Contracts\Auth\Authenticatable|null
     */
    public function retrieveById($identifier)
    {
        return $this->eloquent->retrieveById($identifier);
    }

    /**
     * Retrieve a user by their unique identifier and "remember me" token.
     *
     * @param  mixed  $identifier
     * @param  string  $token
     * @return \Illuminate\Contracts\Auth\Authenticatable|null
     */
    public function retrieveByToken($identifier, $token)
    {
        return $this->eloquent->retrieveByToken($identifier, $token);
    }

    /**
     * Update the "remember me" token for the given user in storage.
     *
     * @param  \Illuminate\Contracts\Auth\Authenticatable  $user
     * @param  string  $token
     * @return void
     */
    public function updateRememberToken(Authenticatable $user, $token)
    {
        return $this->eloquent->updateRememberToken($user, $token);
    }

    /**
     * Validate a user against the given credentials.
     *
     * @param  \Illuminate\Contracts\Auth\Authenticatable  $user
     * @param  array  $credentials
     * @return bool
     */
    public function validateCredentials(Authenticatable $user, array $credentials)
    {
        throw new \BadMethodCallException('Unexpected method [validateCredentials] call');
    }
}
