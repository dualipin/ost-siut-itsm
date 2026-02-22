<?php
namespace App\Shared\Provider;

use App\Module\Auth\DTO\UserAuthContextDTO;
use App\Module\Usuario\Repository\UsuarioRepository;
use App\Shared\Context\UserContext;

final readonly class UserContextProvider implements ContextProviderInterface
{
    public function __construct(
        private UserContext $userContext,
        private UsuarioRepository $repository,
    ) {}

    public function get(): ?UserAuthContextDTO
    {
        $id = $this->userContext->get();
        return $this->repository->findAuthContextById($id);
    }
}
