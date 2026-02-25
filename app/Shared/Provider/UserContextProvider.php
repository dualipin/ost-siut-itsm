<?php

namespace App\Shared\Provider;

use App\Module\Auth\DTO\UserAuthContextDTO;
use App\Module\Usuario\Repository\UsuarioRepository;
use App\Shared\Context\UserContext;

/**
 * @extends AbstractContextProvider<UserAuthContextDTO>
 */
final class UserContextProvider extends AbstractContextProvider
{
    public function __construct(
        private readonly UserContext $context,
        private readonly UsuarioRepository $repository,
    ) {}

    protected function resolve(): ?UserAuthContextDTO
    {
        $session = $this->context->get();

        if ($session === null) {
            return null;
        }

        return $this->repository->findAuthContextById($session->id);
    }
}
